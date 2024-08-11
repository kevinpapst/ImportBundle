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
use KimaiPlugin\ImportBundle\Model\ImportModelInterface;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ImporterInterface
{
    /**
     * @param array<int, string> $header
     */
    public function supports(array $header): bool;

    /**
     * Returns a list of invalid header
     * @param array<int, string> $header
     */
    public function checkHeader(array $header): array;

    /**
     * @param ImportModelInterface $model
     * @param array<ImportRow> $rows
     * @return ImportData
     */
    public function import(ImportModelInterface $model, array $rows): ImportData;
}
