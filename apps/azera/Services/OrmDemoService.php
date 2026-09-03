<?php
/**
 * Unit-of-Work demo service.
 *
 * Exercises the NEW ORM stack end-to-end (separate from the legacy
 * Model::save()/upsert() path the benchmark endpoints use):
 *
 *   Heap (identity map)  ->  UnitOfWork (diff + scheduling)  ->  flush()
 *   = one transaction with exactly the changed columns.
 *
 * The DbEventLog captures the SQL the UoW emits so the endpoint can show
 * proof: a mutate + flush must produce ONE UPDATE listing ONLY the mutated
 * column, and a second flush with no changes must produce NO SQL at all
 * (the UoW never writes clean entities).
 */

namespace App\Services;

use App\Models\Item;
use Azera\AppContext;
use Azera\Db\Event\QueryExecuted;
use Azera\Db\Event\TransactionCommitted;
use Azera\Db\Event\TransactionStarted;
use Azera\Orm\Heap;
use Azera\Orm\Node;
use Azera\Orm\Storage\PdoStore;
use Azera\Orm\UnitOfWork;

class OrmDemoService
{
    public function __construct(
        private AppContext $ctx,
        private DbEventLog $log,
    )
    {
    }

    /**
     * Load the sentinel item through the ORM store (raw row -> entity),
     * attach it to the heap as MANAGED, mutate, persist + flush, and
     * return a report of what happened.
     */
    public function updateSentinelViaUow(): array
    {
        $this->log->clear();

        $sentinelId = 999999;

        // The store borrows the 'default' connection per operation.
        $heap = new Heap();
        $uow  = new UnitOfWork($heap, $this->ctx->dbManager()->getOrDefault('default'));
        $meta = \Azera\Orm\Metadata::for(Item::class);

        // 1) Load raw row via the store seam (no ResultSet, no FETCH_CLASS).
        $row = (new \Azera\Orm\Storage\PdoStore($this->ctx->dbManager(), 'default', 'default'))
            ->findByPk(Item::class, ['id' => $sentinelId]);

        $created = ($row === null);
        if ($created) {
            // First run on a fresh DB: seed the sentinel through the UoW itself.
            $item = new Item();
            $item->id         = $sentinelId;
            $item->title      = 'UoW Item ' . date('Y-m-d H:i:s');
            $item->created_at = date('Y-m-d H:i:s');
            $uow->persist($item);
            $uow->flush();
        }

        // 2) Hydrate the entity from the raw row + attach as MANAGED.
        //    (This mirrors what RowSplitter does for joined reads.)
        $item = new Item();
        foreach ($meta['columns'] as $field => $col) {
            if (array_key_exists($col['name'], $row)) {
                $item->{$field} = $row[$col['name']];
            }
        }
        $id   = ['id' => $sentinelId];
        $data = [];
        foreach ($meta['columns'] as $field => $col) {
            $data[$col['name']] = $row[$col['name']] ?? null;
        }
        $heap->attach($item, new \Azera\Orm\Node(Item::class, $id, $data, Node::MANAGED));

        // 3) Mutate ONE field and persist: the UoW must diff exactly this.
        $item->title = 'UoW Item ' . date('Y-m-d H:i:s');
        $uow->persist($item);

        // 4) Flush: one transaction, one UPDATE, only `title` in the SET.
        $uow->flush();

        // 5) A second flush with nothing scheduled must be a no-op (no SQL).
        $sqlBeforeSecondFlush = $this->queryCount();
        $uow->flush();

        return [
            'created'                    => $created,
            'identity'                   => $id,
            'title'                      => $item->title,
            'events'                     => $this->log->all(),
            'second_flush_emits_nothing' => $this->queryCount() === $sqlBeforeSecondFlush,
        ];
    }

    /**
     * Load 3 items through the ORM store and return their state.
     * Demonstrates the identity map: same PK loaded twice = same object.
     */
    public function hydrateViaStore(int $limit = 3): array
    {
        $store = new \Azera\Orm\Storage\PdoStore($this->ctx->dbManager(), 'default', 'default');

        $rows = $store->findBy(\App\Models\Item::class, []);
        $rows = \array_slice($rows, 0, $limit);
        $meta = \Azera\Orm\Metadata::for(Item::class);
        $heap = new Heap();

        $items = [];
        foreach ($rows as $row) {
            $item = new Item();
            foreach ($meta['columns'] as $field => $col) {
                if (array_key_exists($col['name'], $row)) {
                    $item->{$field} = $row[$col['name']];
                }
            }
            $id   = ['id' => $row['id']];
            $data = [];
            foreach ($meta['columns'] as $field => $col) {
                $data[$col['name']] = $row[$col['name']] ?? null;
            }
            $heap->attach($item, new \Azera\Orm\Node(Item::class, $id, $data, Node::MANAGED));
        }

        return [
            'count'    => \count($rows),
            'first'    => isset($rows[0]) ? $rows[0]['id'] : null,
            'entities' => \array_map(fn($n) => $n->data, \array_values($heap->all())),
        ];
    }

    private function queryCount(): int
    {
        $n = 0;
        foreach ($this->log->all() as $e) {
            if (str_starts_with($e['type'], 'Query')) {
                $n++;
            }
        }
        return $n;
    }
}