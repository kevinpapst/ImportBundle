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
     * @var string[]
     */
    private static array $supportedHeader = [
        'Date',
        'From',
        'To',
        'Duration',
        'Rate',
        'User',
        'Username',
        'Customer',
        'Project',
        'Activity',
        'Description',
        'Exported',
        'Tags',
        'HourlyRate',
        'InternalRate',
        'FixedRate',
        'Billable',
    ];

    /**
     * @var array<string, string>
     */
    private array $translatedHeader = [];

    public function supports(array $header): bool
    {
        return empty($this->checkHeader($header));
    }

    private function getTranslatedHeaders(): array
    {
        if (\count($this->translatedHeader) === 0) {
            // some old versions of the export CSV had translation issues
            $this->translatedHeader = [
                'rate_internal' => 'InternalRate',
                'internalRate' => 'InternalRate',
                'internal_rate' => 'InternalRate',
                'username' => 'Username',
            ];

            $locales = ['en', null]; // order "en" than current (null) locale

            foreach ($locales as $locale) {
                $this->translatedHeader = array_merge($this->translatedHeader, [
                    $this->translator->trans('date', [], null, $locale) => 'Date',
                    $this->translator->trans('begin', [], null, $locale) => 'From',
                    $this->translator->trans('end', [], null, $locale) => 'To',
                    $this->translator->trans('duration', [], null, $locale) => 'Duration',
                    $this->translator->trans('rate', [], null, $locale) => 'Rate',
                    $this->translator->trans('internalRate', [], null, $locale) => 'InternalRate',
                    $this->translator->trans('username', [], null, $locale) => 'Username',
                    $this->translator->trans('name', [], null, $locale) => 'User',
                    $this->translator->trans('user', [], null, $locale) => 'Username',
                    $this->translator->trans('customer', [], null, $locale) => 'Customer',
                    $this->translator->trans('project', [], null, $locale) => 'Project',
                    $this->translator->trans('activity', [], null, $locale) => 'Activity',
                    $this->translator->trans('description', [], null, $locale) => 'Description',
                    $this->translator->trans('exported', [], null, $locale) => 'Exported',
                    $this->translator->trans('billable', [], null, $locale) => 'Billable',
                    $this->translator->trans('tags', [], null, $locale) => 'Tags',
                    $this->translator->trans('hourlyRate', [], null, $locale) => 'HourlyRate',
                    $this->translator->trans('fixedRate', [], null, $locale) => 'FixedRate',
                ]);
            }
        }

        return $this->translatedHeader;
    }

    private function getSupportedHeaders(): array
    {
        return array_merge(self::$supportedHeader, array_keys($this->getTranslatedHeaders()));
    }

    public function checkHeader(array $header): array
    {
        $known = [];
        $translated = $this->getTranslatedHeaders();

        foreach ($header as $name) {
            if (\array_key_exists($name, $translated)) {
                $known[] = $translated[$name];
            } elseif (\array_key_exists($name, self::$supportedHeader)) {
                $known[] = $name;
            }
        }

        $required = [
            'Date' => ['Date'],
            'From' => ['From', 'Begin'],
            'To' => ['End', 'To', 'Duration'],
            'Rate' => ['Rate'],
            'User' => ['User', 'Username', 'Name'],
            'Customer' => ['Customer'],
            'Project' => ['Project'],
            'Activity' => ['Activity'],
        ];

        $missing = [];

        foreach ($required as $name => $columns) {
            $found = false;
            foreach ($columns as $column) {
                if (\in_array($column, $known, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    protected function createImportData(ImportRow $row): ImportData
    {
        return new ImportData('time_tracking', array_keys($row->getData()));
    }

    public function importRow(Duration $durationParser, ImportData $data, ImportRow $row, bool $dryRun): void
    {
        $rawData = $row->getData();
        $translated = $this->getTranslatedHeaders();

        foreach ($rawData as $key => $value) {
            if (\array_key_exists($key, $translated)) {
                if (($newKey = $translated[$key]) === $key) {
                    continue;
                }
                if (\array_key_exists($newKey, $rawData)) {
                    // prevent that existing key will be overwritten (e.g. Date using the export CSV)
                    continue;
                }
                $rawData[$newKey] = $value;
            }
        }

        parent::importRow($durationParser, $data, new ImportRow($rawData), $dryRun);
    }
}
