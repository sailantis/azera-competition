<?php

declare(strict_types=1);

/**
 * REST API controller — JSON serialization benchmark endpoints.
 */

namespace App\Symfony\Controller;

use App\Symfony\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiController extends AbstractController
{
    private const SENTINEL_API_ID = 999998;

    public function __construct(
        private readonly EntityManagerInterface $em,
    )
    {
    }

    /**
     * GET /api/items — list items as a JSON array.
     */
    public function index(): JsonResponse
    {
        $items = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->json(\array_map(self::serialize(...), $items));
    }

    /**
     * GET /api/items/{id} — fetch a single item as a JSON object.
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->em->find(Item::class, $id);
        if ($item === null) {
            return $this->json(['error' => 'Not Found'], 404);
        }

        return $this->json(self::serialize($item));
    }

    /**
     * POST /api/items — create an item and return its id as JSON.
     *
     * ORM upsert semantics (see BenchController::create).
     */
    public function create(): JsonResponse
    {
        // Ensure every request actually changes the sentinel row, otherwise
        // Doctrine's dirty-checking may skip the UPDATE when the timestamp is
        // unchanged within the same second. A static counter plus microtime
        // guarantees a unique value on every request.
        static $counter = 0;
        $now = \date('Y-m-d H:i:s') . '.' . \microtime(true) . '.' . ++$counter;

        $item = $this->em->find(Item::class, self::SENTINEL_API_ID);
        if ($item === null) {
            $item = new Item('API Item ' . $now, $now);
            $item->id = self::SENTINEL_API_ID;
        } else {
            $item->title      = 'API Item ' . $now;
            $item->created_at = $now;
        }

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(['id' => $item->id]);
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