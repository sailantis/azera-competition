<?php

declare(strict_types=1);

namespace App\Phalcon\Controller;

use App\Phalcon\Bootstrap;
use App\Phalcon\Model\Item;
use Phalcon\DataMapper\Pdo\Connection as DataMapperConnection;
use Phalcon\DataMapper\Query\QueryFactory;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;

/**
 * The HTML endpoints, mirroring the other six apps' BenchController.
 *
 * A conventional Phalcon controller: `extends Phalcon\Mvc\Controller` (so the
 * dispatcher's no-argument construction works and `$this->request`, `$this->di`
 * and friends resolve through the container) with `...Action()` method names.
 * The dispatcher resolves this class by name and calls the action itself, which
 * is the same lifecycle the other six entrants' front controllers run.
 *
 * Actions return the rendered body as a string rather than writing to a
 * `Phalcon\Http\Response`. `Phalcon\Mvc\Application` treats a returned string as
 * the response body verbatim, so the benchmark adapters — which all return a
 * body and let the transport build the response — see the same lifecycle here as
 * everywhere else.
 */
final class BenchController extends Controller
{
    private const PAGE_SIZE = Bootstrap::PAGE_SIZE;
    private const SENTINEL_ORM_ID = Bootstrap::SENTINEL_ORM_ID;
    private const SENTINEL_QB_ID = Bootstrap::SENTINEL_QB_ID;

    /**
     * GET /echo — a constant handler through the whole HTTP path.
     *
     * The framework floor for this app: routing, dispatch, controller
     * instantiation, then three bytes.
     */
    public function echoAction(): string
    {
        return 'bench';
    }

    /**
     * GET /api/routing/{id} — routing cost with nothing after it.
     *
     * `/echo` is static, so the router can answer it without walking
     * candidates; this one carries a typed parameter and a constant handler, so
     * the measured time is matching and dispatch alone.
     */
    public function routingAction(): string
    {
        return 'routing';
    }

    /**
     * Router not-found target.
     *
     * Registered through `$router->notFound()`, so a path that matches nothing is
     * dispatched here instead of throwing out of the dispatcher with
     * "IndexController handler class cannot be loaded" — the normal Phalcon way to
     * own the 404 page.
     *
     * The body opens with the status line because the benchmark's transports take a
     * body string, not a response object; `Bootstrap::handle()` reads the status the
     * action set and keeps the pair consistent.
     */
    public function notFoundAction(): string
    {
        $this->response->setStatusCode(404, 'Not Found');

        return '404 Not Found';
    }

    /**
     * GET / — welcome page via a Volt template (no DB).
     *
     * The template must keep the same SECTIONS as every other entrant's home page
     * ("What is measured?" + "Stack"). Until 2026-11-24 this page carried only the
     * first two, so `/` rendered 3402 bytes against Azera's 4976 and the row
     * compared a larger document against a smaller one. See
     * tests/HomeTemplateParityTest.php.
     */
    public function indexAction(): void
    {
        $this->view->setVar('title', self::title('index'));
    }

