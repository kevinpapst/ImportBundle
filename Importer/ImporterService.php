<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

use App\Doctrine\DataSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\ImportBundle\Model\ImportData;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use KimaiPlugin\ImportBundle\Model\ImportRow;
use League\Csv\Reader;

final class ImporterService
{
    public const MAX_ROWS = 1000;

    /**
     * @param iterable<ImporterInterface> $importer
     */
    public function __construct(private iterable $importer, private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param ImportModel $model
     * @param class-string $importer
     * @return ImportData
     * @throws ImportException
     */
    public function import(ImportModel $model, string $importer): ImportData
    {
        if ($model->getImportFile() === null) {
            throw new ImportException('Missing uploaded file');
        }

        $found = false;
        foreach ($this->importer as $tmp) {
            if ($tmp instanceof $importer) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ImportException('Unknown importer requested');
        }

        $file = $model->getImportFile();

        $evm = $this->entityManager->getEventManager();
        $allListener = $evm->getAllListeners();
        foreach ($allListener as $event => $listeners) {
            foreach ($listeners as $hash => $object) {
                if ($object instanceof DataSubscriberInterface) {
                    $evm->removeEventListener([$event], $object);
                }
            }
        }

        if ($file->getMimeType() === 'text/csv' || $file->getClientMimeType() === 'text/csv') {
            return $this->importCsv($model, $importer);
        }

        if ($file->getMimeType() === 'application/json' || $file->getClientMimeType() === 'application/json') {
            return $this->importJson($model, $importer);
        }

        throw new ImportException('Unsupported file given');
    }

    /**
     * @param ImportModel $model
     * @param class-string $import
     * @return ImportData
     * @throws ImportException
     */
    private function importJson(ImportModel $model, string $import): ImportData
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

            /** @var array<int, string> $header */
            $header = array_keys($data[0]);

            if ($totalRows > self::MAX_ROWS) {
                throw new ImportException('Maximum of 1000 rows allowed per import');
            }

            foreach ($this->importer as $importer) {
                if ($importer instanceof $import) {
                    if (!$importer->supports($header)) {
                        throw new ImportException('Invalid file given, missing and/or invalid columns: ' . implode(', ', $importer->checkHeader($header)));
                    }
                    $rows = [];
                    /** @var array<string, mixed> $record */
                    foreach ($data as $record) {
                        $rows[] = new ImportRow($record);
                    }

                    @unlink($model->getImportFile()->getRealPath());

                    return $importer->import($model, $rows);
                }
            }
        } catch (\Exception $ex) {
            throw new ImportException($ex->getMessage());
        }

        throw new ImportException('Could not find matching importer');
    }

    /**
     * @param ImportModel $model
     * @param class-string $import
     * @return ImportData
     * @throws ImportException
     */
    private function importCsv(ImportModel $model, string $import): ImportData
    {
        try {
            if ($model->getImportFile() === null) {
                throw new ImportException('Missing uploaded file');
            }

            if ($model->getDelimiter() === null) {
                throw new ImportException('Missing delimiter');
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

            if ($csv->count() > self::MAX_ROWS) {
                throw new ImportException('Maximum of 1000 rows allowed per import');
            }

            foreach ($this->importer as $importer) {
                if ($importer instanceof $import) {
                    if (!$importer->supports($header)) {
                        throw new ImportException('Invalid file given, missing and/or invalid columns: ' . implode(', ', $importer->checkHeader($header)));
                    }
                    $rows = [];
                    /** @var array<string, mixed> $record */
                    foreach ($csv->getRecords() as $record) {
                        $rows[] = new ImportRow($record);
                    }

                    @unlink($model->getImportFile()->getRealPath());

                    return $importer->import($model, $rows);
                }
            }
        } catch (\Exception $ex) {
            throw new ImportException($ex->getMessage());
        }

        throw new ImportException('Could not find matching importer');
    }
}
