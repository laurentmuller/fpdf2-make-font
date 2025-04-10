<?php

/*
 * This file is part of the 'fpdf2-make-font' package.
 *
 * For the license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author bibi.nu <bibi@bibi.nu>
 */

declare(strict_types=1);

namespace fpdf;

class MakeFontException extends \RuntimeException
{
    public static function format(string $format, string|int ...$values): self
    {
        return self::instance(\sprintf($format, ...$values));
    }

    public static function instance(string $message): self
    {
        return new self($message);
    }
}
