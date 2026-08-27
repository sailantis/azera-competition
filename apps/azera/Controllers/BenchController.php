<?php
/**
 * Azera benchmark controller.
 *
 * Implements the 4 benchmark endpoints using the Azera router/dispatcher,
 * ClarityEngine templates, and the Item model over SQLite.
 */

namespace App\Controllers;

use App\Models\Item;
use Azera\AppContext;
use Azera\Core\Controller;
use Azera\Db\Query;
use Azera\Http\Response;

class BenchController extends Controller
{
    /**
     * GET / — welcome page via Clarity template (no DB, just routing + middleware + template render).
     */
    public function indexAction(): Response
    {
        $html = $this->view()->render('home.welcome');
        return Response::html($html);
    }

    /**
     * GET /items — list items via model with pagination + render list template.
     *
     * Accepts ?page=N query parameter (defaults to 1).  Shows 20 items
     * per page with prev/next navigation links.
     */
    public function listAction(): Response
    {
        $page     = (int) AppContext::instance()->request()->query('page', 1);
        $pageSize = 20;

        $paginator = Item::query()->paginate($page, $pageSize);
        $items     = $paginator->models();

        $html = $this->view()->render('items.list', [
            'baseUrl'    => '/items',
            'items'      => $items,
            'pagination' => [
                'currentPage'  => $paginator->currentPage(),
                'lastPage'     => $paginator->lastPage(),
                'previousPage' => $paginator->previousPage(),
                'nextPage'     => $paginator->nextPage(),
                'totalItems'   => $paginator->totalItems(),
                'firstItem'    => $paginator->firstItem(),
                'lastItem'     => $paginator->lastItem(),
                'hasPrevious'  => $paginator->hasPrevious(),
                'hasNext'      => $paginator->hasNext(),
            ],
        ]);
        return Response::html($html);
    }

    /**
     * GET /items/{id} — find a single item by id + render single template.
     */
    public function showAction(int $id): Response
    {
        $item = Item::find($id);
        if ($item === null) {
            return Response::text('Not Found', 404);
        }
        $html = $this->view()->render('items.show', [
            'item' => $item,
        ]);
        return Response::html($html);
    }

    /**
     * POST /items — upsert a sentinel item via Model ORM, return id as JSON.
     *
     * Uses Item::upsert() (INSERT ... ON CONFLICT DO UPDATE) with a fixed
     * sentinel ID (999999) so the row count stays stable across benchmark
     * runs.  This exercises the full ORM write path: model instantiation,
     * __performWrite(), saveState(), and the query builder's upsert
     * compilation.
     */
    public function createAction(): Response
    {
        $item = Item::upsert([
            'id'         => 999999,
            'title'      => 'Created Item ' . date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['id' => $item->id]);
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ----------------------------------------------------- */

    /**
     * GET /items-qb — list items via raw Query Builder with pagination.
     *
     * Same as GET /items but bypasses model hydration — fetches rows
     * directly as arrays via the query builder.  Compares ORM hydration
     * overhead vs raw row fetching.
     */
    public function listQbAction(): Response
    {
        $page     = (int) $this->request()->query('page', 1);
        $pageSize = 20;

        // Plain Query::new() with a table name — no model bound, so the
        // Paginator returns plain arrays instead of hydrated models.
        $paginator = Item::query()->paginate($page, $pageSize);
        $items     = $paginator->objects();

        $html = $this->view()->render('items.list', [
            'baseUrl'    => '/items-qb',
            'items'      => $items,
            'pagination' => [
                'currentPage'  => $paginator->currentPage(),
                'lastPage'     => $paginator->lastPage(),
                'previousPage' => $paginator->previousPage(),
                'nextPage'     => $paginator->nextPage(),
                'totalItems'   => $paginator->totalItems(),
                'firstItem'    => $paginator->firstItem(),
                'lastItem'     => $paginator->lastItem(),
                'hasPrevious'  => $paginator->hasPrevious(),
                'hasNext'      => $paginator->hasNext(),
            ],
        ]);
        return Response::html($html);
    }

    /**
     * GET /items-qb/{id} — find a single item by id via raw Query Builder.
     *
     * Same as GET /items/{id} but uses the query builder directly instead
     * of Item::find().  No model instantiation or hydration.
     */
    public function showQbAction(int $id): Response
    {
        // Plain query builder — returns a stdClass, not a model.
        $row = Item::query()->where('id', $id)->select()->fetchObject();
        if ($row === null) {
            return Response::text('Not Found', 404);
        }
        $html = $this->view()->render('items.show', [
            'item' => $row,
        ]);
        return Response::html($html);
    }

    /**
     * POST /items-qb — upsert a sentinel item via raw Query Builder.
     *
     * Same as the PUT /items endpoint — uses the Query Builder directly
     * without model overhead.  POST /items (ORM) vs POST /items-qb (QB)
     * gives a clean ORM-vs-builder write comparison on the same method.
     */
    public function createQbAction(): Response
    {
        $title      = 'Created Item ' . date('Y-m-d H:i:s');
        $created_at = date('Y-m-d H:i:s');

        Item::query()
            ->conflict(['id'])
            ->upsert([
                'id'         => 999997,
                'title'      => $title,
                'created_at' => $created_at,
            ]);

        return Response::json(['id' => 999997]);
    }
}