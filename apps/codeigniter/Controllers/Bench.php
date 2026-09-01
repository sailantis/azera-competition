<?php

declare(strict_types=1);

/**
 * Benchmark controller — core HTML endpoints (routing + ORM/query-builder
 * + template render + write path).  Mirrors the azera BenchController for
 * parity: same pagination (20/page), same item fields, same badges.
 */

namespace Ci4App\Controllers;

use Ci4App\Models\Item;

final class Bench extends BaseController
{
    private const PAGE_SIZE = 20;
    private const SENTINEL_ORM_ID = 999999;
    private const SENTINEL_QB_ID = 999997;

    /**
     * GET / — welcome page via CodeIgniter(View) template (no DB).
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        return view('home', ['title' => 'Welcome'] + $this->viewGlobals());
    }

    /**
     * GET /items — list items via Model with pagination + list template.
     */
    public function list(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $page = max(1, $page);

        $model = new Item();
        $total = $model->countAll();
        $rows  = $model->orderBy('id', 'ASC')
            ->findAll(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return view('items', [
            'baseUrl'    => '/items',
            'title'      => 'Items',
            'items'      => $rows,
            'pagination' => $this->pagination($page, $total),
        ] + $this->viewGlobals());
    }

    /**
     * GET /items/{id} — find a single item by id + single template.
     */
    public function show(?int $id = null): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $item = (new Item())->find($id);
        if ($item === null) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not Found');
        }

        return view('item', ['title' => 'Item ' . $id, 'item' => $item] + $this->viewGlobals());
    }

    /**
     * POST /items — upsert the sentinel item via the ORM / model, render
     * the item detail template with a flash message.
     *
     * find-then-insert-or-update mirrors azera's Item::upsert() semantics
     * with a fixed sentinel id so row count stays stable across runs.
     *
     * Unlike POST /api/items (pure JSON), this renders the item template
     * with an inline flash banner ("created" on INSERT, "updated" after).
     * The find-before-write already distinguishes INSERT vs UPDATE, so no
     * extra probe is needed.
     */
    public function create(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $model = new Item();
        $now   = date('Y-m-d H:i:s');

        $existing = $model->find(self::SENTINEL_ORM_ID);
        if ($existing === null) {
            $model->db->table('items')->insert([
                'id'         => self::SENTINEL_ORM_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ]);
            $existed = false;
        } else {
            $model->db->table('items')
                ->where('id', self::SENTINEL_ORM_ID)
                ->update(['title' => 'Created Item ' . $now]);
            $existed = true;
        }

        return view('item', [
            'title' => 'Item ' . self::SENTINEL_ORM_ID,
            'item'  => (object) [
                'id'         => self::SENTINEL_ORM_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ],
            'flash' => 'Item #' . self::SENTINEL_ORM_ID . ($existed ? ' updated' : ' created') . ' ✓',
        ] + $this->viewGlobals());
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ----------------------------------------------------- */

    /**
     * GET /items-qb — same as list() but via the raw query builder.
     */
    public function listQb(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $page = max(1, $page);

        $builder = $this->db()->table('items');
        $total   = (int) (clone $builder)->countAllResults(false);
        $rows    = $builder->orderBy('id', 'ASC')
            ->get(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->getResult();

        return view('items', [
            'baseUrl'    => '/items-qb',
            'title'      => 'Items',
            'items'      => $rows,
            'pagination' => $this->pagination($page, $total),
        ] + $this->viewGlobals());
    }

    /**
     * GET /items-qb/{id} — raw query builder find.
     */
    public function showQb(?int $id = null): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $row = $this->db()->table('items')->where('id', $id)->get()->getRow();
        if ($row === null) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not Found');
        }

        return view('item', ['title' => 'Item ' . $id, 'item' => $row] + $this->viewGlobals());
    }

    /**
     * POST /items-qb — upsert the QB sentinel row via raw SQL builder,
     * render the item detail template with a flash message.
     *
     * Same render treatment as POST /items: detail template + flash, so the
     * two HTML write endpoints stay symmetric (POST /api/items remains the
     * pure JSON write measurement). The upsert() is a plain write — the
     * EXISTS probe distinguishes INSERT from UPDATE for the flash.
     */
    public function createQb(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $title = 'Created Item ' . date('Y-m-d H:i:s');

        $existed = $this->db()->table('items')
            ->where('id', self::SENTINEL_QB_ID)
            ->countAllResults() > 0;

        $this->db()->table('items')->upsert([
            'id'         => self::SENTINEL_QB_ID,
            'title'      => $title,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return view('item', [
            'title' => 'Item ' . self::SENTINEL_QB_ID,
            'item'  => (object) [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => $title,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            'flash' => 'Item #' . self::SENTINEL_QB_ID . ($existed ? ' updated' : ' created') . ' ✓',
        ] + $this->viewGlobals());
    }

    /**
     * GET /filler/{n} — route-table filler used to keep route counts equal.
     */
    public function filler(?int $n = null): \CodeIgniter\HTTP\ResponseInterface|string
    {
        return 'Filler ' . $n;
    }

    private function db(): object
    {
        return \Config\Database::connect();
    }

    /**
     * @return array<string, int|bool>
     */
    private function pagination(int $page, int $total): array
    {
        $last = max(1, (int) ceil($total / self::PAGE_SIZE));

        return [
            'currentPage'  => $page,
            'lastPage'     => $last,
            'previousPage' => max(1, $page - 1),
            'nextPage'     => min($last, $page + 1),
            'totalItems'   => $total,
            'firstItem'    => ($page - 1) * self::PAGE_SIZE + 1,
            'lastItem'     => min($total, $page * self::PAGE_SIZE),
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $last,
        ];
    }
}