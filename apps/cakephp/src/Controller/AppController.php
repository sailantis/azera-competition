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
     * Shared view vars for every template render — mirrors azera's
     * RequestContext view vars (locale + platform) used by the layout
     * footer ("Rendered with … | Locale: en_US | Platform: desktop").
     */
    public function beforeRender(EventInterface $event): void
    {
        $this->set('locale', 'en_US');
        $this->set('platform', 'desktop');
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