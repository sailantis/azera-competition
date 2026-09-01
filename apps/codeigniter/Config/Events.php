<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — events registration.
 *
 * Events::initialize() unconditionally includes APPPATH/Config/Events.php
 * (system/Events/Events.php line ~86-91); without it every request fails.
 * App listeners are registered inline in the Feature controller instead.
 */

use CodeIgniter\Events\Events;

/*
|--------------------------------------------------------------------
| Framework events
|--------------------------------------------------------------------
| The DBQuery tap for the db-events feature is registered from
| Ci4App\Support\DbEventLog (register() is idempotent), so nothing to do
| here. Keep this file as a harmless no-op stub.
*/