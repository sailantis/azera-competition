<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 adapter — boots the real CI4 kernel in-process and
 * dispatches synthetic requests through CodeIgniter::run($routes, true)
 * (the official returnResponse mode used by the framework's own tests).
 *
 * Boot sequence mirrors system/Boot.php (bootWeb) minus the parts a
 * long-running worker cannot do (header(), exit()): constants, Common
 * functions, the CI4 autoloader, the exception handler, then
 * service('codeigniter')->initialize() + setContext('web').
 *
 * Between requests CodeIgniter::resetForWorkerMode() releases request-
 * scoped state — the same call FrankenPHP worker mode makes.
 */

final class CodeIgniterAdapter implements WebAppAdapter
{
    private const APP_NAMESPACE = 'Ci4App';

    private ?\CodeIgniter\CodeIgniter $app = null;

    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = __DIR__ . '/../data/bench.sqlite';
        // Ensure data dir exists (fresh clone) — shared with all adapters.
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
    }

    public function name(): string
    {
        return 'codeigniter';
    }

    public function bootstrap(): void
    {
        if ($this->app instanceof \CodeIgniter\CodeIgniter) {
            // Warm re-bootstrap (cold mode): reset request-scoped state.
            $this->app->resetForWorkerMode();

            return;
        }

        $this->bootFramework();

        $this->app = service('codeigniter');
        $this->app->initialize();
        $this->app->setContext('web');
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->app instanceof \CodeIgniter\CodeIgniter);

        // Spoof the SAPI env for the incoming request. CI4 reads
        // $_SERVER[REQUEST_URI] per its App::$uriProtocol, REMOTE_ADDR for
        // rate limiting parity and REQUEST_METHOD for method spoofing.
        $_SERVER['REQUEST_METHOD']  = $method;
        $_SERVER['REQUEST_URI']     = $uri;
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTP_HOST']       = 'bench.local';

        // Refresh the Superglobals service snapshot — it captured $_SERVER
        // at construction and would otherwise freeze the first request's
        // URI forever (same call the official FrankenPHP worker makes).
        service('superglobals')
            ->setServerArray($_SERVER)
            ->setGetArray($_GET)
            ->setPostArray($_POST)
            ->setCookieArray($_COOKIE)
            ->setFilesArray($_FILES)
            ->setRequestArray($_REQUEST);

        try {
            // run() returns ?ResponseInterface because $returnResponse=true.
            $response = $this->app->run(null, true);
        } catch (\Throwable $e) {
            $response = null;
            $error    = '500 ' . get_class($e) . ': ' . $e->getMessage();
        }

        // Worker-mode teardown between requests (mirrors the FrankenPHP
        // worker loop): drop request-scoped services so URI/request/filter
        // state cannot leak into the next dispatch.
        \CodeIgniter\Config\Factories::reset();
        \CodeIgniter\Config\BaseService::resetForWorkerMode(new \Config\WorkerMode());

        if (!isset($response)) {
            return $error;
        }

        $this->app->resetForWorkerMode();

        return (string) $response->getBody();
    }

    /**
     * One-time framework boot (constants + autoloader + handlers).
     */
    private function bootFramework(): void
    {
        $root     = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $appDir   = $root . 'apps/codeigniter/';
        $sysDir   = $root . 'vendor/codeigniter4/framework/system/';
        $writeDir = $root . 'writable/ci4/';

        foreach ([$writeDir, $writeDir . 'cache', $writeDir . 'logs', $writeDir . 'session'] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }

        // 1. Path + env constants — BEFORE anything references them.
        if (!defined('APP_NAMESPACE')) {
            define('APP_NAMESPACE', self::APP_NAMESPACE);
        }
        if (!defined('ROOTPATH')) {
            define('ROOTPATH', $root);
        }
        if (!defined('APPPATH')) {
            define('APPPATH', $appDir);
        }
        if (!defined('SYSTEMPATH')) {
            define('SYSTEMPATH', $sysDir);
        }
        if (!defined('WRITEPATH')) {
            define('WRITEPATH', $writeDir);
        }
        if (!defined('TESTPATH')) {
            define('TESTPATH', $root . 'tests/');
        }
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'production');
        }
        if (!defined('CI_DEBUG')) {
            define('CI_DEBUG', false);
        }

        // 2. Framework constants (timing constants, COMPOSER_PATH, exit codes).
        //    Shippped in the sample app dir (Boot::loadConstants() does the
        //    same require from APPPATH); pre-defined APP_NAMESPACE wins there.
        require_once $root . 'vendor/codeigniter4/framework/app/Config/Constants.php';

        // 3. Common functions (service(), config(), is_cli(), view(), ...).
        require_once SYSTEMPATH . 'Common.php';

        // 4. Autoloader config classes must exist before instantiation.
        //    NOTE: the Config\ namespace is framework-fixed (Autoloader::
        //    initialize() hard-types Config\Autoload / Config\Modules).
        if (!class_exists(\Config\Autoload::class, false)) {
            require_once SYSTEMPATH . 'Config/AutoloadConfig.php';
            require_once APPPATH . 'Config/Autoload.php';
            require_once SYSTEMPATH . 'Modules/Modules.php';
            require_once APPPATH . 'Config/Modules.php';
        }

        require_once SYSTEMPATH . 'Autoloader/Autoloader.php';
        require_once SYSTEMPATH . 'Config/BaseService.php';
        require_once SYSTEMPATH . 'Config/Services.php';
        require_once APPPATH . 'Config/Services.php';

        // 5. Register the CI4 autoloader (PSR-4 Ci4App => apps/codeigniter
        //    plus the CodeIgniter + Config core mappings).
        \CodeIgniter\Config\Services::autoloader()
            ->initialize(new \Config\Autoload(), new \Config\Modules())
            ->register();

        // 6. Exception handler + helpers.
        service('exceptions')->initialize();
        service('autoloader')->loadHelpers();

        // 7. Point the Database config at the shared benchmark SQLite file
        //    before any connection is opened (config is a singleton; the
        //    controller connections later reuse this path).
        $database = config(\Config\Database::class);
        $database->default['database'] = $this->dbPath;
    }
}