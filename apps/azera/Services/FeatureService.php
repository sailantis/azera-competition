<?php
/**
 * Feature demo service — exercises AOP #[Transactional] and #[Cache].
 *
 * This service is autowired (registered as a class string, not a factory)
 * so AppContext::build() generates an AOP proxy.  The #[Advised] attribute
 * on the class marks it for proxy generation; individual methods carry
 * #[Transactional] or #[Cache] to activate interception.
 */

namespace App\Azera\Services;

use Azera\Aop\Advised;
use Azera\Aop\Cache;
use Azera\Aop\Transactional;
use Azera\AppContext;
use Azera\Db\Query;

#[Advised]
class FeatureService
{
    /** Fixed row used by every feature-demo write — the row count stays stable across benchmark runs. */
    public const FEATURE_SENTINEL_ID = 888800;

    /**
     * Cache key of the cached COUNT.
     *
     * Shared by the `#[Cache]` advice (below) and the hand-written control in
     * `FeatureController::aopCacheDirectAction()`, so the two endpoints read and
     * write the SAME entry — which is what makes them a same-work pair rather
     * than two demos that happen to look alike.
     */
    public const CACHE_KEY = 'item_count';

    public function __construct(
        private AppContext $ctx,
    ) {}

    /**
     * Write the feature sentinel row and dispatch its event — the ONE workload
     * every AOP demo in this app runs.
     *
     * Extracted so the three demos cannot drift apart. `#[Transactional]` (proxy),
     * the explicit `Pipeline` and the hand-written control all call this same
     * method, so the transaction handling is genuinely the only variable between
     * them. The `WriteTitleUniqueness` and `AdvicePair` guards both depend on that:
     * a twin that wrote a different row would be measuring a different workload
     * under a label claiming otherwise.
     */
    private function writeSentinel(string $title): int
    {
        $db = $this->ctx->dbManager()->getOrDefault('default');
        $db->query(
            'INSERT INTO items (id, title, created_at) VALUES (?, ?, ?)'
                . ' ON CONFLICT(id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at',
            [self::FEATURE_SENTINEL_ID, $title, date('Y-m-d H:i:s')],
        );
        $id = self::FEATURE_SENTINEL_ID;

        // Dispatch an event — the listener will run synchronously
        // because we use EventDispatcher (PSR-14).
        $this->ctx->events()->dispatch(new \App\Azera\Events\ItemCreated($id, $title));

        return $id;
    }

    /**
     * Create an item inside a transaction.
     *
     * The #[Transactional] interceptor wraps this method in a DB
     * transaction: begin before, commit on success, rollback on
     * exception.  No manual begin/commit/rollback needed.
     *
     * Writes the FIXED feature sentinel row (INSERT ... ON CONFLICT DO
     * UPDATE, upsert semantics) instead of a fresh INSERT per request —
     * the demo runs once per benchmark request, and an unbounded INSERT
     * would grow the shared table every request (the row count must stay
     * stable so COUNT-style reads measure constant work).
     */
    #[Transactional]
    public function createItemTransactional(string $title): int
    {
        return $this->writeSentinel($title);
    }

    /**
     * Create an item with NO advice and NO transaction — plain autocommit.
     *
     * `GET /features/db-events` calls this. That row is about the database EVENT
     * PIPELINE, not about interception: it writes a row and reads a COUNT so the
     * framework's query/transaction events have something to fire on, then
     * reports what its listener captured.
     *
     * It used to call {@see createItemTransactional()}, which carries
     * `#[Transactional]`. That charged the row a generated-proxy dispatch and a
     * `TransactionalInterceptor` frame the other six entrants do not pay, and —
     * worse — it made Azera the only entrant whose demo opened a transaction
     * here. Three of the others then described transaction events their own
     * code could not produce. Same reasoning as the `log` row: advice belongs to
     * the `aop-*` features, which measure it on their own rows.
     *
     * On an autocommit connection the event log therefore reports queries only,
     * which is what every entrant without an explicit `begin` reports too.
     */
    public function createItemAutocommit(string $title): int
    {
        return $this->writeSentinel($title);
    }

