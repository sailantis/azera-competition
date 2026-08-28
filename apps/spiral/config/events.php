<?php

declare(strict_types=1);

return [
    'listeners' => [
        \App\Spiral\Event\ItemCreated::class => [
            \App\Spiral\Listener\StampListener::class,
        ],
    ],
];