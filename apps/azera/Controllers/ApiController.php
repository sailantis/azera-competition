<?php
/**
 * Azera REST API controller.
 *
 * Implements a minimal JSON REST API over the shared `items` table.
 * These endpoints are the "REST API" competition category — every
 * framework implements the same routes so the JSON serialization +
 * routing + controller overhead is compared apples-to-apples.
 *
 * Endpoints:
 *   GET    /api/items       — list items (JSON array, hydrated)
 *   GET    /api/items-fast  — list items (JSON array, not hydrated)
 *   GET    /api/items/{id}  — fetch a single item (JSON object, hydrated)
 *   POST   /api/items       — create an item (JSON with new id, hydrated)
 */

namespace App\Azera\Controllers;

use App\Azera\Models\Item;
use Azera\Core\Controller;
use Azera\Http\Response;

class ApiController extends Controller
{
    /**
     * GET /api/items — list items as a JSON array (hydrated entities).
     *
     * Uses `entities()`, so each row is a heap-tracked `Item` model instance —
     * the same hydration cost `/items` pays, which is what makes the two list
     * endpoints comparable. {@see indexFastAction()} is the unhydrated
     * counterpart.
     *
     * This used to call `select()->toArray()`, whose `fetchAllAssoc()` is
     * `PDO::FETCH_ASSOC` — i.e. it handed the serializer associative arrays and
     * never hydrated at all. Measured in-process at ~132 us against ~308 us for
     * the hydrated path, so switching makes this endpoint slower by design: the
     * benchmark compares frameworks, and every other framework hydrates here.
     */
    public function indexAction(): Response
    {
        $items = Item::query()->orderBy('id', 'asc')->limit(20)->entities();

        return Response::json(array_map($this->serialize(...), $items));
    }

    /**
     * GET /api/items-fast — the same JSON as {@see indexAction()}, without
     * hydration.
     *
     * Identical in every other respect: same model, same `source()` resolution,
     * same builder, same SELECT, same serializer. Only the terminal differs —
     * `select()->toArray()` fetches with `PDO::FETCH_ASSOC`, so no entity is
     * instantiated, no identity-map entry is created and no load-time snapshot
     * is taken.
     *
     * Verified byte-identical JSON against {@see indexAction()}, so the
     * difference between the two routes is hydration and only hydration.
     */
    public function indexFastAction(): Response
    {
        $items = Item::query()->orderBy('id', 'asc')->limit(20)->select();

        return Response::json(array_map($this->serialize(...), $items->toArray()));
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     */
    public function showAction(int $id): Response
    {
        $item = Item::find($id);
        if ($item === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }
        return Response::json($this->serialize($item));
    }

    /**
     * POST /api/items — create an item and return its id as JSON.
     */
    public function createAction(): Response
    {
        $item = Item::upsert([
            'id'         => 999998,
            'title'      => 'API Item ' . date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['id' => $item->id]);
    }

    /**
     * Serialize one row to a plain array for JSON output.
     *
     * Accepts either shape the two list routes produce -- a hydrated `Item` from
     * `indexAction()` or an associative row from `indexFastAction()`. Both carry
     * the same three values, so one serializer keeps their output byte-identical,
     * which is what makes the pair a single-variable comparison.
     */
    private function serialize(Item|array $item): array
    {
        $row = \is_array($item) ? $item : [
            'id'         => $item->id,
            'title'      => $item->title,
            'created_at' => $item->created_at,
        ];

        return [
            'id'         => $row['id'],
            'title'      => $row['title'],
            'created_at' => $row['created_at'],
        ];
    }
}
