<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        $auth = $this->security;

        if (!$auth->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        if (!$auth->isGranted('importer')) {
            return;
        }

        $system = $event->getSystemMenu();
        $system->addChild(
            new MenuItemModel('importer', 'Importer', 'importer', [], 'fas fa-file-import')
        );
        $system->addChildRoute('importer_timesheet');
        $system->addChildRoute('importer_customer');
        $system->addChildRoute('importer_project');
        $system->addChildRoute('importer_grandtotal');
        $system->addChildRoute('importer_clockify');
        $system->addChildRoute('importer_toggl');
    }
}
