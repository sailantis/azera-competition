<?php
/**
 * Azera adapter — wires Azera's router/dispatcher/Clarity/Model over SQLite
 * and dispatches synthetic requests in-process.
 */

use App\Bootstrap;
use Azera\AppContext;
use Azera\Http\Request;
use Azera\Http\Response;

class AzeraAdapter implements WebAppAdapter
{
    private AppContext $ctx;
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = __DIR__ . '/../data/bench.sqlite';
    }

    public function name(): string
    {
        return 'azera';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the App namespace used by the Azera benchmark app.
        // Azera's own autoloader is provided by composer.
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = __DIR__ . '/../apps/azera/' . str_replace('\\', '/', $relative) . '.php';
            // Guard: other benchmark apps (e.g. App\Spiral\...) share the App\
            // prefix but live elsewhere — let their autoloaders handle them.
            if (is_file($file)) {
                require $file;
            }
        });

        $this->ctx = \App\Bootstrap::boot($this->dbPath);
    }

    public function dispatch(string $method, string $uri): string
    {
        // Build a synthetic request via Azera's Request using a fake $_SERVER.
        $server = [
            'REQUEST_URI'     => $uri,
            'REQUEST_METHOD'  => $method,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST'       => 'bench.local',
            'SCRIPT_NAME'     => '/index.php',
        ];

        // Override AppContext's request so route matching sees our synthetic URI.
        $request = new Request($server);
        $this->ctx->set(\Azera\Http\Request::class, $request);

        $path   = $request->path();
        $method = $request->method();

        $route = $this->ctx->router()->match($path, $method);
        if ($route === null) {
            return '404 Not Found';
        }

        $response = $this->ctx->dispatcher()->dispatch($route);

        return self::readBody($response);
    }

    /**
     * Read the protected Response::$body via reflection (no public getter).
     */
    private static function readBody(Response $response): string
    {
        $r = new ReflectionProperty(Response::class, 'body');
        return (string) $r->getValue($response);
    }
}