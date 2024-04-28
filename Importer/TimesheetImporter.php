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

final class TimesheetImporter extends AbstractTimesheetImporter implements ImporterInterface
{
    /**
     * @var array<string, string>
     */
    private array $translatedHeader = [
        'internal_rate' => 'InternalRate',
        'rate_internal' => 'InternalRate',
        'internalrate' => 'InternalRate',
        'hourly_rate' => 'HourlyRate',
        'rate_hourly' => 'HourlyRate',
        'hourlyrate' => 'HourlyRate',
        'fixed_rate' => 'FixedRate',
        'rate_fixed' => 'FixedRate',
        'fixedrate' => 'FixedRate',
        'user' => 'User',
        'username' => 'User',
        'name' => 'User',
        'email' => 'Email',
        'date' => 'Date',
        'from' => 'From',
        'to' => 'To',
        'duration' => 'Duration',
        'rate' => 'Rate',
        'customer' => 'Customer',
        'project' => 'Project',
        'activity' => 'Activity',
        'description' => 'Description',
        'exported' => 'Exported',
        'billable' => 'Billable',
        'tags' => 'Tags',
    ];

    public function supports(array $header): bool
    {
        $missing = $this->checkHeader($header);

        return \count($missing) === 0;
    }

    /**
     * @return array<string, string>
     */
    private function getTranslatedHeaders(): array
    {
        return $this->translatedHeader;
    }

    public function checkHeader(array $header): array
    {
        $known = [];

        foreach ($header as $name) {
            $name = strtolower($name);
            if (\array_key_exists($name, $this->translatedHeader)) {
                $known[] = $this->translatedHeader[$name];
            }
        }
        $known = array_unique($known);

        $required = [
            'Date',
            'From',
            'To',
            'User',
            'Email',
            'Customer',
            'Project',
            'Activity',
        ];

        $missing = [];
        foreach ($required as $name) {
            if (!\in_array($name, $known, true)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    protected function createImportData(ImportRow $row): ImportData
    {
        $translated = $this->getTranslatedHeaders();
        $converted = [
            'Begin',
            'End'
        ];

        foreach ($row->getData() as $key => $value) {
            $k = strtolower($key);
            if ($k === 'date' || $k === 'from' || $k === 'to') {
                continue;
            }
            if (\array_key_exists($k, $translated)) {
                $key = $translated[$k];
            }
            $converted[] = $key;
        }

        return new ImportData('time_tracking', $converted);
    }

    public function importRow(Duration $durationParser, ImportData $data, ImportRow $row, bool $dryRun): void
    {
        $rawData = $row->getData();
        $translated = $this->getTranslatedHeaders();

        if (\array_key_exists('From', $rawData) && \is_string($rawData['From']) && $rawData['From'] !== '') {
            $len = \strlen($rawData['From']);
            if ($len === 1) {
                $rawData['From'] = '0' . $rawData['From'] . ':00';
            } elseif ($len == 2) {
                $rawData['From'] = $rawData['From'] . ':00';
            }
        }

        if (\array_key_exists('To', $rawData) && \is_string($rawData['To']) && $rawData['To'] !== '') {
            $len = \strlen($rawData['To']);
            if ($len === 1) {
                $rawData['To'] = '0' . $rawData['To'] . ':00';
            } elseif ($len == 2) {
                $rawData['To'] = $rawData['To'] . ':00';
            }
        }

        $converted = [
            'Begin' => $rawData['Date'] . ' ' . $rawData['From'],
            'End' => $rawData['Date'] . ' ' . $rawData['To'],
        ];

        foreach ($rawData as $key => $value) {
            $k = strtolower($key);
            switch ($k) {
                case 'date':
                case 'from':
                case 'to':
                    // nothing to do
                    break;
                default:
                    if (\array_key_exists($k, $translated)) {
                        $key = $translated[$k];
                    }
                    $converted[$key] = \is_string($value) ? trim($value) : $value;
                    break;
            }
        }

        parent::importRow($durationParser, $data, new ImportRow($converted), $dryRun);
    }
}
