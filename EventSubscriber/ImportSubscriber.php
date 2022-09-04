<?php

/*
 * This file is part of the ExpensesBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\EventSubscriber;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

class ImportSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'import';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $event->addHelp($this->documentationLink('plugin-import.html'));
    }
}