    /**
     * Create an item with NO advice and NO transaction — the body that the
     * explicit-`Pipeline` demo wraps.
     *
     * `GET /features/aop-tx-explicit` feeds this method through
     * `Azera\Aop\Pipeline` with a real `TransactionalInterceptor`, so the
     * BEGIN/COMMIT/ROLLBACK that the endpoint measures is executed by framework
     * machinery the caller composed, not by this method. The method itself must
     * therefore contain no transaction handling at all; the interceptor is the
     * only thing that opens one.
     *
     * This is what makes Azera's two AOP modes comparable on identical work:
     * the proxy route reaches {@see createItemTransactional()} and this one
     * reaches the same `writeSentinel()` call, differing only in which layer
     * supplies the transaction.
     */
    public function createItemUnmanaged(string $title): int
    {
        return $this->writeSentinel($title);
    }

    /**
     * Count items — result is cached for 10 seconds via #[Cache].
     *
     * The #[Cache] interceptor checks the cache before invoking the
     * method; on a miss it calls the method and stores the result.
     * The key is interpolated from the {segment} argument.
     *
     * The body is a real aggregate query and nothing else. An artificial
     * delay here (this used to usleep 50ms) is not a framework cost: it is
     * added to every app's cache-MISS row, and the deployment models differ
     * in whether they miss. php-fpm rebuilds the app context per request, so
     * every app always missed and paid it on every request, while a resident
     * RoadRunner worker hit and paid nothing — the delay measured the harness,
     * not the cache. On php-fpm it was 67% of azera's total-response-time
     * metric and it flattened the spread between frameworks from 15.3x to
     * 6.0x.
     */
    #[Cache(ttl: 10, key: self::CACHE_KEY)]
    public function countItems(): int
    {
        $row = $this->ctx->dbManager()
            ->getOrDefault('default')
            ->selectRow('SELECT COUNT(*) AS c FROM items', null, \PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Create an item inside a hand-written transaction — the CONTROL row.
     *
     * This is deliberately NOT an AOP demo: it is plain `begin`/`commit`/
     * `rollback` in application code, with no interceptor involved. It exists so
     * `GET /features/aop-tx-direct` has the same-work control that every entrant can
     * serve, which is what makes that row comparable across frameworks at all.
     *
     * Azera's own explicit-interceptor facility is measured separately, on
     * `GET /features/aop-tx-explicit`, through {@see createItemUnmanaged()} plus a
     * real `Azera\Aop\Pipeline`. Keeping the two apart matters: if this method
     * called the framework's `TransactionalInterceptor` it would be a different
     * workload from the six entrants whose control really is hand-written code.
     */
    public function createItemDirect(string $title): int
    {
        $db = $this->ctx->dbManager()->getOrDefault('default');

        // Same calls the #[Transactional] interceptor makes (see
        // Azera\Aop\TransactionalInterceptor): begin(true) — savepoint-aware —
        // then commit/rollback. Using a different transaction API here would
        // make the pair measure the API rather than the interception.
        $db->begin(true);

        try {
            $this->writeSentinel($title);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();

            throw $e;
        }

        return self::FEATURE_SENTINEL_ID;
    }

    /**
     * Count items WITHOUT the #[Cache] interceptor — the plain query path.
     *
     * Used by the Db Events demo, which must exercise a real query (and its
     * QueryExecuted / StatementPrepared events) on EVERY request instead of
     * a cache hit: routing it through countItems() would turn most calls into
     * no-op cache reads that fire no Db events.
     */
    public function countItemsQuery(): int
    {
        $row = $this->ctx->dbManager()
            ->getOrDefault('default')
            ->selectRow('SELECT COUNT(*) AS c FROM items', null, \PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Log a method entry and exit, and measure the work between them.
     *
     * DIRECT, not advised. This used to carry `#[Log(level: 'info', logArgs: true)]`
     * and was reached through the generated proxy, so Azera's `/features/log` row
     * paid for a proxy dispatch and an `LogInterceptor` frame that the other six
     * entrants did not — a comparison between different machinery under one label.
     * The row is about logging, so every entrant now writes the records in the
     * method body and reports what its own logger captured; the advice belongs to
     * the `aop-*` features, which have their own rows.
     *
     * `logger` is the PSR-3 service registered in Bootstrap (`MemoryLogger` here),
     * the same interface the interceptors use — so this is still Azera's own
     * logging stack, just called directly.
     */
    public function logSomething(string $message): string
    {
        $method = self::class . '::logSomething';
        $logger = $this->ctx->logger();

        $logger->info("Entering {$method}", ['method' => $method, 'args' => [$message]]);

        $start  = hrtime(true);
        $result = "logged: {$message}";
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        $logger->info("Completed {$method}", [
            'method'     => $method,
            'args'       => [$message],
            'duration_ms' => round($elapsedMs, 4),
        ]);

        return $result;
    }
}
