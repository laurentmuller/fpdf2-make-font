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

readonly class AfmFileParser
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @return array<string, int>
     */
    public function parse(string $afmFile, FontInfo $font): array
    {
        $cw = [];
        $parser = new FileLinesParser($this->translator);
        $lines = $parser->getLines($afmFile);
        foreach ($lines as $line) {
            $values = \explode(' ', \rtrim($line));
            if (\count($values) < 2) {
                continue;
            }
            switch ($values[0]) {
                case 'C':
                    $cw[$values[7]] = (int) $values[4];
                    break;
                case 'Weight':
                    $font->weight = $values[1];
                    break;
                case 'FontName':
                    $font->fontName = $values[1];
                    break;
                case 'Ascender':
                    $font->ascender = (int) $values[1];
                    break;
                case 'Descender':
                    $font->descender = (int) $values[1];
                    break;
                case 'UnderlineThickness':
                    $font->underlineThickness = (int) $values[1];
                    break;
                case 'UnderlinePosition':
                    $font->underlinePosition = (int) $values[1];
                    break;
                case 'CapHeight':
                    $font->capHeight = (int) $values[1];
                    break;
                case 'StdVW':
                    $font->stdVW = (int) $values[1];
                    break;
                case 'ItalicAngle':
                    $font->italicAngle = (int) $values[1];
                    break;
                case 'IsFixedPitch':
                    $font->fixedPitch = \filter_var($values[1], \FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'FontBBox':
                    $font->fontBBox = [
                        (int) $values[1],
                        (int) $values[2],
                        (int) $values[3],
                        (int) $values[4],
                    ];
                    break;
            }
        }

        return $cw;
    }
}
