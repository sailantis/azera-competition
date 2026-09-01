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
     * POST /items — upsert the sentinel item via the ORM Table, JSON id.
     *
     * get-or-new-then-save mirrors azera's Item::upsert() semantics with a
     * fixed sentinel id so row count stays stable across runs.
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
            $entity = $existing;
        }

        $table->save($entity);

        return $this->json(['id' => $sentinel]);
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
     */
    public function createQb(): \Cake\Http\Response
    {
        $now = date('Y-m-d H:i:s');
        Db::connection()
            ->insertQuery('items', [
                'id'         => Benchmark::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ])
            ->epilog('ON CONFLICT (id) DO UPDATE SET title = excluded.title')
            ->rowCountAndClose();

        return $this->json(['id' => Benchmark::SENTINEL_QB_ID]);
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