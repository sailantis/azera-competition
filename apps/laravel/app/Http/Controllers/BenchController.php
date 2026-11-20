<?php

declare(strict_types=1);

/**
 * Benchmark controller — core HTML endpoints (ORM + query builder).
 *
 * Mirrors the azera/Spiral/Symfony response shapes exactly: pagination
 * array, item detail template, inline flash on write.
 */

namespace App\Laravel\Http\Controllers;

use App\Laravel\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BenchController
{
    private const PAGE_SIZE = 20;
    private const SENTINEL_ORM_ID = 999999;
    private const SENTINEL_QB_ID = 999997;

    /**
     * GET /echo — a constant handler through the whole HTTP path.
     *
     * The framework floor for this app: Laravel's router, its global middleware,
     * route-model binding resolution and controller instantiation all run, then
     * the action returns three bytes. The gap between this and GET / is what a
     * Blade render costs.
     */
    public function echo(): Response
    {
        return response()->make('bench', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * GET /routing/{id} — routing cost with nothing after it.
     *
     * `/echo` is static, so a router that indexes literals can answer it without
     * ever walking candidates; the endpoint measures throughput, not routing.
     * This one carries a numeric constraint, which is the path a compiled index
     * actually changes. The handler is constant on purpose: the id is ignored,
     * so the measured time is matching and dispatch and nothing else.
     */
    public function routing(int $id): Response
    {
        return response()->make('routing', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * GET / — welcome page via Blade template (no DB).
     */
    public function index(): View
    {
        return view('home', []);
    }

    /**
     * GET /items — list items via Eloquent with pagination.
     */
    public function list(Request $request): View
    {
        $page = \max(1, (int) $request->query('page', '1'));

        $total = Item::query()->count();
        $items = Item::query()
            ->orderBy('id')
            ->skip(($page - 1) * self::PAGE_SIZE)
            ->take(self::PAGE_SIZE)
            ->get();

        return view('items', [
            'baseUrl'    => '/items',
            'items'      => $items,
            'pagination' => self::pagination($page, $total),
        ]);
    }

    /**
     * GET /items-fast — GET /items with Eloquent hydration removed.
     *
     * Identical to {@see list()} except for `toBase()`, which returns the rows the
     * query builder produced instead of turning each one into a model instance.
     * The Eloquent builder still resolves the model's table and connection, still
     * applies the ordering and the LIMIT/OFFSET, and the pagination COUNT still
     * runs through `Item::query()`; what is skipped is hydration — no model
     * instantiation, no `$attributes` array per row, no cast/date/event setup, no
     * `$exists`/`$original` bookkeeping.
     *
     * That is the same single-variable control azera's `listFastAction()` provides:
     * `/items` vs `/items-qb` changes the data-access layer AND hydration at once,
     * this changes only the second. The list template reads `$item->id`, which a
     * stdClass row satisfies exactly as a model does.
     */
    public function listFast(Request $request): View
    {
        $page = \max(1, (int) $request->query('page', '1'));

        $total = Item::query()->count();
        $items = Item::query()
            ->orderBy('id')
            ->skip(($page - 1) * self::PAGE_SIZE)
            ->take(self::PAGE_SIZE)
            ->toBase()
            ->get();

        return view('items', [
            'baseUrl'    => '/items-fast',
            'items'      => $items,
            'pagination' => self::pagination($page, $total),
        ]);
    }

    /**
     * GET /items/{id} — find a single item by id (Eloquent).
     *
     * NOTE: route params arrive as strings; the signature takes string and
     * casts (declare(strict_types=1) would reject implicit string→int).
     */
    public function show(string $id): Response|View
    {
        $item = Item::find((int) $id);
        if ($item === null) {
            return response()->make('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return view('item', ['item' => $item]);
    }

    /**
     * POST /items — upsert a sentinel item via Eloquent, render the item
     * detail template with a flash message.
     *
     * Fixed sentinel ID keeps the row count stable across benchmark runs.
     * ORM upsert semantics: load the sentinel if it exists (→ UPDATE),
     * else create a new model with the fixed PK (→ INSERT). Mirrors
     * azera's Item::upsert().
     */
    public function create(): View
    {
        $now = \date('Y-m-d H:i:s');

        $item    = Item::find(self::SENTINEL_ORM_ID);
        $existed = $item !== null;
        if ($item === null) {
            $item = new Item();
            $item->id = self::SENTINEL_ORM_ID;
        }
        $item->title      = 'Created Item ' . $now;
        $item->created_at = $now;
        $item->save();

        return view('item', [
            'item'  => $item,
            'flash' => 'Item #' . $item->id . ($existed ? ' updated' : ' created') . ' ✓',
        ]);
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ------------------------------------------------------------- */

    /**
     * GET /items-qb — list items via the query builder (no hydration).
     */
    public function listQb(Request $request): View
    {
        $page = \max(1, (int) $request->query('page', '1'));

        $total = (int) DB::table('items')->count();
        $rows  = DB::table('items')
            ->orderBy('id')
            ->skip(($page - 1) * self::PAGE_SIZE)
            ->take(self::PAGE_SIZE)
            ->get();

        return view('items', [
            'baseUrl'    => '/items-qb',
            'items'      => $rows,
            'pagination' => self::pagination($page, $total),
        ]);
    }

    /**
     * GET /items-qb/{id} — fetch a single row via the query builder.
     */
    public function showQb(string $id): Response|View
    {
        $row = DB::table('items')->where('id', (int) $id)->first();
        if ($row === null) {
            return response()->make('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return view('item', ['item' => $row]);
    }

    /**
     * POST /items-qb — upsert via the query builder, render the item detail
     * template with a flash message.
     *
     * Same render treatment as POST /items: detail template + flash, so the
     * two HTML write endpoints stay symmetric (POST /api/items remains the
     * pure JSON write measurement). The EXISTS probe distinguishes INSERT
     * from UPDATE for the flash.
     */
    public function createQb(): View
    {
        $now = \date('Y-m-d H:i:s');

        $existed = DB::table('items')->where('id', self::SENTINEL_QB_ID)->exists();

        DB::table('items')->upsert(
            [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ],
            ['id'],
            ['title', 'created_at'],
        );

        return view('item', [
            'item' => (object) [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ],
            'flash' => 'Item #' . self::SENTINEL_QB_ID . ($existed ? ' updated' : ' created') . ' ✓',
        ]);
    }

    /**
     * Pagination view data (mirrors azera's paginator shape).
     *
     * @return array<string, int|bool|null>
     */
    private static function pagination(int $page, int $totalItems): array
    {
        $lastPage = (int) \max(1, \ceil($totalItems / self::PAGE_SIZE));
        $page     = \min(\max(1, $page), $lastPage);
        $first    = $totalItems === 0 ? 0 : ($page - 1) * self::PAGE_SIZE + 1;
        $last     = \min($page * self::PAGE_SIZE, $totalItems);

        return [
            'currentPage'  => $page,
            'lastPage'     => $lastPage,
            'previousPage' => $page > 1 ? $page - 1 : null,
            'nextPage'     => $page < $lastPage ? $page + 1 : null,
            'totalItems'   => $totalItems,
            'firstItem'    => $first,
            'lastItem'     => $last,
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $lastPage,
        ];
    }

    /**
     * Body of the 100 filler routes registered by routes/web.php.
     *
     * A CONTROLLER method rather than the `fn () => 'filler'` closure it used to be,
     * because a Closure is the one thing that keeps `route:cache` from being a plain
     * deploy step, and every other app in this suite already points its fillers at a
     * method (Symfony's BenchController::filler, Spiral's). The filler routes are never
     * dispatched by the benchmark: they exist so the route TABLE is the same size
     * everywhere, and a route that is never dispatched still has to be loadable from a
     * compiled table for the comparison to describe the same work.
     */
    public function filler(): string
    {
        return 'filler';
    }
}