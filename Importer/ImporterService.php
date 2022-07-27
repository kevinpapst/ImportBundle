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
use KimaiPlugin\ImportBundle\Model\ImportModel;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use League\Csv\Exception as LeagueException;
use League\Csv\Reader;

final class ImporterService
{
    public const MAX_ROWS = 1000;

    /**
     * @var iterable<ImporterInterface>
     */
    private $importer;

    /**
     * @param iterable<ImporterInterface> $importer
     */
    public function __construct(iterable $importer)
    {
        $this->importer = $importer;
    }

    /**
     * @param ImportModel $model
     * @return ImportData
     * @throws ImportException
     */
    public function import(ImportModel $model): ImportData
    {
        if ($model->getImportFile() === null) {
            throw new ImportException('Missing uploaded file');
        }

        $file = $model->getImportFile();

        if ($file->getMimeType() === 'text/csv' || $file->getClientMimeType() === 'text/csv') {
            return $this->importCsv($model);
        }

        if ($file->getMimeType() === 'application/json' || $file->getClientMimeType() === 'application/json') {
            return $this->importJson($model);
        }

        throw new ImportException('Unsupported file given');
    }

    private function importJson(ImportModel $model): ImportData
    {
        try {
            if ($model->getImportFile() === null) {
                throw new ImportException('Missing uploaded file');
            }

            $json = file_get_contents($model->getImportFile()->getRealPath());
            if ($json === false) {
                throw new ImportException('Cannot read uploaded file');
            }

            /** @var null|false|array $data */
            $data = json_decode($json, true);

            if ($data === false || $data === null || ($totalRows = \count($data)) === 0) {
                throw new ImportException('Unsupported file given: empty');
            }

            $header = array_keys($data[0]);

            $preview = $model->isPreview();

            if ($totalRows > self::MAX_ROWS) {
                throw new ImportException('Maximum of 1000 rows allowed per import');
            }

            foreach ($this->importer as $importer) {
                if ($importer->supports($header)) {
                    $rows = [];
                    /** @var array<string, mixed> $record */
                    foreach ($data as $record) {
                        $rows[] = new ImportRow($record);
                    }

                    @unlink($model->getImportFile()->getRealPath());

                    return $importer->import($rows, $preview);
                }
            }
        } catch (\Exception $ex) {
            throw new ImportException($ex->getMessage());
        }

        throw new ImportException('Could not find matching importer');
    }

    private function importCsv(ImportModel $model): ImportData
    {
        try {
            if ($model->getImportFile() === null) {
                throw new ImportException('Missing uploaded file');
            }

            $csv = Reader::createFromFileObject($model->getImportFile()->openFile());
            $csv->setDelimiter($model->getDelimiter());
            $csv->setHeaderOffset(0);

            $header = $csv->getHeader();

            if ($csv->count() === 0) {
                throw new ImportException('Unsupported file given: empty');
            }

            $oppositeDelimiter = ',';
            if ($model->getDelimiter() === ',') {
                $oppositeDelimiter = ';';
            }

            if (\count($header) === 1 && stripos($header[0], $oppositeDelimiter) !== false) {
                throw new ImportException('Unsupported file given: wrong delimiter?');
            }

            $preview = $model->isPreview();

            if ($csv->count() > self::MAX_ROWS) {
                throw new ImportException('Maximum of 1000 rows allowed per import');
            }

            foreach ($this->importer as $importer) {
                if ($importer->supports($header)) {
                    $rows = [];
                    /** @var array<string, mixed> $record */
                    foreach ($csv->getRecords() as $record) {
                        $rows[] = new ImportRow($record);
                    }

                    @unlink($model->getImportFile()->getRealPath());

                    return $importer->import($rows, $preview);
                }
            }
        } catch (LeagueException $ex) {
            throw new ImportException($ex->getMessage());
        }

        throw new ImportException('Could not find matching importer');
    }
}
