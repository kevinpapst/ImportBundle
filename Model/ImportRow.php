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
     * @var array<array{field: ?string, message: string}>
     */
    private array $errors = [];
    /**
     * @var array<string, mixed>|null
     */
    private ?array $processedData = null;

    /**
     * @param array<string, bool|int|string> $row
     */
    public function __construct(private readonly array $row)
    {
    }

    public function addError(string $message, ?string $field = null): void
    {
        $field = ($field === '') ? null : $field;
        $this->errors[] = ['field' => (\is_string($field) ? strtolower($field) : null), 'message' => $message];
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return array_column($this->errors, 'message');
    }

    /**
     * Returns errors grouped by lowercase field name.
     * The empty-string key holds errors not tied to a specific field.
     *
     * @return array<string, string[]>
     */
    public function getFieldErrors(): array
    {
        $result = [];
        foreach ($this->errors as $error) {
            $field = $error['field'] ?? '__EMPTY__';
            $result[$field][] = $error['message'];
        }

        return $result;
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

    /**
     * @param array<string, mixed> $data
     */
    public function setProcessedData(array $data): void
    {
        $this->processedData = $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProcessedData(): ?array
    {
        return $this->processedData;
    }

    public function hasProcessedData(): bool
    {
        return $this->processedData !== null;
    }
}
