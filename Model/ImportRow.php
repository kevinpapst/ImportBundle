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
     * @var array<string>
     */
    private array $errors = [];

    /**
     * @param array<string, bool|int|string> $row
     */
    public function __construct(private readonly array $row)
    {
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

    /**
     * @return array<string, bool|int|string>
     */
    public function getData(): array
    {
        return $this->row;
    }
}
