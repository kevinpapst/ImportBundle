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
        if ($model->getCsvFile() === null) {
            throw new ImportException('Missing uploaded file');
        }

        if ($model->getCsvFile()->getMimeType() !== 'text/csv' && $model->getCsvFile()->getClientMimeType() !== 'text/csv') {
            throw new ImportException('Invalid Mimetype given');
        }

        try {
            $csv = Reader::createFromFileObject($model->getCsvFile()->openFile());
            $csv->setDelimiter($model->getDelimiter());
            $csv->setHeaderOffset(0);

            $header = $csv->getHeader();

            $oppositeDelimiter = ',';
            if ($model->getDelimiter() === ',') {
                $oppositeDelimiter = ';';
            }

            if (\count($header) === 1 && stripos($header[0], $oppositeDelimiter) !== false) {
                throw new ImportException('Invalid delimiter chosen?');
            }

            $preview = $model->isPreview();

            if ($csv->count() > 1000) {
                throw new ImportException('Maximum of 1000 rows allowed per import');
            }

            foreach ($this->importer as $importer) {
                if ($importer->supports($header)) {
                    $rows = [];
                    /** @var array<string, mixed> $record */
                    foreach ($csv->getRecords() as $record) {
                        $rows[] = new ImportRow($record);
                    }

                    return $importer->import($rows, $preview);
                }
            }
        } catch (LeagueException $ex) {
            throw new ImportException($ex->getMessage());
        }

        throw new ImportException('Could not find matching importer');
    }
}
