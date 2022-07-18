<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportRow;

interface ImporterInterface
{
    public function supports(array $header): bool;

    /**
     * @param array<ImportRow> $rows
     * @param bool $dryRun
     * @return ImportData
     */
    public function import(array $rows, bool $dryRun): ImportData;
}
