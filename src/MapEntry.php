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

readonly class MapEntry
{
    public const string NOT_DEF = '.notdef';

    public function __construct(
        public int $uv = -1,
        public string $name = self::NOT_DEF
    ) {
    }

    public static function instance(): self
    {
        return new self();
    }

    public function isName(): bool
    {
        return self::NOT_DEF !== $this->name;
    }

    public function isUv(): bool
    {
        return -1 !== $this->uv;
    }
}
