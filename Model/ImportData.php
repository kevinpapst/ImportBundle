<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Model;

final class ImportData
{
    /**
     * @var array<ImportRow>
     */
    private array $rows;
    /**
     * @var array<string>
     */
    private array $status = [];

    public function __construct(private readonly string $name, private readonly array $header)
    {
    }

    public function getTitle(): string
    {
        return $this->name;
    }

    public function getHeader(): array
    {
        return $this->header;
    }

    public function addRow(ImportRow $row): void
    {
        $this->rows[] = $row;
    }

    /**
     * @return ImportRow[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function countRows(): int
    {
        return \count($this->rows);
    }

    public function addStatus(string $status): void
    {
        $this->status[] = $status;
    }

    /**
     * @return string[]
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    public function countErrors(): int
    {
        $errors = 0;

        foreach ($this->rows as $row) {
            $errors += \count($row->getErrors());
        }

        return $errors;
    }
}
