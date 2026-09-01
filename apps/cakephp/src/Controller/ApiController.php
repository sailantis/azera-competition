<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization endpoints (same shapes as the
 * azera/spiral/CI4 API controllers): id, title, created_at.
 */

namespace App\Cake\Controller;

use App\Cake\Benchmark;
use App\Cake\Db;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;

final class ApiController extends AppController
{
    /**
     * GET /api/items — list 20 items as a JSON array.
     */
    public function index(): \Cake\Http\Response
    {
        $rows = $this->items()
            ->find()
            ->orderByAsc('id')
            ->limit(20)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->serialize($row);
        }

        return $this->json($out);
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     */
    public function show(?int $id = null): \Cake\Http\Response
    {
        $item = $this->items()->find()->where(['id' => $id])->first();
        if (!$item instanceof EntityInterface) {
            return $this->response
                ->withStatus(404)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Not Found'], JSON_THROW_ON_ERROR));
        }

        return $this->json($this->serialize($item));
    }

    /**
     * POST /api/items — upsert the API sentinel item, JSON with the id.
     */
    public function create(): \Cake\Http\Response
    {
        $now      = date('Y-m-d H:i:s');
        $sentinel = Benchmark::SENTINEL_API_ID;

        Db::connection()->execute(
            'INSERT INTO items (id, title, created_at) VALUES (:id, :title, :created_at)
             ON CONFLICT(id) DO UPDATE SET title = excluded.title',
            ['id' => $sentinel, 'title' => 'API Item ' . $now, 'created_at' => $now],
        );

        return $this->json(['id' => $sentinel]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(EntityInterface $item): array
    {
        return [
            'id'         => $item->id,
            'title'      => $item->title,
            'created_at' => $item->created_at,
        ];
    }

    /**
     * @param list<mixed> $data
     */
    private function json(array $data): \Cake\Http\Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_THROW_ON_ERROR));
    }
}