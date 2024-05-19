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

        return \count($result) === 0;
    }

    public function checkHeader(array $header): array
    {
        return array_diff(self::$supportedHeader, $header);
    }

    protected function createImportData(ImportRow $row): ImportData
    {
        $header = [
            'Begin',
            'End',
            'Customer',
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
            'Begin' => $rawData['Start Date'] . ' ' . $rawData['Start Time'],
            'End' => $rawData['End Date'] . ' ' . $rawData['End Time'],
            'Customer' => 'Importer',
            'Project' => '',
            'Description' => '',
            'Activity' => '',
            'User' => '',
            'Email' => '',
            'Tags' => '',
            'Billable' => true,
            'Duration' => '',
            'HourlyRate' => '',
            'Rate' => '',
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
                case 'Billable':
                    $values[$key] = ImporterHelper::convertBoolean($value);
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

                case 'Start Date':
                case 'Start Time':
                case 'End Date':
                case 'End Time':
                    // nothing to do
                    break;
                case 'Duration (h)':
                    $values['Duration'] = $durationParser->parseDurationString((string) $value);
                    break;
                case 'Duration (decimal)':
                    // see https://github.com/kimai/kimai/issues/4838 - rounding issues cause minute loss
                    // $values['Duration'] = $durationParser->parseDurationString((string) $value);
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
