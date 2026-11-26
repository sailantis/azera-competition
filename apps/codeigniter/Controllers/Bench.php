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
     * GET /echo — a constant handler through the whole HTTP path.
     *
     * The framework floor for this app: CI4's Router, its filter pipeline, the
     * controller instantiation and the `BaseController` wiring all run, then the
     * action returns three bytes. The gap between this and GET / is what a CI4
     * view render costs.
     *
     * Returning a bare string lets CI4's own response path turn it into a body,
     * so nothing is short-circuited: this is the cheapest response the framework
     * can produce, which is exactly what a floor should measure.
     */
    public function echo(): string
    {
        return 'bench';
    }

    /**
     * GET /api/routing/{id} — routing cost with nothing after it.
     *
     * `/echo` is static, so a router that indexes literals can answer it without
     * ever walking candidates; the endpoint measures throughput, not routing.
     * This one carries a numeric placeholder, which is the path a compiled index
     * actually changes. The handler is constant on purpose: the id is ignored,
     * so the measured time is matching and dispatch and nothing else.
     */
    public function routing(string $id = ''): string
    {
        return 'routing';
    }

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
     * GET /items-fast — GET /items with the row shape reduced to plain stdClass.
     *
     * Identical to {@see list()} except that the rows are never hydrated: the
     * Model still resolves its table, still builds the query builder, still runs
     * the same SELECT and the same `countAll()`; what changes is only that no
     * entity is constructed per row.
     *
     * `getResultObject()` is CI4's unhydrated read: it walks the result and
     * yields one `stdClass` per row. `list()` goes through the Model instead,
     * whose `$returnType = Ci4App\Entities\Item::class` makes
     * `getCustomResultObject()` construct an entity per row and inject the raw
     * values into it. Hydration is therefore the single variable between the two
     * routes, which is what this control exists to isolate.
     *
     * TWO CORRECTIONS, both measured rather than assumed:
     *
     *  - This route is NOT "PDO::FETCH_OBJ in C". CI4's SQLite3 driver says so
     *    itself (`SQLite3\Result::fetchObject()` — "No native support for
     *    fetching rows as objects") and does `fetchAssoc()` + `(object) $row`.
     *    There is no C-level object fetch on this driver, so an earlier version
     *    of this comment described a mechanism that never ran.
     *  - Before the Model hydrated at all, `list()` and this route both ended in
     *    that same `fetchAssoc() + (object)` call — which is exactly why the
     *    published `/items` vs `/items-fast` gap was 0.4%, indistinguishable from
     *    noise. The route was measuring nothing. With `$returnType` set to the
     *    entity it measures hydration, like the other five apps.
     *
     * WHY NOT `asArray()`: `getResult('array')` reads with `PDO::FETCH_ASSOC`,
     * and `getResultObject()` then loops those arrays casting each with
     * `(object) $row` — building the array AND the object and paying for both.
     *
     * Rows still expose `->id`/`->title`/`->created_at`, so the list template is
     * untouched and the body stays byte-identical to `/items`.
     */
    public function listFast(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $page = max(1, $page);

        // Built via the SAME model so table resolution, the builder and the
        // pagination COUNT are untouched; only the result fetch mode differs.
        // `getResultObject()` uses PDO::FETCH_OBJ, so the rows never exist as
        // arrays.
        $model = new Item();
        $total = $model->countAll();
        $rows  = (new Item())
            ->builder()
            ->orderBy('id', 'ASC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->get()
            ->getResultObject();

        return view('items', [
            'baseUrl'    => '/items-fast',
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
        $now = \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);

        $existing = $model->find(self::SENTINEL_ORM_ID);
        $existed  = $existing !== null;
        if ($existing === null) {
            $model->db->table('items')->insert([
                'id'         => self::SENTINEL_ORM_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ]);
        } else {
            $model->db->table('items')
                ->where('id', self::SENTINEL_ORM_ID)
                ->update(['title' => 'Created Item ' . $now]);
        }

        // Re-read through the Model so the template is handed the same hydrated
        // entity shape `/items` uses, exactly like the other five apps (whose
        // write paths persist and then render the entity they just wrote).
        // Constructing a literal object here instead would make POST the one
        // items endpoint that skips hydration.
        $item = $model->find(self::SENTINEL_ORM_ID);

        return view('item', [
            'title' => 'Item ' . self::SENTINEL_ORM_ID,
            'item'  => $item,
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
        $title = 'Created Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999);

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