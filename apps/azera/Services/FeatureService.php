<?php
/**
 * Feature demo service — exercises AOP #[Transactional] and #[Cache].
 *
 * This service is autowired (registered as a class string, not a factory)
 * so AppContext::build() generates an AOP proxy.  The #[Advised] attribute
 * on the class marks it for proxy generation; individual methods carry
 * #[Transactional] or #[Cache] to activate interception.
 */

namespace App\Services;

use Azera\Aop\Advised;
use Azera\Aop\Cache;
use Azera\Aop\Transactional;
use Azera\AppContext;
use Azera\Db\Query;

#[Advised]
class FeatureService
{
    public function __construct(
        private AppContext $ctx,
    ) {}

    /**
     * Create an item inside a transaction.
     *
     * The #[Transactional] interceptor wraps this method in a DB
     * transaction: begin before, commit on success, rollback on
     * exception.  No manual begin/commit/rollback needed.
     */
    #[Transactional]
    public function createItemTransactional(string $title): int
    {
        $db = $this->ctx->dbManager()->getOrDefault('default');
        $db->query(
            'INSERT INTO items (title, created_at) VALUES (?, ?)',
            [$title, date('Y-m-d H:i:s')],
        );
        $id = (int) $db->lastInsertId();

        // Dispatch an event — the listener will run synchronously
        // because we use EventDispatcher (PSR-14).
        $this->ctx->events()->dispatch(new \App\Events\ItemCreated($id, $title));

        return $id;
    }

    /**
     * Count items — result is cached for 10 seconds via #[Cache].
     *
     * The #[Cache] interceptor checks the cache before invoking the
     * method; on a miss it calls the method and stores the result.
     * The key is interpolated from the {segment} argument.
     */
    #[Cache(ttl: 10, key: 'item_count')]
    public function countItems(): int
    {
        // Simulate an expensive query
        usleep(50_000);

        $row = $this->ctx->dbManager()
            ->getOrDefault('default')
            ->selectRow('SELECT COUNT(*) AS c FROM items', null, \PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }
}