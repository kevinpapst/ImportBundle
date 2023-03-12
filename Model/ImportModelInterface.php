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

interface ImportModelInterface
{
    public function getImportFile(): ?UploadedFile;

    public function getDelimiter(): ?string;

    public function isPreview(): bool;
}
