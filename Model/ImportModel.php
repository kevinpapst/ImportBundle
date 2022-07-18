<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportModel
{
    /**
     * @var UploadedFile|null
     */
    private $csvFile;
    /**
     * @var string
     */
    private $delimiter = ';';
    /**
     * @var bool
     */
    private $preview = true;

    public function getCsvFile(): ?UploadedFile
    {
        return $this->csvFile;
    }

    public function setCsvFile(?UploadedFile $csvFile): void
    {
        $this->csvFile = $csvFile;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function setPreview(bool $preview): void
    {
        $this->preview = $preview;
    }
}
