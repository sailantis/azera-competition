<?php
/**
 * Azera adapter — wires Azera's router/dispatcher/Clarity/Model over SQLite
 * and dispatches synthetic requests in-process.
 */

use App\Azera\Bootstrap;
use Azera\AppContext;
use Azera\Http\Request;

class AzeraAdapter implements WebAppAdapter
{
    private AppContext $ctx;
    private string $dbPath;

    /** Status of the most recent dispatch — see WebAppAdapter::lastStatus(). */
    private int $status = 200;

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
        // The query string is passed explicitly: `Request` falls back to `$_GET`,
        // which is empty under the in-process harness, so `?page=2` would silently
        // serve page 1 (the FPM entry reads the real superglobal and does page
        // correctly — the two arms must agree).
        $server = [
            'REQUEST_URI'     => $uri,
            'REQUEST_METHOD'  => $method,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST'       => 'bench.local',
            'SCRIPT_NAME'     => '/index.php',
        ];

        $query = [];
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);

        // Reset first: an exception below must not publish the previous request's
        // status (see WebAppAdapter::lastStatus()).
        $this->status = 200;

        // The framework's own front controller, not its primitives.
        //
        // This used to call router()->match() and dispatcher()->dispatch() by hand,
        // which skipped the layer every other entrant runs: Symfony and Laravel
        // through HttpKernel::handle(), CakePHP through Server::run(), and now
        // Phalcon through Mvc\Application::handle(). Kernel owns the parts a front
        // controller is for — the 404 and 405 bodies, the error boundary that turns a
        // thrown exception into a 500 instead of a fatal, and the queued-cookie
        // flush — so calling the router directly meant Azera was the only entrant
        // measured without one and its own 404 page was never exercised.
        //
        // Measured, Kernel is not a cost: on GET /api/items-fast it ran 36.4 us
        // against 34.8 us for the manual pair (interleaved, order-rotated), and the
        // bodies are byte-identical.
        $response = $this->ctx->kernel()->handle(new Request($server, $query));

        $this->status = $response->getStatus();

        return $response->getBody();
    }

    public function lastStatus(): int
    {
        return $this->status;
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
}