<?php

declare(strict_types=1);

namespace App\Phalcon\Controller;

use App\Phalcon\Bootstrap;
use App\Phalcon\Model\Item;
use Phalcon\Di\Di;
use Phalcon\Mvc\View\Simple as SimpleView;

/**
 * The HTML endpoints, mirroring the other six apps' BenchController.
 *
 * Actions return the rendered body as a string rather than a Phalcon Response:
 * the benchmark adapters all return a body and let the transport build the
 * response, so a controller that wrote to an Http\Response would measure a
 * different lifecycle here than everywhere else.
 */
final class BenchController
{
    private const PAGE_SIZE = Bootstrap::PAGE_SIZE;
    private const SENTINEL_ORM_ID = Bootstrap::SENTINEL_ORM_ID;
    private const SENTINEL_QB_ID = Bootstrap::SENTINEL_QB_ID;

    /** @param array<string,string> $query @param array<string,string> $params */
    public function __construct(
        private readonly Di $di,
        private readonly array $query = [],
        private readonly array $params = [],
    ) {}

    /**
     * GET /echo — a constant handler through the whole HTTP path.
     *
     * The framework floor for this app: routing, dispatch, controller
     * instantiation, then three bytes.
     */
    public function echo(): string
    {
        return 'bench';
    }

    /**
     * GET /routing/{id} — routing cost with nothing after it.
     *
     * `/echo` is static, so the router can answer it without walking
     * candidates; this one carries a typed parameter and a constant handler, so
     * the measured time is matching and dispatch alone.
     */
    public function routing(): string
    {
        return 'routing';
    }

    /**
     * GET / — welcome page via a Volt template (no DB).
     */
    public function index(): string
    {
        return $this->render('home', []);
    }

    /**
     * GET /items — list items, hydrated through Phalcon's ORM.
     */
    public function list(): string
    {
        $page = $this->page();

        $count = Item::count();
        $items = Item::find([
            'order'  => 'id ASC',
            'limit'  => self::PAGE_SIZE,
            'offset' => ($page - 1) * self::PAGE_SIZE,
        ]);

        return $this->render('items', [
            'baseUrl'    => '/items',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items-fast — GET /items with model hydration removed.
     *
     * The single-variable control: identical SQL, same LIMIT/OFFSET, same
     * COUNT — but rows come back as arrays instead of `Item` instances, so no
     * object is instantiated and no attribute is assigned. `/items` minus this
     * is the cost of hydration, with the query held constant.
     */
    public function listFast(): string
    {
        $page = $this->page();

        $db = $this->di->getShared('db');

        $count = (int) $db->fetchOne('SELECT COUNT(*) AS c FROM items')['c'];

        $rows = $db->fetchAll(
            'SELECT id, title, created_at FROM items ORDER BY id ASC LIMIT ' . self::PAGE_SIZE
            . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );

        $items = array_map(static fn(array $r): Item => Item::fromRow($r), $rows);

        return $this->render('items', [
            'baseUrl'    => '/items-fast',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items/{id} — a single item through the ORM.
     */
    public function show(): string
    {
        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => (int) ($this->params['id'] ?? 0)],
        ]);

        if ($item === null) {
            return 'Not Found';
        }

        return $this->render('item', ['item' => $item, 'flash' => '']);
    }

    /**
     * POST /items — upsert the ORM sentinel and render the detail template with
     * a flash banner.
     *
     * Fixed id keeps the row count stable across a run, the same upsert
     * contract every other app implements: load-then-save means an existing row
     * is an UPDATE and a missing one an INSERT, so the find result is also what
     * labels the flash.
     */
    public function create(): string
    {
        $now = date('Y-m-d H:i:s');

        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => self::SENTINEL_ORM_ID],
        ]);

        $existed = $item !== null;

        if ($item === null) {
            $item = new Item();
            $item->id = self::SENTINEL_ORM_ID;
        }

        $item->title      = 'Created Item ' . $now;
        $item->created_at = $now;
        $item->save();

        return $this->render('item', [
            'item'  => $item,
            'flash' => $existed ? 'updated' : 'created',
        ]);
    }

