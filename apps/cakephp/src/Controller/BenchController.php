<?php

declare(strict_types=1);

/**
 * Benchmark controller — core HTML endpoints (routing + ORM/query-builder
 * + template render + write path). Mirrors the azera/CI4 Bench controllers
 * for parity: same pagination (20/page), same item fields, same badges.
 */

namespace App\Cake\Controller;

use App\Cake\Benchmark;
use App\Cake\Db;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;

final class BenchController extends AppController
{
    /**
     * GET /echo — a constant handler through the whole HTTP path.
     *
     * The framework floor for this app: Cake's Router, RoutingMiddleware, the
     * controller factory and controller instantiation all run, then the action
     * returns three bytes. The gap between this and GET / is what a Cake view
     * render costs, which no other endpoint in the set isolates.
     *
     * A plain string body, not `$this->render()`: rendering would add the view
     * layer back and there would be nothing left to compare against.
     */
    public function echo(): \Cake\Http\Response
    {
        return $this->response->withStringBody('bench');
    }

    /**
     * GET /routing/{id} — routing cost with nothing after it.
     *
     * `/echo` is static, so a router that indexes literals can answer it without
     * ever walking candidates; the endpoint measures throughput, not routing.
     * This one carries a typed parameter, which is the path a compiled index
     * actually changes. The handler is constant on purpose: the id is ignored,
     * so the measured time is matching and dispatch and nothing else.
     */
    public function routing(string $id = ''): \Cake\Http\Response
    {
        return $this->response->withStringBody('routing');
    }

    /**
     * GET / — welcome page via Cake View template (no DB).
     */
    public function index(): \Cake\Http\Response
    {
        $this->set('title', 'Welcome');

        return $this->render();
    }

    /**
     * GET /items — list items via Table ORM with pagination + template.
     */
    public function list(): \Cake\Http\Response
    {
        $page = max(1, (int) ($this->request->getQuery('page') ?? '1'));

        $items = $this->items()
            ->find()
            ->orderByAsc('id')
            ->limit(Benchmark::PAGE_SIZE)
            ->offset(($page - 1) * Benchmark::PAGE_SIZE)
            ->all();

        $total = $this->items()->find()->count();

        $this->set('title', 'Items');
        $this->set('items', $items);
        $this->set('baseUrl', '/items');
        $this->set('pagination', $this->pagination($page, $total));

        return $this->render();
    }

    /**
     * GET /items-fast — GET /items with hydration removed, and nothing else.
     *
     * Identical to {@see list()} except that the query runs with
     * `disableHydration()`. The Table, its `find()`, the association loader, the
     * column mapping and the pagination COUNT all still happen; what is skipped is
     * the step that turns each row into an `Entity` — no instantiation, no
     * constructor, no per-column cast, no dirty-tracking registry.
     *
     * That single difference is the point. `/items` vs `/items-qb` changes two
     * things at once (the ORM AND hydration), so it cannot say which one costs
     * what; this route changes exactly one. It is the same control azera's
     * `listFastAction()` provides, so the two frameworks' rows are comparable.
     *
     * Rows come back as arrays, but the list template reads `$item->id`, so they
     * are cast to stdClass — the same treatment `listQb()` gives its raw rows.
     */
    public function listFast(): \Cake\Http\Response
    {
        $page = max(1, (int) ($this->request->getQuery('page') ?? '1'));

        $rows = $this->items()
            ->find()
            ->disableHydration()
            ->orderByAsc('id')
            ->limit(Benchmark::PAGE_SIZE)
            ->offset(($page - 1) * Benchmark::PAGE_SIZE)
            ->all()
            ->toList();

        $total = $this->items()->find()->count();

        $this->set('title', 'Items');
        $this->set('items', $this->qbObjects($rows));
        $this->set('baseUrl', '/items-fast');
        $this->set('pagination', $this->pagination($page, $total));

        // Named explicitly, not left to auto-resolution: the route must serve the
        // SAME page as /items, so it renders the same template rather than a
        // `list_fast.php` copy that could drift.
        return $this->render('list');
    }

    /**
     * GET /items/{id} — find a single item by id + template.
     */
    public function show(?int $id = null): \Cake\Http\Response
    {
        $item = $this->items()->find()->where(['id' => $id])->first();
        if (!$item instanceof EntityInterface) {
            return $this->response->withStatus(404)->withStringBody('Not Found');
        }

        $this->set('title', 'Item ' . $id);
        $this->set('item', $item);

        return $this->render();
    }

