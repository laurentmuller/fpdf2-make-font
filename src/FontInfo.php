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

class FontInfo
{
    /**
     * @param int[] $fontBBox
     * @param int[] $widths
     */
    public function __construct(
        public string $file = '',
        public string $data = '',
        public int $originalSize = 0,
        public bool $bold = false,
        public int $italicAngle = 0,
        public bool $fixedPitch = false,
        public ?int $ascender = null,
        public ?int $descender = null,
        public int $underlineThickness = 0,
        public int $underlinePosition = 0,
        public array $fontBBox = [],
        public int $missingWidth = 0,
        public int $size1 = 0,
        public int $size2 = 0,
        public array $widths = [],
        public ?string $fontName = null,
        public ?int $stdVW = null,
        public ?int $capHeight = null,
        public ?string $weight = null
    ) {
    }

    public function getAscender(): int
    {
        return $this->ascender ?? 0;
    }

    public function getCapHeight(): int
    {
        return $this->capHeight ?? $this->getAscender();
    }

    public function getDataLength(): int
    {
        return \strlen($this->data);
    }

    public function getDescender(): int
    {
        return $this->descender ?? 0;
    }

    public function getFontName(): string
    {
        return $this->fontName ?? '';
    }

    /**
     * @phpstan-assert-if-true int $this->ascender
     */
    public function isAscender(): bool
    {
        return null !== $this->ascender;
    }

    /**
     * @phpstan-assert-if-true int $this->descender
     */
    public function isDescender(): bool
    {
        return null !== $this->descender;
    }

    public function isItalicAngle(): bool
    {
        return 0 !== $this->italicAngle;
    }

    /**
     * @phpstan-assert-if-true int $this->stdVW
     */
    public function isStdVW(): bool
    {
        return null !== $this->stdVW;
    }

    /**
     * @phpstan-assert-if-true string $this->weight
     */
    public function isWeight(): bool
    {
        return null !== $this->weight;
    }
}
