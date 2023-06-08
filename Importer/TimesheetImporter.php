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

final class TimesheetImporter extends AbstractTimesheetImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'Date',
        'From',
        'To',
        'Duration',
        'Rate',
        'User',
        'Customer',
        'Project',
        'Activity',
        'Description',
        'Exported',
        'Tags',
        'HourlyRate',
        'InternalRate',
        'FixedRate',
    ];

    public function supports(array $header): bool
    {
        $result = array_diff(self::$supportedHeader, $header);

        return empty($result);
    }

    public function checkHeader(array $header): array
    {
        return array_diff(self::$supportedHeader, $header);
    }

    protected function createImportData(ImportRow $row): ImportData
    {
        return new ImportData('time_tracking', array_keys($row->getData()));
    }
}
