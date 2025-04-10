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
 * @phpstan-type  FontInfoType = array{
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
    private const NOT_DEF = '.notdef';

    public function makeFont(string $fontFile, string $enc = 'cp1252', bool $embed = true, bool $subset = true): void
    {
        if (!\file_exists($fontFile)) {
            $this->error('Font file not found: ' . $fontFile);
        }
        $ext = \strtolower(\substr($fontFile, -3));

        $type = '';
        switch ($ext) {
            case 'ttf':
            case 'otf':
                $type = 'TrueType';
                break;
            case 'pfb':
                $type = 'Type1';
                break;
            default:
                $this->error('Unrecognized font file extension: ' . $ext);
        }

        $map = $this->loadMap($enc);

        if ('TrueType' === $type) {
            $info = $this->getInfoFromTrueType($fontFile, $embed, $subset, $map);
        } else {
            $info = $this->getInfoFromType1($fontFile, $embed, $map);
        }

        $basename = \substr(\basename($fontFile), 0, -4);
        if ($embed) {
            if (\function_exists('gzcompress')) {
                $file = $basename . '.z';
                $this->saveToFile($file, (string) \gzcompress($info['Data']), 'b');
                $info['File'] = $file;
                $this->message('Font file compressed: ' . $file);
            } else {
                $info['File'] = \basename($fontFile);
                $subset = false;
                $this->warning('Font file could not be compressed (zlib extension not available)');
            }
        }

        $this->makeDefinitionFile($basename . '.php', $type, $enc, $embed, $subset, $map, $info);
        $this->message('Font definition file generated: ' . $basename . '.php');
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
            'Ascender' => 0,
            'Descender' => 0,
            'UnderlineThickness' => 0,
            'UnderlinePosition' => 0,
            'FontBBox' => [],
            'MissingWidth' => 0,
            'Size1' => 0,
            'Size2' => 0,
            'Widths' => [],
        ];
    }

    private function error(string $message): never
    {
        $this->message($message, 'Error');
        exit(1);
    }

    /**
     * @phpstan-param array<int, MapType> $map
     *
     * @phpstan-return FontInfoType
     */
    private function getInfoFromTrueType(string $fontFile, bool $embed, bool $subset, array $map): array
    {
        // Return information from a TrueType font
        try {
            $ttf = new TTFParser($fontFile);
            $ttf->parse();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $info = $this->createEmptyFont();
        if ($embed) {
            if (!$ttf->embeddable) {
                $this->error('Font license does not allow embedding');
            }
            if ($subset) {
                $chars = [];
                foreach ($map as $v) {
                    if (self::NOT_DEF !== $v['name']) {
                        $chars[] = $v['uv'];
                    }
                }
                $ttf->subset($chars);
                $info['Data'] = $ttf->build();
            } else {
                $info['Data'] = (string) \file_get_contents($fontFile);
            }
            $info['OriginalSize'] = \strlen($info['Data']);
        }
        $k = 1000 / $ttf->unitsPerEm;
        $info['FontName'] = $ttf->postScriptName;
        $info['Bold'] = $ttf->bold;
        $info['ItalicAngle'] = $ttf->italicAngle;
        $info['IsFixedPitch'] = $ttf->isFixedPitch;
        $info['Ascender'] = $this->round($k, $ttf->typoAscender);
        $info['Descender'] = $this->round($k, $ttf->typoDescender);
        $info['UnderlineThickness'] = $this->round($k, $ttf->underlineThickness);
        $info['UnderlinePosition'] = $this->round($k, $ttf->underlinePosition);
        $info['FontBBox'] = [
            $this->round($k, $ttf->xMin),
            $this->round($k, $ttf->yMin),
            $this->round($k, $ttf->xMax),
            $this->round($k, $ttf->yMax)];
        $info['CapHeight'] = $this->round($k, $ttf->capHeight);
        $info['MissingWidth'] = $this->round($k, $ttf->glyphs[0]['w']);
        $widths = \array_fill(0, 256, $info['MissingWidth']);
        foreach ($map as $c => $v) {
            if (self::NOT_DEF !== $v['name']) {
                if (isset($ttf->chars[$v['uv']])) {
                    $id = $ttf->chars[$v['uv']];
                    $w = $ttf->glyphs[$id]['w'];
                    $widths[$c] = $this->round($k, $w);
                } else {
                    $this->warning('Character ' . $v['name'] . ' is missing');
                }
            }
        }
        $info['Widths'] = $widths;

        return $info;
    }

    /**
     * @phpstan-param array<int, MapType> $map
     *
     * @phpstan-return FontInfoType
     */
    private function getInfoFromType1(string $fontFile, bool $embed, array $map): array
    {
        $info = $this->createEmptyFont();
        if ($embed) {
            $this->updateSegments($fontFile, $info);
        }

        $afmFile = \substr($fontFile, 0, -3) . 'afm';
        $cw = $this->parseAfmFile($afmFile, $info);

        if (!isset($info['FontName'])) {
            $this->error('FontName missing in AFM file');
        }
        if (!isset($info['Ascender'])) {
            $info['Ascender'] = $info['FontBBox'][3];
        }
        if (!isset($info['Descender'])) {
            $info['Descender'] = $info['FontBBox'][1];
        }
        $info['Bold'] = isset($info['Weight']) && 1 === \preg_match('/bold|black/i', $info['Weight']);
        $info['MissingWidth'] = $cw[self::NOT_DEF] ?? 0;
        $widths = \array_fill(0, 256, $info['MissingWidth']);
        foreach ($map as $c => $v) {
            if (self::NOT_DEF !== $v['name']) {
                if (isset($cw[$v['name']])) {
                    $widths[$c] = $cw[$v['name']];
                } else {
                    $this->warning('Character ' . $v['name'] . ' is missing');
                }
            }
        }
        $info['Widths'] = $widths;

        return $info;
    }

    /**
     * @phpstan-return array<int, MapType>
     */
    private function loadMap(string $enc): array
    {
        $file = \sprintf('%s/map/%s.map', __DIR__, \strtolower($enc));
        $lines = \file($file);
        if (false === $lines || [] === $lines) {
            $this->error('Encoding not found: ' . $enc);
        }
        $map = \array_fill(0, 256, ['uv' => -1, 'name' => self::NOT_DEF]);
        foreach ($lines as $line) {
            $e = \explode(' ', \rtrim($line));
            $c = (int) \hexdec(\substr($e[0], 1));
            $uv = (int) \hexdec(\substr($e[1], 2));
            $name = $e[2];
            $map[$c] = ['uv' => $uv, 'name' => $name];
        }

        return $map;
    }

    /**
     * @phpstan-param array<int, MapType> $map
     * @phpstan-param FontInfoType $info
     */
    private function makeDefinitionFile(
        string $file,
        string $type,
        string $enc,
        bool $embed,
        bool $subset,
        array $map,
        array $info
    ): void {
        $output = "<?php\n";
        $output .= '$type = \'' . $type . "';\n";
        $output .= '$name = \'' . ($info['FontName'] ?? '') . "';\n";
        $output .= '$enc = \'' . $enc . "';\n";
        $output .= '$up = ' . $info['UnderlinePosition'] . ";\n";
        $output .= '$ut = ' . $info['UnderlineThickness'] . ";\n";

        if ($embed) {
            $output .= '$file = \'' . $info['File'] . "';\n";
            if ('Type1' === $type) {
                $output .= '$size1 = ' . $info['Size1'] . ";\n";
                $output .= '$size2 = ' . $info['Size2'] . ";\n";
            } else {
                $output .= '$originalsize = ' . $info['OriginalSize'] . ";\n";
                if ($subset) {
                    $output .= "\$subsetted = true;\n";
                }
            }
        }

        $output .= '$desc = ' . $this->makeFontDescriptor($info) . ";\n";
        $output .= '$cw = ' . $this->makeWidthArray($info['Widths']) . ";\n";
        $diff = $this->makeFontEncoding($map);
        if ('' !== $diff) {
            $output .= '$diff = \'' . $diff . "';\n";
        }
        $output .= '$uv = ' . $this->makeUnicodeArray($map) . ";\n";

        $this->saveToFile($file, $output, 't');
    }

    /**
     * @phpstan-param FontInfoType $info
     */
    private function makeFontDescriptor(array $info): string
    {
        // Ascent
        $output = "[\n\t'Ascent' => " . ($info['Ascender'] ?? 0);
        // Descent
        $output .= ",\n\t'Descent' => " . ($info['Descender'] ?? 0);
        // CapHeight
        $output .= ",\n\t'CapHeight' => " . ($info['CapHeight'] ?? $info['Ascender'] ?? 0);

        // Flags
        $flags = 0;
        if ($info['IsFixedPitch']) {
            $flags += 1 << 0;
        }
        $flags += 1 << 5;
        if (0 !== $info['ItalicAngle']) {
            $flags += 1 << 6;
        }
        $output .= ",\n\t'Flags' => " . $flags;
        // FontBBox
        $output .= ",\n\t'FontBBox' => '[" . \implode(' ', $info['FontBBox']) . "]'";
        // ItalicAngle
        $output .= ",\n\t'ItalicAngle' => " . $info['ItalicAngle'];
        // StemV
        if (isset($info['StdVW'])) {
            $stemv = $info['StdVW'];
        } elseif ($info['Bold']) {
            $stemv = 120;
        } else {
            $stemv = 70;
        }
        $output .= ",\n\t'StemV' => " . $stemv;
        // MissingWidth
        $output .= ",\n\t'MissingWidth' => " . $info['MissingWidth'] . "\n]";

        return $output;
    }

    /**
     * Build differences from reference encoding.
     *
     * @phpstan-param array<int, MapType> $map
     */
    private function makeFontEncoding(array $map): string
    {
        $ref = $this->loadMap('cp1252');
        $output = '';
        $last = 0;
        for ($c = 32; $c <= 255; ++$c) {
            if ($map[$c]['name'] !== $ref[$c]['name']) {
                if ($c !== $last + 1) {
                    $output .= $c . ' ';
                }
                $last = $c;
                $output .= '/' . $map[$c]['name'] . ' ';
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
        for ($ch = 0; $ch <= 255; ++$ch) {
            if ("'" === \chr($ch)) {
                $output .= "'\\''";
            } elseif ('\\' === \chr($ch)) {
                $output .= "'\\\\'";
            } elseif ($ch >= 32 && $ch <= 126) {
                $output .= "'" . \chr($ch) . "'";
            } else {
                $output .= "chr($ch)";
            }
            $output .= ' => ' . $widths[$ch];
            if ($ch < 255) {
                $output .= ",\n\t";
            }
        }
        $output .= "\n]";

        return $output;
    }

    private function message(string $message, string $severity = 'Info'): void
    {
        if (\PHP_SAPI === 'cli') {
            echo "$severity: $message\n";
        } else {
            echo "<b>$severity</b>: $message<br>";
        }
    }

    /**
     * @phpstan-param FontInfoType $info
     *
     * @phpstan-return array<string, int>
     */
    private function parseAfmFile(string $afmFile, array &$info): array
    {
        if (!\file_exists($afmFile)) {
            $this->error('AFM font file not found: ' . $afmFile);
        }
        $lines = \file($afmFile);
        if (false === $lines || [] === $lines) {
            $this->error('AFM font file empty or not readable: ' . $afmFile);
        }

        $cw = [];
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
                    $info[$entry] = $values[1];
                    break;
                case 'Ascender':
                case 'Descender':
                case 'UnderlineThickness':
                case 'UnderlinePosition':
                case 'CapHeight':
                case 'StdVW':
                case 'ItalicAngle':
                    $info[$entry] = (int) $values[1];
                    break;
                case 'IsFixedPitch':
                    $info[$entry] = (bool)($values[1]);
                    break;
                case 'FontBBox':
                    $info[$entry] = [(int) $values[1], (int) $values[2], (int) $values[3], (int) $values[4]];
                    break;
            }
        }

        return $cw;
    }

    /**
     * @param resource $handle
     *
     * @psalm-return positive-int
     */
    private function readSegment(mixed $handle): int
    {
        /** @phpstan-var array{marker: int, size: positive-int} $lines */
        $lines = \unpack('Cmarker/Ctype/Vsize', (string) \fread($handle, 6));
        if (128 !== $lines['marker']) {
            \fclose($handle);
            $this->error('Font file is not a valid binary Type1');
        }

        return $lines['size'];
    }

    private function round(float $factor, float $value): int
    {
        return (int) \round($factor * $value);
    }

    private function saveToFile(string $file, string $data, string $mode): void
    {
        $handle = \fopen($file, 'w' . $mode);
        if (!\is_resource($handle)) {
            $this->error('Unable to open file: ' . $file);
        }
        \fwrite($handle, $data);
        \fclose($handle);
    }

    /**
     * @phpstan-param FontInfoType $info
     */
    private function updateSegments(string $fontFile, array &$info): void
    {
        $handle = \fopen($fontFile, 'r');
        if (!\is_resource($handle)) {
            $this->error('Unable to open file: ' . $fontFile);
        }

        // read the first segment
        $size1 = $this->readSegment($handle);
        $data = (string) \fread($handle, $size1);

        // read the second segment
        $size2 = $this->readSegment($handle);
        $data .= (string) \fread($handle, $size2);

        $info['Data'] = $data;
        $info['Size1'] = $size1;
        $info['Size2'] = $size2;

        \fclose($handle);
    }

    private function warning(string $message): void
    {
        $this->message($message, 'Warning');
    }
}
