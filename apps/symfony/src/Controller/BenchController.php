<?php

declare(strict_types=1);

/**
 * Benchmark controller — core HTML endpoints (ORM + query builder).
 *
 * Mirrors the azera/Spiral/Laravel response shapes exactly: pagination
 * array, item detail template, inline flash on write.
 */

namespace App\Symfony\Controller;

use App\Symfony\Entity\Item;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BenchController extends AbstractController
{
    private const PAGE_SIZE = 20;
    private const SENTINEL_ORM_ID = 999999;
    private const SENTINEL_QB_ID = 999997;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    )
    {
    }

    /**
     * GET / — welcome page via Twig template (no DB).
     */
    public function index(): Response
    {
        return $this->render('home.html.twig', self::viewGlobals());
    }

    /**
     * GET /items — list items via Doctrine ORM with pagination.
     */
    public function list(Request $request): Response
    {
        $page = (int) ($request->query->get('page', 1));

        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->orderBy('i.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $total     = \count($paginator);
        $items     = \iterator_to_array($paginator);

        return $this->render('items.html.twig', \array_merge(self::viewGlobals(), [
            'baseUrl'    => '/items',
            'items'      => $items,
            'pagination' => self::pagination($page, $total),
        ]));
    }

    /**
     * GET /items/{id} — find a single item by id (ORM).
     */
    public function show(int $id): Response
    {
        $item = $this->em->find(Item::class, $id);
        if ($item === null) {
            return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $this->render('item.html.twig', \array_merge(self::viewGlobals(), ['item' => $item]));
    }

    /**
     * POST /items — upsert a sentinel item via the ORM, render the item
     * detail template with a flash message.
     *
     * Fixed sentinel ID keeps the row count stable across benchmark runs.
     * ORM upsert semantics: load the sentinel entity if it exists (→ UPDATE),
     * else build a new one with the fixed PK (→ INSERT). Mirrors azera's
     * Item::upsert().
     */
    public function create(): Response
    {
        $now = \date('Y-m-d H:i:s');

        $item    = $this->em->find(Item::class, self::SENTINEL_ORM_ID);
        $existed = $item !== null;
        if ($item === null) {
            $item = new Item('Created Item ' . $now, $now);
            $item->id = self::SENTINEL_ORM_ID;
        } else {
            $item->title      = 'Created Item ' . $now;
            $item->created_at = $now;
        }

        $this->em->persist($item);
        $this->em->flush();

        return $this->render('item.html.twig', \array_merge(self::viewGlobals(), [
            'item'  => $item,
            'flash' => 'Item #' . $item->id . ($existed ? ' updated' : ' created') . ' ✓',
        ]));
    }

    /* -------------------------------------------------------------
     *  QUERY BUILDER ENDPOINTS (no ORM hydration)
     * ------------------------------------------------------------- */

    /**
     * GET /items-qb — list items via raw Doctrine DBAL query builder.
     */
    public function listQb(Request $request): Response
    {
        $page = (int) ($request->query->get('page', 1));

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM items');
        $rows  = $this->connection->fetchAllAssociative(
            'SELECT * FROM items ORDER BY id ASC LIMIT :limit OFFSET :offset',
            ['limit' => self::PAGE_SIZE, 'offset' => ($page - 1) * self::PAGE_SIZE],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        // Cast plain arrays to stdClass so the view can use $item->id
        // syntax uniformly (same as showQb and the ORM path).
        $items = \array_map(static fn(array $row): object => (object) $row, $rows);

        return $this->render('items.html.twig', \array_merge(self::viewGlobals(), [
            'baseUrl'    => '/items-qb',
            'items'      => $items,
            'pagination' => self::pagination($page, $total),
        ]));
    }

    /**
     * GET /items-qb/{id} — fetch a single row via the query builder.
     */
    public function showQb(int $id): Response
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM items WHERE id = :id',
            ['id' => $id],
            ['id' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        if ($row === false) {
            return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $this->render('item.html.twig', \array_merge(self::viewGlobals(), ['item' => (object) $row]));
    }

    /**
     * POST /items-qb — upsert via the query builder, render the item detail
     * template with a flash message.
     *
     * Same render treatment as POST /items: detail template + flash, so the
     * two HTML write endpoints stay symmetric (POST /api/items remains the
     * pure JSON write measurement). The EXISTS probe distinguishes INSERT
     * from UPDATE for the flash.
     */
    public function createQb(): Response
    {
        $now = \date('Y-m-d H:i:s');

        $existed = (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM items WHERE id = :id',
            ['id' => self::SENTINEL_QB_ID],
            ['id' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        $this->connection->executeStatement(
            'INSERT INTO items (id, title, created_at) VALUES (:id, :title, :created_at)
             ON CONFLICT(id) DO UPDATE SET title = :title, created_at = :created_at',
            [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ],
            ['id' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $this->render('item.html.twig', \array_merge(self::viewGlobals(), [
            'item' => (object) [
                'id'         => self::SENTINEL_QB_ID,
                'title'      => 'Created Item ' . $now,
                'created_at' => $now,
            ],
            'flash' => 'Item #' . self::SENTINEL_QB_ID . ($existed ? ' updated' : ' created') . ' ✓',
        ]));
    }

    /**
     * GET /filler/{n} — filler route handler (route-table parity).
     */
    public function filler(): Response
    {
        return new Response('filler');
    }

    /**
     * @return array<string, int|bool>
     */
    private static function pagination(int $page, int $total): array
    {
        $lastPage = (int) \max(1, \ceil($total / self::PAGE_SIZE));
        $page     = \min(\max(1, $page), $lastPage);
        $first    = $total === 0 ? 0 : ($page - 1) * self::PAGE_SIZE + 1;
        $last     = \min($page * self::PAGE_SIZE, $total);

        return [
            'currentPage'  => $page,
            'lastPage'     => $lastPage,
            'previousPage' => $page > 1 ? $page - 1 : null,
            'nextPage'     => $page < $lastPage ? $page + 1 : null,
            'totalItems'   => $total,
            'firstItem'    => $first,
            'lastItem'     => $last,
            'hasPrevious'  => $page > 1,
            'hasNext'      => $page < $lastPage,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function viewGlobals(): array
    {
        return ['locale' => 'en_US', 'platform' => 'desktop'];
    }
}