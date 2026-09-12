<?php
/**
 * Azera adapter — wires Azera's router/dispatcher/Clarity/Model over SQLite
 * and dispatches synthetic requests in-process.
 */

use App\Azera\Bootstrap;
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
        // Ensure the data directory exists so SQLite can create/open the DB
        // file (on a fresh clone the data/ dir may not be present).
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
    }

    public function name(): string
    {
        return 'azera';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the App namespace used by the Azera benchmark app.
        // Azera's own autoloader is provided by composer. The shared loader is
        // idempotent — re-calling this from every bootstrap() never grows the
        // SPL stack (see BenchmarkAutoloader docblock).
        BenchmarkAutoloader::map('App\\Azera\\', __DIR__ . '/../apps/azera');

        $this->ctx = \App\Azera\Bootstrap::boot($this->dbPath);
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
     * Persistent-worker contract: reset per-request state (request, route,
     * ORM heap + EntityManager, RequestScoped services) between synthetic
     * requests, exactly like a real worker loop would. Without this the
     * request-scoped heap and EM retain every request's entities — visible
     * as steadily growing memory and (via the entityFor scan) steadily
     * growing latency across a benchmark run.
     */
    public function cleanup(): void
    {
        $this->ctx->clearRequestScope();
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