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

final class TogglTimesheetImporter extends AbstractTimesheetImporter implements ImporterInterface
{
    /**
     * @var string[]
     */
    private static array $supportedHeader = [
        'User',
        'Email',
        'Client',
        'Project',
        'Task',
        'Description',
        'Billable',
        'Start date',
        'Start time',
        'End date',
        'End time',
        'Duration',
        'Tags',
        //'Amount (USD)',
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
            'Customer',
            'Project',
            'Activity',
            'Begin',
            'End',
            'Description',
            'User',
            'Email',
            'Tags',
            'Billable',
            'Rate',
            'Duration',
        ];

        return new ImportData('Toggl', $header);
    }

    public function importRow(Duration $durationParser, ImportData $data, ImportRow $row, bool $dryRun): void
    {
        $rawData = $row->getData();
        $values = [
            'Customer' => 'Importer',
            'Project' => 'Importer',
            'Activity' => 'Importer',
            'Begin' => $rawData['Start date'] . ' ' . $rawData['Start time'],
            'End' => $rawData['End date'] . ' ' . $rawData['End time'],
            'Description' => '',
            'User' => '',
            'Email' => '',
            'Tags' => '',
            'Billable' => true,
            'Rate' => '0.0',
            'Duration' => '',
        ];

        foreach ($rawData as $key => $value) {
            switch (strtolower($key)) {
                case 'email':
                case 'user':
                case 'project':
                case 'description':
                case 'tags':
                    $values[$key] = $value;
                    break;
                case 'billable':
                    $values[$key] = ImporterHelper::convertBoolean($value);
                    break;
                case 'client':
                    if ($value !== '') {
                        $values['Customer'] = $value;
                    }
                    break;
                case 'task':
                    if ($value !== '') {
                        $values['Activity'] = $value;
                    }
                    break;
                case 'start date':
                case 'start time':
                case 'end date':
                case 'end time':
                    // nothing to do
                    break;
                case 'duration':
                    $values['Duration'] = $durationParser->parseDurationString((string) $value);
                    break;
                default:
                    if (str_starts_with($key, 'Amount') && $value !== '') {
                        $values['Rate'] = $value;
                    }
                    break;
            }
        }

        parent::importRow($durationParser, $data, new ImportRow($values), $dryRun);
    }
}
