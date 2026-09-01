<?php

/**
 * View/view-engine configuration — Blade compiled views cache in the shared
 * writable dir so compiled templates persist across benchmark runs.
 */

return [
    'paths' => [
        realpath(__DIR__ . '/../resources/views') ?: __DIR__ . '/../resources/views',
    ],
    'compiled' => realpath(__DIR__ . '/../../writable/laravel/views') ?: __DIR__ . '/../../writable/laravel/views',
];