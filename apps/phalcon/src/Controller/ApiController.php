<?php

declare(strict_types=1);

namespace App\Phalcon\Controller;

use App\Phalcon\Bootstrap;
use App\Phalcon\Model\Item;
use Phalcon\Di\Di;

/**
 * The JSON endpoints, mirroring the other six apps' ApiController.
 *
 * Payload shape is fixed by the benchmark contract: a page of 20 items as
 * `[{"id":…,"title":…,"created_at":…}]`, one item for the `{id}` route, and the
 * written row for POST. Every app returns the same bytes apart from whitespace,
 * so a difference in the numbers is the framework and not the workload.
 */
final class ApiController
{
    private const PAGE_SIZE = Bootstrap::PAGE_SIZE;
    private const SENTINEL_ORM_ID = Bootstrap::SENTINEL_ORM_ID;

    /** @param array<string,string> $query @param array<string,string> $params */
    public function __construct(
        private readonly Di $di,
        private readonly array $query = [],
        private readonly array $params = [],
    ) {}

    /**
     * GET /api/items — a page of items, hydrated through the ORM.
     */
    public function index(): string
    {
        $page = $this->page();

        $items = Item::find([
            'order'  => 'id ASC',
            'limit'  => self::PAGE_SIZE,
            'offset' => ($page - 1) * self::PAGE_SIZE,
        ]);

        $out = [];
        foreach ($items as $item) {
            $out[] = self::row($item);
        }

        return json_encode($out, JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/items-fast — the same payload with hydration removed.
     *
     * Identical SQL and COUNT; rows stay arrays. `/api/items` minus this is the
     * cost of building objects, with the query held constant.
     */
    public function indexFast(): string
    {
        $page = $this->page();
        $db   = $this->di->getShared('db');

        $rows = $db->fetchAll(
            'SELECT id, title, created_at FROM items ORDER BY id ASC LIMIT ' . self::PAGE_SIZE
            . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'         => (int) $row['id'],
                'title'      => (string) $row['title'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return json_encode($out, JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/items/{id} — one item as JSON.
     */
    public function show(): string
    {
        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => (int) ($this->params['id'] ?? 0)],
        ]);

        if ($item === null) {
            return json_encode(['error' => 'Not Found'], JSON_UNESCAPED_SLASHES);
        }

        return json_encode(self::row($item), JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/items — upsert the sentinel row and return it as JSON.
     */
    public function create(): string
    {
        $now = date('Y-m-d H:i:s');

        $item = Item::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => ['id' => self::SENTINEL_ORM_ID],
        ]);

        if ($item === null) {
            $item = new Item();
            $item->id = self::SENTINEL_ORM_ID;
        }

        $item->title      = 'Created Item ' . $now;
        $item->created_at = $now;
        $item->save();

        return json_encode(self::row($item), JSON_UNESCAPED_SLASHES);
    }

    private function page(): int
    {
        return (int) ($this->query['page'] ?? 1);
    }

    /** @return array<string,mixed> */
    private static function row(Item $item): array
    {
        return [
            'id'         => (int) $item->id,
            'title'      => (string) $item->title,
            'created_at' => (string) $item->created_at,
        ];
    }
}
