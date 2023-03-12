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

class ImportModel implements ImportModelInterface
{
    private ?UploadedFile $importFile = null;
    private ?string $delimiter = ';';
    private bool $preview = true;

    public function getImportFile(): ?UploadedFile
    {
        return $this->importFile;
    }

    public function setImportFile(?UploadedFile $importFile): void
    {
        $this->importFile = $importFile;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function setDelimiter(?string $delimiter): void
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
