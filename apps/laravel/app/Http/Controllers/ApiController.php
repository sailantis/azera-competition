<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization benchmark endpoints.
 *
 * Mirrors Spiral's ApiController response shapes exactly: JSON array list,
 * single-object detail (404 error object on miss), id-only create response.
 */

namespace App\Laravel\Http\Controllers;

use App\Laravel\Models\Item;
use Illuminate\Http\JsonResponse;

class ApiController
{
    private const SENTINEL_API_ID = 999998;

    /**
     * GET /api/items — list items as a JSON array (ORM hydration).
     */
    public function index(): JsonResponse
    {
        $items = Item::query()
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     *
     * NOTE: route params arrive as strings; the signature takes string and
     * casts (declare(strict_types=1) would reject implicit string→int).
     */
    public function show(string $id): JsonResponse
    {
        $item = Item::find((int) $id);
        if ($item === null) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        return response()->json($item);
    }

    /**
     * POST /api/items — create an item and return its id as JSON.
     *
     * ORM upsert semantics (see BenchController::create): load the sentinel
     * if it returned true (→ UPDATE), else create with the fixed PK (→ INSERT).
     */
    public function create(): JsonResponse
    {
        $now = \date('Y-m-d H:i:s');

        $item = Item::find(self::SENTINEL_API_ID);
        if ($item === null) {
            $item = new Item();
            $item->id = self::SENTINEL_API_ID;
        }
        $item->title      = 'API Item ' . $now;
        $item->created_at = $now;
        $item->save();

        return response()->json(['id' => $item->id]);
    }
}