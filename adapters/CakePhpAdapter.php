﻿
<?php

/**
 * CakePHP 5 adapter — boots a real Cake application (BaseApplication +
 * RoutingMiddleware + ControllerFactory + View templates + Table ORM over
 * the shared SQLite database) and dispatches synthetic requests in-process.
 *
 * This mirrors a stock Cake 5 app skeleton (routes() hook, controllers,
 * templates/, src/Model/Table), so boot/dispatch costs reflect what Cake
 * users actually pay.
 */

use App\Cake\Application as BenchApp;
use App\Cake\Db;
use Cake\Core\Configure;
use Cake\Http\Server;

class CakePhpAdapter implements WebAppAdapter
{
    private ?Server $server = null;

    public function name(): string
    {
        return 'cakephp';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Cake benchmark app namespace.
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\Cake\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = __DIR__ . '/../apps/cakephp/src/' . str_replace('\\', '/', $relative) . '.php';
            // Guard required when multiple adapters share one process.
            if (is_file($file)) {
                require $file;
            }
        });

        $root = \dirname(__DIR__) . '/';

        // Global h()/pr()/pluginSplit() wrappers (opt-in file in Cake 5; a
        // stock app skeleton requires it — templates call global h()).
        require_once $root . 'vendor/cakephp/cakephp/src/Core/functions_global.php';

        // --- App-skel path constants (a stock webroot/index.php defines them;
        // App::core()/Debugger reference them) -----------------------------
        $cakeCore = $root . 'vendor/cakephp/cakephp/';
        $writable = $root . 'writable/cakephp/';
        if (!is_dir($writable)) {
            @mkdir($writable, 0777, true);
        }
        if (!defined('CAKE_CORE_INCLUDE_PATH')) {
            define('CAKE_CORE_INCLUDE_PATH', $cakeCore);
            define('CORE_PATH', $cakeCore);
            define('CAKE', $cakeCore . 'src' . DIRECTORY_SEPARATOR);
            define('ROOT', $root);
            define('APP_DIR', 'cakephp');
            define('APP', $root . 'apps/cakephp' . DIRECTORY_SEPARATOR);
            define('TMP', $writable);
            define('LOGS', $writable . 'logs' . DIRECTORY_SEPARATOR);
            define('CACHE', $writable . 'cache' . DIRECTORY_SEPARATOR);
        }

        // App-class + template path resolution (stock app/config/app.php
        // sets these; we replicate the defaults for our custom namespace).
        Configure::write('App.namespace', 'App\Cake');
        Configure::write('App.paths.templates', [$root . 'apps/cakephp/templates/']);
        // Response::$_charset is string-typed; App.encoding must exist.
        Configure::write('App.encoding', 'UTF-8');

        Db::init($root . 'data/bench.sqlite');

        // I18n/Validator need a real cache config for the translator pool.
        // Bootstrap can run twice in one process (run.php re-boots per mode)
        // — guard against StaticConfigTrait's no-reconfigure rule.
        if (\Cake\Cache\Cache::getConfig('_cake_translations_') === null) {
            \Cake\Cache\Cache::setConfig('_cake_translations_', [
                'className' => 'Cake\Cache\Engine\ArrayEngine',
                'duration'  => '+10 seconds',
            ]);
        }
        if (\Cake\Cache\Cache::getConfig('_cake_core_') === null) {
            \Cake\Cache\Cache::setConfig('_cake_core_', [
                'className' => 'Cake\Cache\Engine\ArrayEngine',
                'duration'  => '+10 seconds',
            ]);
        }

        // Typed static $_collection is only initialized in reload() (the
        // stock bootstrap does this indirectly) — call it up front.
        \Cake\Routing\Router::reload();

        $this->server = new Server(new BenchApp($root . 'apps/cakephp/config/'));
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->server instanceof Server);

        // ServerRequestFactory::fromGlobals() reads $_SERVER; give it a
        // minimal spoof per request (same approach as the CI4 worker).
        $server = [
            'REQUEST_METHOD'  => $method,
            'REQUEST_URI'     => $uri,
            'QUERY_STRING'    => '',
            'HTTP_HOST'       => 'bench.local',
            'REMOTE_ADDR'     => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SCRIPT_NAME'     => 'index.php',
        ];

        try {
            $request  = \Cake\Http\ServerRequestFactory::fromGlobals($server);
            $response = $this->server->run($request);
        } catch (\Throwable $e) {
            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }

        return (string) $response->getBody();
    }
}