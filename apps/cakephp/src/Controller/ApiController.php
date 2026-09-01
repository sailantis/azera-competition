<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization endpoints (same shapes as the
 * azera/spiral/CI4 API controllers): id, title, created_at.
 */

namespace App\Cake\Controller;

use App\Cake\Benchmark;
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
     * POST /api/items — upsert the API sentinel item via the ORM Table,
     * JSON with the id.
     *
     * get-or-new-then-save mirrors azera's Item::upsert() semantics with a
     * fixed sentinel id so row count stays stable across runs — the same
     * ORM write path as BenchController::create().  The API category
     * compares routing + JSON serialization, so every framework sits on
     * its model layer here (no raw builder shortcut).
     */
    public function create(): \Cake\Http\Response
    {
        $now      = date('Y-m-d H:i:s');
        $table    = $this->items();
        $sentinel = Benchmark::SENTINEL_API_ID;

        $existing = $table->find()->where(['id' => $sentinel])->first();
        if (!$existing instanceof EntityInterface) {
            $entity = new Entity([
                'id'         => $sentinel,
                'title'      => 'API Item ' . $now,
                'created_at' => $now,
            ]);
            $entity->setNew(true);
            $entity->setSource('Items');
        } else {
            $existing->set('title', 'API Item ' . $now);
            // save() short-circuits when no field is dirty, and set() only
            // marks dirty if the value actually changed. The title embeds a
            // second-precision timestamp, so within one benchmark run almost
            // every POST builds an identical string and would skip the write
            // entirely (measuring a SELECT, not an upsert). Force the dirty
            // flag so every request exercises the real ORM write path, like
            // azera's Item::upsert() and CI4's update().
            $existing->setDirty('title', true);
            $entity = $existing;
        }

        $table->save($entity);

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