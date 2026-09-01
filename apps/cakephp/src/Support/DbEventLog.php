<?php

declare(strict_types=1);

/**
 * DB activity log — CakePHP's idiomatic DB observation hook.
 *
 * Cake logs every executed query through the connection driver's logger
 * (Driver::log() → LoggedQuery). We register an ArrayLog engine under the
 * 'cake.database.queries' scope, wrap the connection's driver with it, and
 * expose capped entries in the same shape the other adapters emit.
 */

namespace App\Cake\Support;

use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Database\Log\LoggedQuery;
use Cake\Log\Engine\ArrayLog;
use Psr\Log\LoggerInterface;
use Stringable;

final class DbEventLog extends ArrayLog
{
    private const MAX_ENTRIES = 50;

    /** @var list<array<string, mixed>> */
    private array $entries = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $query = $context['query'] ?? null;

        if ($query instanceof LoggedQuery) {
            $context = $query->getContext();
            $this->entries[] = [
                'event'    => 'query',
                'query'    => (string) $context['query'],
                'duration' => $context['took'],
            ];
            if (count($this->entries) > self::MAX_ENTRIES) {
                array_shift($this->entries);
            }
        }

        // Skip parent (no formatted-string duplication needed).
    }

    /**
     * Capped structured entries.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function clearEntries(): void
    {
        $this->entries = [];
        $this->clear();
    }

    /**
     * Attach this logger to the connection's driver (idempotent).
     */
    public static function attach(Connection $connection): self
    {
        $driver = $connection->getDriver();
        $logger = $driver->getLogger();

        if ($logger instanceof self) {
            return $logger;
        }

        $log = new self();
        $driver->setLogger($log);

        return $log;
    }
}