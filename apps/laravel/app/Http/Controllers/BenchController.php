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

        \error_log(\sprintf(
            '[phpthunder-debug] POST /items (laravel) pid=%d existed=%s mem=%d',
            \getmypid(),
            $existed ? 'true' : 'false',
            \memory_get_usage(true),
        ));

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
}