<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Model;

final class ImportRow
{
    /**
     * @var array<string, mixed>
     */
    private $row;
    /**
     * @var array<string>
     */
    private $errors = [];

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(): bool
    {
        return \count($this->errors) > 0;
    }

    public function getData(): array
    {
        return $this->row;
    }
}