    /**
     * POST /items — upsert the sentinel item via the ORM Table, render the
     * item detail template with a flash message.
     *
     * get-or-new-then-save mirrors azera's Item::upsert() semantics with a
     * fixed sentinel id so row count stays stable across runs.
     *
     * Unlike POST /api/items (pure JSON), this renders the show template
     * with an inline flash banner ("created" on INSERT, "updated" after).
     * The find-before-write already distinguishes INSERT vs UPDATE, so no
     * extra probe is needed.
     */
    public function create(): \Cake\Http\Response
    {
        $now      = date('Y-m-d H:i:s');
        $table    = $this->items();
        $sentinel = Benchmark::SENTINEL_ORM_ID;

        $existing = $table->find()->where(['id' => $sentinel])->first();
        if (!$existing instanceof EntityInterface) {
            $entity = new Entity([
                'id'         => $sentinel,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ]);
            $entity->setNew(true);
            $entity->setSource('Items');
            $existed = false;
        } else {
            $existing->set('title', 'Created Item ' . $now);
            // save() short-circuits when no field is dirty, and set() only
            // marks dirty if the value actually changed. The title embeds a
            // second-precision timestamp, so within one benchmark run almost
            // every POST builds an identical string and would skip the write
            // entirely (measuring a SELECT, not an upsert). Force the dirty
            // flag so every request exercises the real ORM write path, like
            // azera's Item::upsert() and CI4's update().
            $existing->setDirty('title', true);
            $entity  = $existing;
            $existed = true;
        }

        $table->save($entity);

        $this->set('title', 'Item ' . $sentinel);
        $this->set('item', $entity);
        $this->set('flash', 'Item #' . $sentinel . ($existed ? ' updated' : ' created') . ' ✓');

        return $this->render('show');
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ----------------------------------------------------- */

    /**
     * GET /items-qb — same as list() but via the raw query builder.
     */
    public function listQb(): \Cake\Http\Response
    {
        $page = max(1, (int) ($this->request->getQuery('page') ?? '1'));

        $rows = Db::connection()
            ->selectQuery(['id', 'title', 'created_at'], 'items')
            ->orderByAsc('id')
            ->limit(Benchmark::PAGE_SIZE)
            ->offset(($page - 1) * Benchmark::PAGE_SIZE)
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);

        $countRow = Db::connection()
            ->selectQuery(['c' => 'COUNT(*)'], 'items')
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

        $total = (int) ($countRow['c'] ?? 0);

        $this->set('title', 'Items');
        $this->set('items', $this->qbObjects($rows));
        $this->set('baseUrl', '/items-qb');
        $this->set('pagination', $this->pagination($page, $total));

        return $this->render();
    }

    /**
     * GET /items-qb/{id} — raw query builder single fetch.
     */
    public function showQb(?int $id = null): \Cake\Http\Response
    {
        $row = Db::connection()
            ->selectQuery(['id', 'title', 'created_at'], 'items')
            ->where(['id' => $id])
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return $this->response->withStatus(404)->withStringBody('Not Found');
        }

        $this->set('title', 'Item ' . $id);
        $this->set('item', $this->toObject($row));

        return $this->render();
    }

    /**
     * POST /items-qb — upsert the QB sentinel row via the database query
     * builder (InsertQuery + ON CONFLICT epilog), mirroring the other
     * frameworks' builder write paths (azera Query::upsert(), CI4 upsert()).
     *
     * Same render treatment as POST /items: detail template + flash, so the
     * two HTML write endpoints stay symmetric (POST /api/items remains the
     * pure JSON write measurement). The EXISTS probe distinguishes INSERT
     * from UPDATE for the flash.
     */
    public function createQb(): \Cake\Http\Response
    {
        $now = date('Y-m-d H:i:s');

        $existing = Db::connection()
            ->selectQuery(['id'], 'items')
            ->where(['id' => Benchmark::SENTINEL_QB_ID])
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);
        $existed = $existing !== false;

        Db::connection()
            ->insertQuery('items', [
                'id'         => Benchmark::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ])
            ->epilog('ON CONFLICT (id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at')
            ->rowCountAndClose();

        $this->set('title', 'Item ' . Benchmark::SENTINEL_QB_ID);
        $this->set('item', $this->toObject([
            'id'         => Benchmark::SENTINEL_QB_ID,
            'title'      => 'Created Item ' . $now,
            'created_at' => $now,
        ]));
        $this->set('flash', 'Item #' . Benchmark::SENTINEL_QB_ID . ($existed ? ' updated' : ' created') . ' ✓');

        return $this->render('show_qb');
    }

    /**
     * GET /filler/{n} — route-table filler used to keep route counts equal.
     */
    public function filler(): \Cake\Http\Response
    {
        return $this->response->withStringBody('Filler ' . $this->request->getParam('n'));
    }

    /* -------------------------------------------------------------
     *  HELPERS
     * ----------------------------------------------------- */

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): \Cake\Http\Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<object>
     */
    private function qbObjects(array $rows): array
    {
        return array_map($this->toObject(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toObject(array $row): object
    {
        return (object) [
            'id'         => $row['id'] ?? null,
            'title'      => $row['title'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function pagination(int $page, int $total): array
    {
        $last = max(1, (int) ceil($total / Benchmark::PAGE_SIZE));

        return [
            'currentPage'  => $page,
            'lastPage'     => $last,
            'previousPage' => max(1, $page - 1),
            'nextPage'     => min($last, $page + 1),
            'totalItems'   => $total,
            'firstItem'    => ($page - 1) * Benchmark::PAGE_SIZE + 1,
            'lastItem'     => min($total, $page * Benchmark::PAGE_SIZE),
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $last,
        ];
    }
}