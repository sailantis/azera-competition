<?php

declare(strict_types=1);

/**
 * Benchmark controller — core HTML endpoints (ORM + query builder).
 *
 * Action names match route action names exactly (Spiral resolves actions
 * via ReflectionMethod, no "Action" suffix).
 */

namespace App\Spiral\Controller;

use App\Spiral\Entity\Item;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Spiral\Http\ResponseWrapper;
use Spiral\Views\ViewsInterface;

class BenchController
{
    private const PAGE_SIZE = 20;
    private const SENTINEL_ORM_ID = 999999;
    private const SENTINEL_QB_ID = 999997;

    public function __construct(
        private readonly ORMInterface $orm,
        private readonly EntityManagerInterface $em,
        private readonly DatabaseInterface $db,
        private readonly ViewsInterface $views,
        private readonly ResponseWrapper $response,
        private readonly Request $request,
    ) {}

    /**
     * GET / — welcome page via Stempler template (no DB).
     */
    public function index(): Response
    {
        return $this->response->html(
            $this->views->render('home', self::viewGlobals()),
        );
    }

    /**
     * GET /items — list items via Cycle ORM with pagination.
     */
    public function list(): Response
    {
        $page = (int) ($this->request->getQueryParams()['page'] ?? 1);

        $select = new Select($this->orm, Item::class);
        $count  = (clone $select)->count();
        $rows   = $select->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->orderBy('id', 'ASC')
            ->fetchAll();

        $html = $this->views->render('items', \array_merge(self::viewGlobals(), [
            'baseUrl'    => '/items',
            'items'      => $rows,
            'pagination' => self::pagination($page, $count),
        ]));

        return $this->response->html($html);
    }

    /**
     * GET /items/{id} — find a single item by id (ORM).
     *
     * NOTE: route params arrive as strings; int-typed params fail Spiral's
     * strict argument resolver (BAD_ARGUMENT → BadRequestException), so the
     * signature takes string and casts.
     */
    public function show(string $id): Response
    {
        $item = $this->orm->getRepository(Item::class)->findByPK((int) $id);
        if ($item === null) {
            return $this->response->html('Not Found', 404, 'text/plain; charset=utf-8');
        }

        return $this->response->html(
            $this->views->render('item', \array_merge(self::viewGlobals(), ['item' => $item])),
        );
    }

    /**
     * POST /items — upsert a sentinel item via the ORM, return id as JSON.
     *
     * Fixed sentinel ID keeps the row count stable across benchmark runs.
     * ORM upsert semantics: load the sentinel entity if it exists (→ state
     * MANAGED, persisted as UPDATE), else build a new one with the fixed PK
     * (→ state NEW, persisted as INSERT). Mirrors azera's Item::upsert().
     */
    public function create(): Response
    {
        $now  = \date('Y-m-d H:i:s');
        $repo = $this->orm->getRepository(Item::class);

        $item = $repo->findByPK(self::SENTINEL_ORM_ID);
        if ($item === null) {
            $item = new Item('Created Item ' . $now, $now);
            $item->id = self::SENTINEL_ORM_ID;
        } else {
            $item->title      = 'Created Item ' . $now;
            $item->created_at = $now;
        }

        $this->em->persist($item);
        $this->em->run();

        return $this->response->json(['id' => $item->id]);
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ------------------------------------------------------------- */

    /**
     * GET /items-qb — list items via raw Cycle query builder.
     */
    public function listQb(): Response
    {
        $page = (int) ($this->request->getQueryParams()['page'] ?? 1);

        $count = (int) $this->db->table('items')->count();
        $rows  = $this->db->select()
            ->from('items')
            ->orderBy('id', 'ASC')
            ->limit(self::PAGE_SIZE)
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->fetchAll();

        // Cast plain arrays to stdClass so the view can use $item->id
        // syntax uniformly (same as showQb and the ORM path).
        $items = \array_map(static fn(array $row): object => (object) $row, $rows);

        $html = $this->views->render('items', \array_merge(self::viewGlobals(), [
            'baseUrl'    => '/items-qb',
            'items'      => $items,
            'pagination' => self::pagination($page, $count),
        ]));

        return $this->response->html($html);
    }

    /**
     * GET /items-qb/{id} — fetch a single row via the query builder.
     */
    public function showQb(string $id): Response
    {
        $rows = $this->db->select()
            ->from('items')
            ->where('id', (int) $id)
            ->fetchAll();
        $row = $rows[0] ?? null;

        if ($row === null) {
            return $this->response->html('Not Found', 404, 'text/plain; charset=utf-8');
        }

        return $this->response->html(
            $this->views->render('item', \array_merge(self::viewGlobals(), ['item' => (object) $row])),
        );
    }

    /**
     * POST /items-qb — upsert via the query builder (INSERT ON CONFLICT).
     */
    public function createQb(): Response
    {
        $this->db->insert('items')
            ->values([
                'id'         => self::SENTINEL_QB_ID,
                'title'      => 'QB Item ' . \date('Y-m-d H:i:s'),
                'created_at' => \date('Y-m-d H:i:s'),
            ])
            ->onConflict('id')
            ->run();

        return $this->response->json(['id' => self::SENTINEL_QB_ID]);
    }

    /**
     * GET /filler/{n} — filler endpoint for route-table size parity.
     */
    public function filler(): Response
    {
        return $this->response->html('filler', 200, 'text/plain; charset=utf-8');
    }

    /**
     * Pagination view data (mirrors azera's paginator shape).
     *
     * @return array<string, int|bool>
     */
    private static function pagination(int $page, int $totalItems): array
    {
        $lastPage = \max(1, (int) \ceil($totalItems / self::PAGE_SIZE));
        $first    = ($page - 1) * self::PAGE_SIZE + 1;
        $last     = \min($totalItems, $page * self::PAGE_SIZE);

        return [
            'currentPage'  => $page,
            'lastPage'     => $lastPage,
            'previousPage' => \max(1, $page - 1),
            'nextPage'     => \min($lastPage, $page + 1),
            'totalItems'   => $totalItems,
            'firstItem'    => $totalItems > 0 ? $first : 0,
            'lastItem'     => $totalItems > 0 ? $last : 0,
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $lastPage,
        ];
    }

    /**
     * View globals — locale and platform variables passed to every template,
     * mirroring azera's RequestContextMiddleware which stamps these into the
     * view engine. Since the benchmark adapter dispatches through the router
     * directly (not Spiral's HTTP middleware pipeline), we pass them as
     * render data instead.
     *
     * @return array<string, string>
     */
    private static function viewGlobals(): array
    {
        return ['locale' => 'en_US', 'platform' => 'desktop'];
    }
}