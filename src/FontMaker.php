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

/**
 * @phpstan-type FontInfoType = array{
 *     File: string,
 *     Data: string,
 *     OriginalSize: int,
 *     Bold: bool,
 *     ItalicAngle: int,
 *     IsFixedPitch: bool,
 *     Ascender?: int,
 *     Descender?: int,
 *     UnderlineThickness: int,
 *     UnderlinePosition: int,
 *     FontBBox: int[],
 *     MissingWidth: int,
 *     Size1: int,
 *     Size2: int,
 *     Widths: int[],
 *     FontName?: string,
 *     StdVW?: int,
 *     CapHeight?: int,
 *     Weight?: string}
 * @phpstan-type MapType = array{uv: int, name: string}
 * @phpstan-type RangeType = array{0: int, 1: int, 2: int, 3: int}
 */
class FontMaker
{
    /**
     * The default encoding ('cp1252').
     */
    public const DEFAULT_ENCODING = 'cp1252';

    private const FONT_TRUE_TYPE = 'TrueType';
    private const FONT_TYPE_1 = 'Type1';
    private const NOT_DEF = '.notdef';

    private Translator $translator;

    public function __construct(string $locale = Translator::DEFAULT_LOCALE)
    {
        $this->translator = new Translator($locale);
    }

    /**
     * Gets available encodings.
     *
     * @return array<string, string> an array with the encoding names and the encoding values
     */
    public static function getEncodings(): array
    {
        return [
            'cp1250 (Central Europe)' => 'cp1250',
            'cp1251 (Cyrillic)' => 'cp1251',
            'cp1252 (Western Europe)' => 'cp1252',
            'cp1253 (Greek)' => 'cp1253',
            'cp1254 (Turkish)' => 'cp1254',
            'cp1255 (Hebrew)' => 'cp1255',
            'cp1257 (Baltic)' => 'cp1257',
            'cp1258 (Vietnamese)' => 'cp1258',
            'cp874 (Thai)' => 'cp874',
            'ISO-8859-1 (Western Europe)' => 'ISO-8859-1',
            'ISO-8859-2 (Central Europe)' => 'ISO-8859-2',
            'ISO-8859-4 (Baltic)' => 'ISO-8859-4',
            'ISO-8859-5 (Cyrillic)' => 'ISO-8859-5',
            'ISO-8859-7 (Greek)' => 'ISO-8859-7',
            'ISO-8859-9 (Turkish)' => 'ISO-8859-9',
            'ISO-8859-11 (Thai)' => 'ISO-8859-11',
            'ISO-8859-15 (Western Europe)' => 'ISO-8859-15',
            'ISO-8859-16 (Central Europe)' => 'ISO-8859-16',
            'KOI8-R (Russian)' => 'KOI8-R',
            'KOI8-U (Ukrainian)' => 'KOI8-U',
        ];
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function makeFont(
        string $fontFile,
        string $encoding = self::DEFAULT_ENCODING,
        bool $embed = true,
        bool $subset = true
    ): void {
        if (!\file_exists($fontFile)) {
            throw $this->translator->format('error_file_not_found', $fontFile);
        }

        $pathInfo = \pathinfo($fontFile);
        $extension = $pathInfo['extension'] ?? '';
        $type = match (\strtolower($extension)) {
            'pfb' => self::FONT_TYPE_1,
            'ttf', 'otf' => self::FONT_TRUE_TYPE,
            default => throw $this->translator->format('error_extension', $extension)
        };

        $map = $this->loadMap($encoding);
        $font = match ($type) {
            self::FONT_TYPE_1 => $this->getFontFromType1($fontFile, $embed, $map),
            self::FONT_TRUE_TYPE => $this->getFontFromTrueType($fontFile, $embed, $map, $subset)
        };

        $baseName = $pathInfo['filename'];
        if ($embed) {
            $compressedFile = $baseName . '.z';
            $data = (string) \gzcompress($font['Data']);
            $this->saveToFile($compressedFile, $data, 'b');
            $font['File'] = $compressedFile;
            $this->message(\sprintf($this->trans('info_compressed_generated'), $compressedFile));
        }

        $phpFile = $baseName . '.php';
        $this->makeDefinitionFile($phpFile, $type, $encoding, $embed, $subset, $map, $font);
        $this->message(\sprintf($this->trans('info_file_generated'), $phpFile));
    }

    public function setLocale(string $locale = Translator::DEFAULT_LOCALE): void
    {
        if ($locale !== $this->translator->getLocale()) {
            $this->translator = new Translator($locale);
        }
    }

    /**
     * @phpstan-return FontInfoType
     */
    private function createEmptyFont(): array
    {
        return [
            'File' => '',
            'Data' => '',
            'OriginalSize' => 0,
            'Bold' => false,
            'ItalicAngle' => 0,
            'IsFixedPitch' => false,
            'UnderlineThickness' => 0,
            'UnderlinePosition' => 0,
            'FontBBox' => [],
            'MissingWidth' => 0,
            'Size1' => 0,
            'Size2' => 0,
            'Widths' => [],
        ];
    }

    /**
     * @return string[]
     */
    private function getFileLines(string $fileName): array
    {
        if (!\file_exists($fileName)) {
            throw $this->translator->format('error_file_not_found', $fileName);
        }
        $lines = \file($fileName, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines || [] === $lines) {
            throw $this->translator->format('error_file_empty', $fileName);
        }

        return $lines;
    }

    /**
     * @phpstan-param array<int, MapType> $map
     *
     * @phpstan-return FontInfoType
     */
    private function getFontFromTrueType(string $fontFile, bool $embed, array $map, bool $subset): array
    {
        $parser = new TTFParser($fontFile, $this->translator);
        $parser->parse();

        $font = $this->createEmptyFont();
        if ($embed) {
            if (!$parser->embeddable) {
                throw $this->translator->instance('error_license');
            }
            if ($subset) {
                $chars = [];
                foreach ($map as $v) {
                    if (self::NOT_DEF !== $v['name']) {
                        $chars[] = $v['uv'];
                    }
                }
                $parser->subset($chars);
                $font['Data'] = $parser->build();
            } else {
                $font['Data'] = (string) \file_get_contents($fontFile);
            }
            $font['OriginalSize'] = \strlen($font['Data']);
        }
        $font['FontName'] = $parser->postScriptName;
        $font['Bold'] = $parser->bold;
        $font['ItalicAngle'] = $parser->italicAngle;
        $font['IsFixedPitch'] = $parser->isFixedPitch;
        $font['Ascender'] = $parser->scale($parser->typoAscender);
        $font['Descender'] = $parser->scale($parser->typoDescender);
        $font['UnderlineThickness'] = $parser->scale($parser->underlineThickness);
        $font['UnderlinePosition'] = $parser->scale($parser->underlinePosition);
        $font['FontBBox'] = [
            $parser->scale($parser->xMin),
            $parser->scale($parser->yMin),
            $parser->scale($parser->xMax),
            $parser->scale($parser->yMax)];
        $font['CapHeight'] = $parser->scale($parser->capHeight);
        $font['MissingWidth'] = $parser->scale($parser->glyphs[0]['width']);
        $widths = \array_fill(0, 256, $font['MissingWidth']);
        foreach ($map as $index => $value) {
            if (self::NOT_DEF === $value['name']) {
                continue;
            }
            $uv = $value['uv'];
            if (isset($parser->chars[$uv])) {
                $id = $parser->chars[$uv];
                $width = $parser->glyphs[$id]['width'];
                $widths[$index] = $parser->scale($width);
            } else {
                $this->warning(\sprintf($this->trans('warning_character_missing'), $value['name']));
            }
        }
        $font['Widths'] = $widths;

        return $font;
    }

    /**
     * @phpstan-param array<int, MapType> $map
     *
     * @phpstan-return FontInfoType
     */
    private function getFontFromType1(string $fontFile, bool $embed, array $map): array
    {
        $font = $this->createEmptyFont();
        if ($embed) {
            $this->updateSegments($fontFile, $font);
        }

        $afmFile = \substr($fontFile, 0, -3) . 'afm';
        $cw = $this->parseAfmFile($afmFile, $font);

        if (!isset($font['FontName'])) {
            throw $this->translator->instance('error_font_name');
        }
        if (!isset($font['Ascender'])) {
            $font['Ascender'] = $font['FontBBox'][3];
        }
        if (!isset($font['Descender'])) {
            $font['Descender'] = $font['FontBBox'][1];
        }
        $font['Bold'] = isset($font['Weight']) && 1 === \preg_match('/bold|black/i', $font['Weight']);
        $font['MissingWidth'] = $cw[self::NOT_DEF] ?? 0;
        $widths = \array_fill(0, 256, $font['MissingWidth']);
        foreach ($map as $index => $value) {
            if (self::NOT_DEF === $value['name']) {
                continue;
            }
            if (isset($cw[$value['name']])) {
                $widths[$index] = $cw[$value['name']];
            } else {
                $this->warning(\sprintf($this->trans('warning_character_missing'), $value['name']));
            }
        }
        $font['Widths'] = $widths;

        return $font;
    }

    /**
     * @phpstan-return array<int, MapType>
     */
    private function loadMap(string $encoding): array
    {
        $fileName = \sprintf('%s/map/%s.map', __DIR__, \strtolower($encoding));
        $lines = $this->getFileLines($fileName);

        $map = \array_fill(0, 256, ['uv' => -1, 'name' => self::NOT_DEF]);
        foreach ($lines as $line) {
            $values = \explode(' ', $line);
            $key = (int) \hexdec(\substr($values[0], 1));
            $uv = (int) \hexdec(\substr($values[1], 2));
            $name = \rtrim($values[2]);
            $map[$key] = ['uv' => $uv, 'name' => $name];
        }

        return $map;
    }

    /**
     * @phpstan-param array<int, MapType> $map
     * @phpstan-param FontInfoType $font
     */
    private function makeDefinitionFile(
        string $file,
        string $type,
        string $encoding,
        bool $embed,
        bool $subset,
        array $map,
        array $font
    ): void {
        $output = "<?php\n";
        $output .= '$type = \'' . $type . "';\n";
        $output .= '$name = \'' . ($font['FontName'] ?? '') . "';\n";
        $output .= '$enc = \'' . $encoding . "';\n";
        $output .= '$up = ' . $font['UnderlinePosition'] . ";\n";
        $output .= '$ut = ' . $font['UnderlineThickness'] . ";\n";

        if ($embed) {
            $output .= '$file = \'' . $font['File'] . "';\n";
            if (self::FONT_TYPE_1 === $type) {
                $output .= '$size1 = ' . $font['Size1'] . ";\n";
                $output .= '$size2 = ' . $font['Size2'] . ";\n";
            } else {
                $output .= '$originalsize = ' . $font['OriginalSize'] . ";\n";
                if ($subset) {
                    $output .= "\$subsetted = true;\n";
                }
            }
        }

        $output .= '$desc = ' . $this->makeFontDescriptor($font) . ";\n";
        $output .= '$cw = ' . $this->makeWidthArray($font['Widths']) . ";\n";
        if (self::DEFAULT_ENCODING !== $encoding) {
            $diff = $this->makeFontEncoding($map);
            if ('' !== $diff) {
                $output .= '$diff = \'' . $diff . "';\n";
            }
        }
        $output .= '$uv = ' . $this->makeUnicodeArray($map) . ";\n";

        $this->saveToFile($file, $output, 't');
    }

    /**
     * @phpstan-param FontInfoType $font
     */
    private function makeFontDescriptor(array $font): string
    {
        // Ascent, Descent, and CapHeight
        $output = "[\n\t'Ascent' => " . ($font['Ascender'] ?? 0);
        $output .= ",\n\t'Descent' => " . ($font['Descender'] ?? 0);
        $output .= ",\n\t'CapHeight' => " . ($font['CapHeight'] ?? $font['Ascender'] ?? 0);

        // Flags
        $flags = 0;
        if ($font['IsFixedPitch']) {
            $flags += 1 << 0;
        }
        $flags += 1 << 5;
        if (0 !== $font['ItalicAngle']) {
            $flags += 1 << 6;
        }
        $output .= ",\n\t'Flags' => " . $flags;

        // FontBBox
        $output .= ",\n\t'FontBBox' => '[" . \implode(' ', $font['FontBBox']) . "]'";
        // ItalicAngle
        $output .= ",\n\t'ItalicAngle' => " . $font['ItalicAngle'];
        // StemV
        if (isset($font['StdVW'])) {
            $stemv = $font['StdVW'];
        } elseif ($font['Bold']) {
            $stemv = 120;
        } else {
            $stemv = 70;
        }
        $output .= ",\n\t'StemV' => " . $stemv;
        // MissingWidth
        $output .= ",\n\t'MissingWidth' => " . $font['MissingWidth'] . "\n]";

        return $output;
    }

    /**
     * Build differences from reference encoding.
     *
     * @phpstan-param array<int, MapType> $map
     */
    private function makeFontEncoding(array $map): string
    {
        $last = 0;
        $output = '';
        $source = $this->loadMap(self::DEFAULT_ENCODING);
        for ($ch = 32; $ch <= 255; ++$ch) {
            if ($map[$ch]['name'] !== $source[$ch]['name']) {
                if ($ch !== $last + 1) {
                    $output .= $ch . ' ';
                }
                $last = $ch;
                $output .= '/' . $map[$ch]['name'] . ' ';
            }
        }

        return \rtrim($output);
    }

    /**
     * @phpstan-param array<int, MapType> $map
     */
    private function makeUnicodeArray(array $map): string
    {
        /** @phpstan-var RangeType[] $ranges */
        $ranges = [];
        /** @phpstan-var RangeType|null $range */
        $range = null;
        foreach ($map as $c => $v) {
            $uv = $v['uv'];
            if (-1 === $uv) {
                continue;
            }
            if (null === $range) {
                $range = [$c, $c, $uv, $uv];
                continue;
            }
            if (($range[1] + 1) === $c && ($range[3] + 1) === $uv) {
                ++$range[1];
                ++$range[3];
            } else {
                $ranges[] = $range;
                $range = [$c, $c, $uv, $uv];
            }
        }

        if (null !== $range) {
            $ranges[] = $range;
        }

        $output = '';
        foreach ($ranges as $current) {
            if ('' !== $output) {
                $output .= ",\n\t";
            } else {
                $output = "[\n\t";
            }
            $output .= $current[0] . ' => ';
            $nb = $current[1] - $current[0] + 1;
            if ($nb > 1) {
                $output .= '[' . $current[2] . ', ' . $nb . ']';
            } else {
                $output .= $current[2];
            }
        }
        $output .= "\n]";

        return $output;
    }

    /**
     * @phpstan-param int[] $widths
     */
    private function makeWidthArray(array $widths): string
    {
        $output = "[\n\t";
        for ($cp = 0; $cp <= 255; ++$cp) {
            $output .= match (true) {
                // single quote and backslash characters
                39 === $cp, 92 === $cp => \sprintf("'%s'", \addslashes(\chr($cp))),
                //  ASCII-printable characters
                $cp >= 32 && $cp <= 126 => \sprintf("'%s'", \chr($cp)),
                // ASCII control characters and extended codes
                default => "\chr($cp)"
            };
            $output .= ' => ' . $widths[$cp];
            if ($cp < 255) {
                $output .= ",\n\t";
            }
        }
        $output .= "\n]";

        return $output;
    }

    private function message(string $message, string $severity = 'info'): void
    {
        $severity = $this->trans($severity);

        if (\PHP_SAPI === 'cli') {
            echo "$severity: $message\n";
        } else {
            echo "$severity: $message<br>";
        }
    }

    /**
     * @phpstan-param FontInfoType $font
     *
     * @phpstan-return array<string, int>
     */
    private function parseAfmFile(string $afmFile, array &$font): array
    {
        $cw = [];
        $lines = $this->getFileLines($afmFile);
        foreach ($lines as $line) {
            $values = \explode(' ', \rtrim($line));
            if (\count($values) < 2) {
                continue;
            }
            $entry = $values[0];
            switch ($entry) {
                case 'C':
                    $cw[$values[7]] = (int) $values[4];
                    break;
                case 'Weight':
                case 'FontName':
                    $font[$entry] = $values[1];
                    break;
                case 'Ascender':
                case 'Descender':
                case 'UnderlineThickness':
                case 'UnderlinePosition':
                case 'CapHeight':
                case 'StdVW':
                case 'ItalicAngle':
                    $font[$entry] = (int) $values[1];
                    break;
                case 'IsFixedPitch':
                    $font[$entry] = \filter_var($values[1], \FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'FontBBox':
                    $font[$entry] = [(int) $values[1], (int) $values[2], (int) $values[3], (int) $values[4]];
                    break;
            }
        }

        return $cw;
    }

    /**
     * @psalm-return positive-int
     */
    private function readSegment(FileHandler $handler): int
    {
        /** @phpstan-var array{marker: int, type: int, size: positive-int} $values */
        $values = $handler->unpack('Cmarker/Ctype/Vsize', 6);
        if (128 !== $values['marker']) {
            throw $this->translator->instance('error_invalid_type');
        }

        return $values['size'];
    }

    private function saveToFile(string $file, string $data, string $mode): void
    {
        $handler = new FileHandler($file, 'w' . $mode);
        $handler->write($data);
        $handler->close();
    }

    private function trans(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * @phpstan-param FontInfoType $font
     */
    private function updateSegments(string $fontFile, array &$font): void
    {
        $handler = new FileHandler($fontFile, 'r');

        // read the first segment
        $size1 = $this->readSegment($handler);
        $data1 = $handler->read($size1);

        // read the second segment
        $size2 = $this->readSegment($handler);
        $data2 = $handler->read($size2);

        $font['Data'] = $data1 . $data2;
        $font['Size1'] = $size1;
        $font['Size2'] = $size2;

        $handler->close();
    }

    private function warning(string $message): void
    {
        $this->message($message, 'warning');
    }
}
