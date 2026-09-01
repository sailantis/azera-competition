<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization endpoints (same shapes as the
 * azera/spiral API controllers): id, title, created_at.
 */

namespace Ci4App\Controllers;

use Ci4App\Models\Item;

final class Api extends BaseController
{
    private const SENTINEL_API_ID = 999998;

    /**
     * GET /api/items — list 20 items as a JSON array.
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $rows = (new Item())
            ->orderBy('id', 'ASC')
            ->findAll(20);

        return $this->response->setJSON($this->serializeAll($rows));
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     */
    public function show(?int $id = null): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $item = (new Item())->find($id);
        if ($item === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Not Found']);
        }

        return $this->response->setJSON($this->serialize($item));
    }

    /**
     * POST /api/items — upsert the API sentinel item via the Model (Active
     * Record), JSON with the id.
     *
     * find-then-insert-or-update mirrors azera's Item::upsert() semantics
     * with a fixed sentinel id so row count stays stable across runs — the
     * same model-layer write path as Bench::create().  The API category
     * compares routing + JSON serialization, so every framework sits on
     * its model layer here (no raw builder shortcut).
     */
    public function create(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $now   = date('Y-m-d H:i:s');
        $model = new Item();

        $existing = $model->find(self::SENTINEL_API_ID);
        if ($existing === null) {
            $model->db->table('items')->insert([
                'id'         => self::SENTINEL_API_ID,
                'title'      => 'API Item ' . $now,
                'created_at' => $now,
            ]);
        } else {
            $model->db->table('items')
                ->where('id', self::SENTINEL_API_ID)
                ->update(['title' => 'API Item ' . $now]);
        }

        return $this->response->setJSON(['id' => self::SENTINEL_API_ID]);
    }

    private function db(): object
    {
        return \Config\Database::connect();
    }

    /**
     * @param list<object> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function serializeAll(array $rows): array
    {
        return array_map($this->serialize(...), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(object $item): array
    {
        return [
            'id'         => $item->id,
            'title'      => $item->title,
            'created_at' => $item->created_at,
        ];
    }
}