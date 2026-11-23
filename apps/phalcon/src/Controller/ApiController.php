<?php

declare(strict_types=1);

namespace App\Phalcon\Controller;

use App\Phalcon\Bootstrap;
use App\Phalcon\Model\Item;
use Phalcon\Mvc\Controller;

/**
 * The JSON endpoints, mirroring the other six apps' ApiController.
 *
 * A conventional Phalcon controller — `extends Phalcon\Mvc\Controller`, actions
 * named `...Action()` — so the framework's own dispatcher resolves and invokes
 * them, the same lifecycle every other entrant's front controller runs.
 *
 * Payload shape is fixed by the benchmark contract: a page of 20 items as
 * `[{"id":…,"title":…,"created_at":…}]`, one item for the `{id}` route, and the
 * written row for POST. Every app returns the same bytes apart from whitespace,
 * so a difference in the numbers is the framework and not the workload.
 */
final class ApiController extends Controller
{
    private const PAGE_SIZE = Bootstrap::PAGE_SIZE;
    private const SENTINEL_ORM_ID = Bootstrap::SENTINEL_ORM_ID;

    /**
     * A JSON controller has no templates, and Phalcon's documented way to say so
     * is to disable the view in `initialize()`.
     *
     * With `useImplicitView(true)` the application would otherwise try to render
     * `bench/<action>.volt` for every one of these actions and fail on a template
     * that does not exist. `disable()` is reset per request by
     * {@see Bootstrap::handle()}, because the view service is shared and the
     * BenchController in the same process does need it.
     *
     * Every action still RETURNS its body: `Application::handle()` uses a returned
     * string verbatim, so a disabled view and a returned string are the same thing
     * here — the point of the disable is to keep the render attempt from happening
     * at all.
     */
    public function initialize(): void
    {
        $this->view->disable();
    }

    /**
     * GET /api/items — a page of items, hydrated through the ORM.
     */
    public function indexAction(): string
    {
        $page = $this->page();

        $items = Item::find([
            'order'  => 'id ASC',
            'limit'  => self::PAGE_SIZE,
            'offset' => ($page - 1) * self::PAGE_SIZE,
        ]);

        $out = [];
        foreach ($items as $item) {
            $out[] = self::row($item);
        }

        return json_encode($out, JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/items-fast — the same payload with hydration removed.
     *
     * Identical SQL, LIMIT and OFFSET to /api/items; the ONLY change is that rows come
     * back as `Phalcon\Mvc\Model\Row` values instead of `Item` instances. `/api/items`
     * minus this is the cost of hydrating models, with the query held constant.
     *
     * WHY THE MODEL QUERY AND NOT `$db->fetchAll()`. This used to issue raw SQL straight
     * through the DB adapter, which drops the ORM as well as hydration — two variables
     * changed in a route whose whole purpose is a single-variable control. Every other
     * entrant removes only hydration here (Laravel `toBase()`, Symfony `getArrayResult()`,
     * CakePHP `disableHydration()`, Spiral `fetchData()`, Azera `select()->toArray()`).
     * Measured in rollback-journal mode on the bench VM, the raw-SQL shortcut was worth
     * 3.8x to Phalcon, and it made this row report a framework win that was really a
     * workload difference.
     *
     * `columns()` is what keeps it hydration-free: selecting explicit columns off the
     * builder yields `Model\Row` objects, so `instanceof ModelInterface` is false. This is
     * Phalcon 4's documented behaviour and it is intact in Phalcon 6.
     * See tests/HydrationContractTest.php, which now includes Phalcon.
     */
    public function indexFastAction(): string
    {
        $page = $this->page();

        $rows = Item::query()
            ->columns(['id', 'title', 'created_at'])
            ->orderBy('id ASC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->execute();

        $out = [];
        foreach ($rows as $row) {
            // Model\Row, not a model instance — but it still exposes ->id / ->title, so
            // one serializer keeps this body byte-identical to /api/items.
            $out[] = [
                'id'         => (int) $row->id,
                'title'      => (string) $row->title,
                'created_at' => (string) $row->created_at,
            ];
        }

        return json_encode($out, JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/items/{id} — one item as JSON.
     */
    public function showAction(): string
    {
        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => (int) ($this->dispatcher->getParam('id'))],
        ]);

        if ($item === null) {
            return json_encode(['error' => 'Not Found'], JSON_UNESCAPED_SLASHES);
        }

        return json_encode(self::row($item), JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/items — upsert the sentinel row and return it as JSON.
     */
    public function createAction(): string
    {
        $now = \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);

        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => self::SENTINEL_ORM_ID],
        ]);

        if ($item === null) {
            $item = new Item();
            $item->id = self::SENTINEL_ORM_ID;
        }

        $item->title      = 'Created Item ' . $now;
        $item->created_at = $now;
        $item->save();

        return json_encode(self::row($item), JSON_UNESCAPED_SLASHES);
    }

    private function page(): int
    {
        return (int) ($this->request->getQuery('page') ?? 1);
    }

    /** @return array<string,mixed> */
    private static function row(Item $item): array
    {
        return [
            'id'         => (int) $item->id,
            'title'      => (string) $item->title,
            'created_at' => (string) $item->created_at,
        ];
    }
}
