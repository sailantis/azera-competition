<?php

declare(strict_types=1);

/**
 * App-level bindings: PSR-14 event listener registration (via the events
 * config) and the AOP demo service.
 */

namespace App\Spiral\Bootloader;

use App\Spiral\Service\DbEventLog;
use Spiral\Boot\Bootloader\Bootloader;
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
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(Container $container): void
    {
        // PSR-14 listener for ItemCreated is registered declaratively in
        // config/events.php (picked up by EventsBootloader's ConfigProcessor);
        // patching the `events` config at runtime is fragile because the
        // config object can already be delivered by the time APP-phase
        // bootloaders run.

        // Attach the DB-activity listener used by /features/db-events:
        // Cycle DBAL writes query/transaction log lines into the driver's
        // PSR-3 channel; Spiral's LogFactory turns them into LogEvents and
        // hands them to all registered listeners (bound by CoreBootloader).
        $container
            ->get(\Spiral\Logger\ListenerRegistryInterface::class)
            ->addListener($container->get(DbEventLog::class));
    }

    public static function initDbEventLog(): DbEventLog
    {
        return new DbEventLog();
    }
}