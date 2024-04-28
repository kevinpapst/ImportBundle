<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Importer;

final class ImporterHelper
{
    public static function convertBoolean(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return false;
        }

        if (\is_int($value)) {
            return (bool) $value;
        }

        if (\is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === 'true' || $value === 'yes' || $value === 'on' || $value === '1') {
                return true;
            }
        }

        return false;
    }
}
