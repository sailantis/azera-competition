<?php

declare(strict_types=1);

/**
 * Items controller-like base: Cake controllers die without templates; we
 * keep JSON actions returning Response objects directly (idiomatic cake:
 * $this->response->withStringBody / withType) and template actions call
 * $this->render().
 */

namespace App\Cake\Controller;

use App\Cake\Db;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Datasource\ModelAwareTrait;

abstract class AppController extends Controller
{

    /**
     * Shared view vars for every template render — locale + platform for the layout
     * footer, resolved by the SAME parser every other entrant runs
     * (`bench-view-globals.php`).
     *
     * These were two literals here, so Cake's footer asserted a locale and platform
     * nothing had resolved. Running the shared parser makes all seven do the same
     * work; the values are unchanged because the benchmark sends no header hints.
     */
    public function beforeRender(EventInterface $event): void
    {
        $request = $this->getRequest();
        $globals = benchViewGlobals(
            $request->getHeaderLine('Accept-Language') ?: null,
            $request->getHeaderLine('User-Agent') ?: null,
        );

        $this->set('locale', $globals['locale']);
        $this->set('platform', $globals['platform']);
    }

    /**
     * Shared Items table accessor (TableRegistry-backed ORM).
     */
    protected function items(): \App\Cake\Model\Table\ItemsTable
    {
        static $table = null;
        if ($table === null) {
            $table = Db::tableLocator()->get('Items');
        }

        return $table;
    }
}