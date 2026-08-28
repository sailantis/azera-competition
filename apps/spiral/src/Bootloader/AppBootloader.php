<?php

declare(strict_types=1);

/**
 * App-level bindings: PSR-14 event listener registration (via the events
 * config) and the AOP demo service.
 */

namespace App\Spiral\Bootloader;

use App\Spiral\Event\ItemCreated;
use App\Spiral\Listener\StampListener;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Events\Config\EventsConfig;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

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

    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(): void
    {
        // PSR-14 listener for ItemCreated is registered declaratively in
        // config/events.php (picked up by EventsBootloader's ConfigProcessor);
        // patching the `events` config at runtime is fragile because the
        // config object can already be delivered by the time APP-phase
        // bootloaders run.
    }
}