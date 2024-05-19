<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

class ProjectsSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'projects';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if (!$this->isGranted('importer')) {
            return;
        }

        $event->addQuickImport($this->path('importer_project'));
    }
}
