<?php

/**
 * Phalcon 6 adapter — boots the pure-PHP Phalcon app over the shared SQLite
 * database and dispatches synthetic requests in-process.
 *
 * Phalcon 6 is a composer package, not a C extension. That is the only reason
 * this entrant exists: v5 would have needed the extension compiled against the
 * exact PHP the benchmark measures, which is a build step the other six apps do
 * not have. The trade-off is honest and worth stating wherever these numbers are
 * published — v6 is at RC, so it is measured against released frameworks and
 * must be labelled as such.
 */

use App\Phalcon\Bootstrap;

class PhalconAdapter implements WebAppAdapter
{
    private ?Bootstrap $app = null;

    /** Status of the most recent dispatch — see WebAppAdapter::lastStatus(). */
    private int $status = 200;

    public function name(): string
    {
        return 'phalcon';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the app namespace (shared, idempotent loader).
        BenchmarkAutoloader::map('App\\Phalcon\\', __DIR__ . '/../apps/phalcon/src');

        $dbPath = __DIR__ . '/../data/bench.sqlite';
        $dataDir = dirname($dbPath);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }

        // Volt's compiled-template cache. Created here so the first timed
        // request does not pay for a mkdir, and cleaned when the app source is
        // newer than the compiled output — a stale compile would serve a
        // template from different code, which is the same hazard Symfony's
        // clearStaleCache() guards against.
        $cacheDir = __DIR__ . '/../apps/phalcon/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $this->app = Bootstrap::boot($dbPath);

        // Warm the router and the template compiler now, so bootstrap() carries
        // the one-time cost instead of the first timed request.
        $this->app->handle('GET', '/');
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->app instanceof Bootstrap);

        $this->status = 200;

        try {
            $body = $this->app->handle($method, $uri);

            $this->status = $this->app->lastStatus();

            return $body;
        } catch (\Throwable $e) {
            $this->status = 500;

            return '500 ' . get_class($e) . ': ' . $e->getMessage();
        }
    }

    public function lastStatus(): int
    {
        return $this->status;
    }

    /**
     * Per-request teardown, so the identity map and metadata cache do not
     * accumulate across a resident run — the same reset every other adapter
     * performs in cleanup().
     */
    public function cleanup(): void
    {
        \assert($this->app instanceof Bootstrap);

        $this->app->cleanup();
    }
}
