<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization benchmark endpoints.
 */

namespace App\Spiral\Controller;

use App\Spiral\Entity\Item;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Spiral\Http\ResponseWrapper;

class ApiController
{
    private const SENTINEL_API_ID = 999998;

    public function __construct(
        private readonly ORMInterface $orm,
        private readonly EntityManagerInterface $em,
        private readonly ResponseWrapper $response,
    ) {}

    /**
     * GET /api/items — list items as a JSON array.
     */
    public function index(): Response
    {
        $items = $this->orm->getRepository(Item::class)
            ->select()
            ->orderBy('id', 'ASC')
            ->limit(20)
            ->fetchAll();

        return $this->response->json(
            \array_map(self::serialize(...), $items),
        );
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     *
     * NOTE: route params arrive as strings (strict resolver rejects int).
     */
    public function show(string $id): Response
    {
        $item = $this->orm->getRepository(Item::class)->findByPK((int) $id);
        if ($item === null) {
            return $this->response->json(['error' => 'Not Found'], 404);
        }

        return $this->response->json(self::serialize($item));
    }

    /**
     * POST /api/items — create an item and return its id as JSON.
     *
     * ORM upsert semantics (see BenchController::create).
     */
    public function create(): Response
    {
        $now = \date('Y-m-d H:i:s');
        $repo = $this->orm->getRepository(Item::class);

        $item = $repo->findByPK(self::SENTINEL_API_ID);
        if ($item === null) {
            $item = new Item('API Item ' . $now, $now);
            $item->id = self::SENTINEL_API_ID;
        } else {
            $item->title = 'API Item ' . $now;
            $item->created_at = $now;
        }

        $this->em->persist($item);
        $this->em->run();

        return $this->response->json(['id' => $item->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serialize(Item $item): array
    {
        return [
            'id'         => $item->id,
            'title'      => $item->title,
            'created_at' => $item->created_at,
        ];
    }
}