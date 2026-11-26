<?php

declare(strict_types=1);

/**
 * App-level bindings: PSR-14 event listener registration (via the events
 * config) and the AOP demo service.
 */

namespace App\Spiral\Bootloader;

use App\Spiral\Service\DbEventLog;
use App\Spiral\Service\LogListener;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\FinalizerInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;

final class AppBootloader extends Bootloader
{
    /** PSR-17 factories (laminas-diactoros, already installed). */
    protected const BINDINGS = [
        \Psr\Http\Message\ResponseFactoryInterface::class      => \Laminas\Diactoros\ResponseFactory::class,
        \Psr\Http\Message\UriFactoryInterface::class           => \Laminas\Diactoros\UriFactory::class,
        \Psr\Http\Message\ServerRequestFactoryInterface::class => \Laminas\Diactoros\ServerRequestFactory::class,
        \Psr\Http\Message\StreamFactoryInterface::class        => \Laminas\Diactoros\StreamFactory::class,
        \Psr\Http\Message\UploadedFileFactoryInterface::class  => \Laminas\Diactoros\UploadedFileFactory::class,
    ];

    protected const SINGLETONS = [
        // Shared between the LogEvent listener registration in init() and
        // the controller — otherwise the controller would receive a second,
        // empty instance autowired by the container.
        DbEventLog::class => [self::class, 'initDbEventLog'],

        // Same reason: the /features/log listener is registered once per process
        // on Spiral's ListenerRegistry, while the demo controller is resolved per
        // request.
        LogListener::class => [self::class, 'initLogListener'],
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(Container $container, FinalizerInterface $finalizer): void
    {
        // SQLite pragma parity. Cycle's SQLite driver exposes no `synchronous`
        // option and pdo_sqlite has no PDO attribute for it, so the flag goes
        // through a PRAGMA on the live connection.
        //
        // MUST BE raw PDO, not $database->execute(). Cycle's execute() leaves a
        // statement registered on the driver, and the next transaction then dies
        // with "cannot commit transaction - SQL statements in progress" or "no such
        // savepoint: SVP0" (verified A/B: raw PDO exec is clean, execute() breaks the
        // first transaction in the process). getPDO() is protected, hence reflection
        // — this is bootstrap code that runs once per process, not a hot path.
        //
        // WHY NORMAL: SQLite defaults to synchronous=FULL, which fsyncs the WAL on
        // every commit; when autocheckpoint fires, one commit in a few hundred pays
        // milliseconds instead of microseconds. Every entrant is pinned to NORMAL so
        // the pack measures frameworks, not two storage configurations. See
        // tests/SqlitePragmaParityTest.php.
        $driver = $container->get(\Cycle\Database\DatabaseManager::class)
            ->database('default')
            ->getDriver();

        $pdo = (new \ReflectionMethod($driver, 'getPDO'))->invoke($driver);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        // PSR-14 listener for ItemCreated is registered declaratively in
        // config/events.php (picked up by EventsBootloader's ConfigProcessor);
        // patching the `events` config at runtime is fragile because the
        // config object can already be delivered by the time APP-phase
        // bootloaders run.

        // Attach the DB-activity listener used by /features/db-events:
        // Cycle DBAL writes query/transaction log lines into the driver's
        // PSR-3 channel; Spiral's LogFactory turns them into LogEvents and
        // hands them to all registered listeners (bound by CoreBootloader).
        $dbEventLog = $container->get(DbEventLog::class);
        $container
            ->get(\Spiral\Logger\ListenerRegistryInterface::class)
            ->addListener($dbEventLog);

        // Attach the listener used by /features/log, on the same registry.
        //
        // This is Spiral's native readback: `LogsInterface::getLogger($channel)`
        // returns a PSR-3 logger whose `log()` builds a `Spiral\Logger\Event\LogEvent`
        // and hands it to every listener here. The demo therefore reads back what
        // Spiral emitted, rather than an array the application appends to — which is
        // what it used to do, with no logger in the path at all.
        $logListener = $container->get(LogListener::class);
        $container
            ->get(\Spiral\Logger\ListenerRegistryInterface::class)
            ->addListener($logListener);

        // The log records one entry per query into a container SINGLETON. In a
        // resident worker that grows without bound (measured +~750 B per
        // query-request over 5,000 requests, 2026-09-14), so wipe it after
        // every request — the same FinalizerInterface hook CycleOrmBootloader
        // uses to clean the ORM heap.
        //
        // Resolve the service NOW and close over the instance rather than the
        // container. Spiral runs finalizers from AbstractKernel::__destruct(),
        // which under per-request boot fires when the container is already torn
        // down; calling $container->get() there throws
        // "Typed property Spiral\Core\Container::$container must not be
        // accessed before initialization" (Container::__destruct() has emptied
        // it) and, because it happens during destructor processing, the fatal
        // is unrecoverable and the request 500s. Under RoadRunner the kernel
        // lives for the whole block so the container is intact and the bug
        // never showed (spiral, 2026-09-15).
        $finalizer->addFinalizer(static function () use ($dbEventLog): void {
            $dbEventLog->clear();
        });

        $finalizer->addFinalizer(static function () use ($logListener): void {
            $logListener->clear();
        });
    }

    public static function initDbEventLog(): DbEventLog
    {
        return new DbEventLog();
    }

    public static function initLogListener(): LogListener
    {
        return new LogListener();
    }
}
