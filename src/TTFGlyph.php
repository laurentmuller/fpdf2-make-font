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

class TTFGlyph
{
    /**
     * @param array<int, int>|null $components
     */
    public function __construct(
        public int $width,
        public int $lsb,
        public string|int $name = '',
        public int $length = 0,
        public int $offset = 0,
        public int $ssid = 0,
        public ?array $components = null
    ) {
    }

    /**
     * @phpstan-assert-if-true array<int, int> $this->components
     */
    public function isComponents(): bool
    {
        return null !== $this->components;
    }

    public function isLength(): bool
    {
        return 0 !== $this->length;
    }

    public function isSsid(): bool
    {
        return 0 !== $this->ssid;
    }
}
