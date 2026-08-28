<?php

declare(strict_types=1);

/**
 * Benchmark application kernel.
 *
 * Mirrors the official spiral/app skeleton boot sequence:
 *  - SYSTEM phase: core + tokenizer only.
 *  - LOAD phase (defineBootloaders): all framework bootloaders (Cycle, events,
 *    views, cache, validation, HTTP + routing). This phase is where the kernel
 *    fires the generic `booting`/`booted` callbacks — TokenizerListenerBootloader
 *    registers its scan/finalize hooks there, so the Cycle annotated locators
 *    (registered as tokenizer listeners by AnnotatedBootloader in this same
 *    phase) are correctly scanned and finalized.
 *  - APP phase (defineAppBootloaders): the app's own bootloaders.
 */

namespace App\Spiral;

use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Bootloader\Http\RouterBootloader;
use Spiral\Cache\Bootloader\CacheBootloader;
use Spiral\Cycle\Bootloader\AnnotatedBootloader;
use Spiral\Cycle\Bootloader\CycleOrmBootloader;
use Spiral\Cycle\Bootloader\DatabaseBootloader;
use Spiral\Cycle\Bootloader\SchemaBootloader;
use Spiral\Events\Bootloader\EventsBootloader;
use Spiral\League\Event\Bootloader\EventBootloader;
use Spiral\Tokenizer\Bootloader\TokenizerBootloader;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Spiral\Views\Bootloader\ViewsBootloader;
use Spiral\Stempler\Bootloader\StemplerBootloader;
use Spiral\Validator\Bootloader\ValidatorBootloader;

final class Kernel extends \Spiral\Framework\Kernel
{
    protected const SYSTEM = [
        CoreBootloader::class,
        TokenizerBootloader::class,
        TokenizerListenerBootloader::class,
    ];

    /**
     * @return array<int, class-string>
     */
    #[\Override]
    protected function defineBootloaders(): array
    {
        return [
            // Validation first: patches the `tokenizer` config in init()
            // (adds the validator source dir). Must run before the tokenizer
            // config object gets delivered to any consumer.
            ValidatorBootloader::class,

            // Cycle (DBAL + ORM + annotated entities)
            DatabaseBootloader::class,
            SchemaBootloader::class,
            CycleOrmBootloader::class,
            AnnotatedBootloader::class,

            // Events, cache, views
            // AppBootloader must init in this phase: it patches the `events`
            // config, which EventsBootloader::boot() delivers. All init
            // methods of a phase run before all boot methods, so an init()
            // here is guaranteed to land before that delivery.
            Bootloader\AppBootloader::class,
            EventsBootloader::class,
            EventBootloader::class,
            CacheBootloader::class,
            ViewsBootloader::class,
            StemplerBootloader::class,

            // HTTP + routing
            HttpBootloader::class,
            RouterBootloader::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    #[\Override]
    protected function defineAppBootloaders(): array
    {
        return [
            Bootloader\AppRoutesBootloader::class,
        ];
    }
}