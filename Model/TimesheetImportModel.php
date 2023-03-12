<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Model;

class TimesheetImportModel extends ImportModel
{
    private bool $globalActivities = true;

    public function isGlobalActivities(): bool
    {
        return $this->globalActivities;
    }

    public function setGlobalActivities(bool $globalActivities): void
    {
        $this->globalActivities = $globalActivities;
    }
}