    /**
     * GET /items-qb — the same page through the query builder, no hydration.
     *
     * Phalcon's Model\Query\Builder yields raw rows when `getQuery()->execute()`
     * runs without a model context, which is exactly the "query builder, not
     * ORM" arm the other apps compare against.
     */
    public function listQb(): string
    {
        $page = $this->page();

        $db = $this->di->getShared('db');

        $count = (int) $db->fetchOne('SELECT COUNT(*) AS c FROM items')['c'];

        $rows = $db->fetchAll(
            'SELECT id, title, created_at FROM items ORDER BY id ASC LIMIT ' . self::PAGE_SIZE
            . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );

        $items = array_map(static fn(array $r): Item => Item::fromRow($r), $rows);

        return $this->render('items', [
            'baseUrl'    => '/items-qb',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items-qb/{id} — a single item through the query layer.
     */
    public function showQb(): string
    {
        $db = $this->di->getShared('db');

        $row = $db->fetchOne(
            'SELECT id, title, created_at FROM items WHERE id = ' . (int) ($this->params['id'] ?? 0)
        );

        if ($row === false || $row === null) {
            return 'Not Found';
        }

        return $this->render('item', ['item' => Item::fromRow($row), 'flash' => '']);
    }

    /**
     * POST /items-qb — upsert the query-builder sentinel, no ORM involved.
     */
    public function createQb(): string
    {
        $now = date('Y-m-d H:i:s');
        $db  = $this->di->getShared('db');

        $row = $db->fetchOne('SELECT id FROM items WHERE id = ' . self::SENTINEL_QB_ID);
        $existed = $row !== false && $row !== null;

        if ($existed) {
            $db->execute(
                'UPDATE items SET title = ?, created_at = ? WHERE id = ?',
                ['Updated QB Item ' . $now, $now, self::SENTINEL_QB_ID]
            );
        } else {
            $db->execute(
                'INSERT INTO items (id, title, created_at) VALUES (?, ?, ?)',
                [self::SENTINEL_QB_ID, 'Created QB Item ' . $now, $now]
            );
        }

        return $this->render('item', [
            'item'  => (object) [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => ($existed ? 'Updated QB Item ' : 'Created QB Item ') . $now,
                'created_at' => $now,
            ],
            'flash' => $existed ? 'updated' : 'created',
        ]);
    }

    /**
     * Filler-route body, never dispatched by the benchmark.
     */
    public function filler(): string
    {
        return 'filler';
    }

    // --- helpers -------------------------------------------------------------

    private function page(): int
    {
        return (int) ($this->query['page'] ?? 1);
    }

    /** @param array<string,mixed> $vars */
    private function render(string $template, array $vars): string
    {
        /** @var SimpleView $view */
        $view = $this->di->getShared('view');

        $vars += ['title' => self::title($template)];

        // The layout is rendered as a SECOND template around the page rather
        // than through Mvc\View's layout machinery: that class fails on this
        // engine combination, and composing two SimpleView renders is both
        // simpler and closer to what the other apps do (Twig/Blade/Stempler all
        // render a page into a shell).
        $content = $view->render($template, $vars);

        return $view->render('layout', $vars + ['content' => $content]);
    }

    private static function title(string $template): string
    {
        return match ($template) {
            'home'  => 'Welcome',
            'items' => 'Items',
            'item'  => 'Item',
            default => ucfirst($template),
        };
    }

    /**
     * Pagination block shared with the templates — same shape every other app
     * hands its view, so the rendered page is comparable line for line.
     *
     * @return array<string,int|bool>
     */
    private static function pagination(int $page, int $totalItems): array
    {
        $lastPage = max(1, (int) ceil($totalItems / self::PAGE_SIZE));
        $first    = ($page - 1) * self::PAGE_SIZE + 1;
        $last     = min($totalItems, $page * self::PAGE_SIZE);

        return [
            'currentPage'  => $page,
            'lastPage'     => $lastPage,
            'previousPage' => max(1, $page - 1),
            'nextPage'     => min($lastPage, $page + 1),
            'totalItems'   => $totalItems,
            'firstItem'    => $totalItems > 0 ? $first : 0,
            'lastItem'     => $totalItems > 0 ? $last : 0,
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $lastPage,
        ];
    }
}