    /**
     * GET /items — list items, hydrated through Phalcon's ORM.
     */
    public function listAction(): void
    {
        $page = $this->page();

        $count = Item::count();
        $items = Item::find([
            'order'  => 'id ASC',
            'limit'  => self::PAGE_SIZE,
            'offset' => ($page - 1) * self::PAGE_SIZE,
        ]);

        $this->view->setVars([
            'title'      => self::title('list'),
            'baseUrl'    => '/items',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items-fast — GET /items with model hydration removed.
     *
     * The single-variable control: identical SQL, same LIMIT/OFFSET, same COUNT — but
     * rows come back as `Phalcon\Mvc\Model\Row` values instead of `Item` instances, so no
     * model object is built and no attribute is assigned. `/items` minus this is the cost
     * of hydration, with the query held constant.
     *
     * WHY THE MODEL QUERY AND NOT `$db->fetchAll()`. This used to issue raw SQL through the
     * DB adapter, which removes the ORM as well as hydration — two variables in a route
     * that exists to vary one. The other six all keep their query layer here: Azera
     * `Item::query()->paginate()->objects()`, Laravel `toBase()`, Symfony
     * `getArrayResult()`, CakePHP `disableHydration()`, Spiral `fetchData()`, CI4
     * `getResultObject()`. Measured on the bench VM this route cost 67 us via raw SQL and
     * 163 us through the model query, so the shortcut was hiding a 2.4x gap.
     *
     * `columns()` is what keeps it hydration-free — it yields `Model\Row`, not `Item`.
     * See tests/HydrationContractTest.php, which now includes Phalcon.
     */
    public function listFastAction(): void
    {
        $page = $this->page();

        $count = Item::count();

        $rows = Item::query()
            ->columns(['id', 'title', 'created_at'])
            ->orderBy('id ASC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->execute();

        // Model\Row values, not Item instances. They expose ->id / ->title / ->created_at,
        // so the shared action view renders them unchanged.
        $items = [];
        foreach ($rows as $row) {
            $items[] = $row;
        }

        $this->view->setVars([
            'title'      => self::title('listFast'),
            'baseUrl'    => '/items-fast',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items/{id} — a single item through the ORM.
     *
     * Returns a string only for the not-found path: a returned string becomes the
     * response body verbatim, which is how a missing row answers without a
     * template. The success path sets view vars and returns nothing, so the view
     * renders `bench/show.volt`.
     */
    public function showAction(): ?string
    {
        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => (int) ($this->dispatcher->getParam('id'))],
        ]);

        // Returning a string short-circuits rendering and becomes the body, which
        // is how a missing row answers 404 without a template. `show.volt` is the
        // view for the success path only.
        if ($item === null) {
            return 'Not Found';
        }

        $this->view->setVars([
            'title' => self::title('show'),
            'item'  => $item,
            'flash' => '',
        ]);

        return null;
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
    public function createAction(): void
    {
        $now = \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);

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

        $this->view->setVars([
            'title' => self::title('create'),
            'item'  => $item,
            'flash' => $existed ? 'updated' : 'created',
        ]);
    }

    /**
     * GET /items-qb — the same page through the query builder, no hydration.
     *
     * The builder is `Phalcon\DataMapper\Query\Select`, Phalcon 6's raw SQL
     * layer: `columns()/from()/orderBy()/limit()/offset()` compose a statement
     * and `perform()` runs it, so this route measures SQL construction with no
     * model in it. That is the "query builder, not ORM" arm the other apps
     * compare against — `Mvc\Model\Query\Builder` is PHQL and refuses a literal
     * table name (`Model 'items' could not be loaded`), so it cannot express
     * this route at all.
     *
     * This used to hand-write the SQL and run it through the Db adapter, which
     * is "raw SQL", not a builder: the string was already assembled before the
     * framework saw it, so the row measured string concatenation rather than
     * what the other entrants' builders do.
     */
    public function listQbAction(): void
    {
        $page = $this->page();

        $factory = $this->queryFactory();
        $db      = $this->builderConnection();

        $count = (int) $factory->newSelect($db)
            ->columns(['COUNT(*) AS c'])
            ->from('items')
            ->perform()
            ->fetchColumn(0);

        $rows = $factory->newSelect($db)
            ->columns(['id', 'title', 'created_at'])
            ->from('items')
            ->orderBy('id ASC')
            ->limit(self::PAGE_SIZE)
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->perform()
            ->fetchAll(\PDO::FETCH_OBJ);

        // Plain objects, the same terminal the `-fast` route uses: the query
        // builder arm is the "no model, no hydration" comparison.
        $this->view->setVars([
            'title'      => self::title('listQb'),
            'baseUrl'    => '/items-qb',
            'items'      => $rows,
            'pagination' => self::pagination($page, $count),
        ]);
    }

    /**
     * GET /items-qb/{id} — a single item through the query builder.
     */
    public function showQbAction(): ?string
    {
        $row = $this->queryFactory()
            ->newSelect($this->builderConnection())
            ->columns(['id', 'title', 'created_at'])
            ->from('items')
            ->where('id = :id')
            ->bindValue('id', (int) ($this->dispatcher->getParam('id')))
            ->perform()
            ->fetch(\PDO::FETCH_OBJ);

        if ($row === false) {
            return 'Not Found';
        }

        $this->view->setVars([
            'title' => self::title('showQb'),
            'item'  => $row,
            'flash' => '',
        ]);

        return null;
    }

    /**
     * POST /items-qb — upsert the query-builder sentinel, no ORM involved.
     *
     * Load-then-save through the builder: the SELECT decides INSERT vs UPDATE,
     * so the row count stays stable and the flash names what actually happened.
     */
    public function createQbAction(): void
    {
        $now     = \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        $factory = $this->queryFactory();
        $db      = $this->builderConnection();

        $existed = $factory->newSelect($db)
            ->columns(['id'])
            ->from('items')
            ->where('id = :id')
            ->bindValue('id', self::SENTINEL_QB_ID)
            ->perform()
            ->fetch(\PDO::FETCH_OBJ) !== false;

        if ($existed) {
            $factory->newUpdate($db)
                ->from('items')
                ->columns(['title' => 'Updated QB Item ' . $now, 'created_at' => $now])
                ->where('id = :id')
                ->bindValue('id', self::SENTINEL_QB_ID)
                ->perform();
        } else {
            $factory->newInsert($db)
                ->into('items')
                ->columns([
                    'id'         => self::SENTINEL_QB_ID,
                    'title'      => 'Created QB Item ' . $now,
                    'created_at' => $now,
                ])
                ->perform();
        }

        $this->view->setVars([
            'title' => self::title('createQb'),
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
    public function fillerAction(): string
    {
        return 'filler';
    }

    // --- helpers -------------------------------------------------------------

    /**
     * The `?page=N` argument, read the way a Phalcon controller reads it.
     *
     * `Phalcon\Http\Request::getQuery()` reads `$_GET`, which `Bootstrap::handle()`
     * publishes per request from the URI it was handed.
     */
    private function page(): int
    {
        return (int) ($this->request->getQuery('page') ?? 1);
    }

    private function builderConnection(): DataMapperConnection
    {
        return $this->getDI()->getShared('dbBuilder');
    }

    private function queryFactory(): QueryFactory
    {
        return $this->getDI()->getShared('dbQueryFactory');
    }

    /**
     * Page title for an action.
     *
     * Keyed by the DISPATCHER's action name — `index`, `list`, `listFast`, … — because
     * that is both what selects `bench/<action>.volt` and what the old
     * template-keyed version had to be kept in sync with by hand. `list` and
     * `listFast` share "Items", and `show`/`showQb` share "Item", so the rendered
     * pages stay byte-comparable with the other six apps.
     */
    private static function title(string $action): string
    {
        return match ($action) {
            'index'                      => 'Welcome',
            'list', 'listFast', 'listQb' => 'Items',
            'show', 'showQb'             => 'Item',
            default                      => ucfirst($action),
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
