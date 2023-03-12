<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

use App\Utils\Duration;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportRow;

final class ClockifyTimesheetImporter extends AbstractTimesheetImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'Project',
        'Client',
        'Description',
        'Task',
        'User',
        'Group', // not yet supported
        'Email',
        'Tags',
        'Billable',
        'Start Date',
        'Start Time',
        'End Date',
        'End Time',
        'Duration (h)',
        'Duration (decimal)',
        //'Billable Rate (GBP)',
        //'Billable Amount (GBP)'
    ];

    public function supports(array $header): bool
    {
        $result = array_diff(self::$supportedHeader, $header);

        return empty($result);
    }

    protected function createImportData(ImportRow $row): ImportData
    {
        $header = [
            'Customer',
            'Start',
            'End',
            'Project',
            'Description',
            'Activity',
            'User',
            'Email',
            'Tags',
            'Billable',
            'Duration',
            'Hourly rate',
            'Rate',
        ];

        return new ImportData('Clockify', $header);
    }

    public function importRow(Duration $durationParser, ImportData $data, ImportRow $row, bool $dryRun): void
    {
        $rawData = $row->getData();
        $values = [
            'Customer' => 'Importer',
            'Begin' => $rawData['Start Date'] . ' ' . $rawData['Start Time'],
            'End' => $rawData['End Date'] . ' ' . $rawData['End Time'],
        ];

        foreach ($rawData as $key => $value) {
            switch ($key) {
                case 'Email':
                case 'User':
                case 'Project':
                case 'Description':
                case 'Tags':
                    $values[$key] = $value;
                    break;
                case 'Client':
                    if ($value !== '') {
                        $values['Customer'] = $value;
                    }
                    break;
                case 'Task':
                    $values['Activity'] = $value;
                    break;
                case 'Group':
                    // TODO
                    break;
                case 'Billable':
                    $values['Billable'] = ($value === 'Yes');
                    break;

                case 'Start Date':
                case 'Start Time':
                case 'End Date':
                case 'End Time':
                case 'Duration (h)':
                    // nothing to do
                    break;
                case 'Duration (decimal)':
                    $values['Duration'] = $value;
                    break;
                default:
                    if (str_starts_with($key, 'Billable Rate')) {
                        $values['HourlyRate'] = $value;
                    } elseif (str_starts_with($key, 'Billable Amount')) {
                        $values['Rate'] = $value;
                    }
                    break;
            }
        }

        parent::importRow($durationParser, $data, new ImportRow($values), $dryRun);
    }
}
