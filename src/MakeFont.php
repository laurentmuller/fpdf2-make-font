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
/*******************************************************************************
 * Utility to generate font definition files                                    *
 *                                                                              *
 * Version: 1.31                                                                *
 * Date:    2019-12-07                                                          *
 * Author:  Olivier PLATHEY                                                     *
 *******************************************************************************/

namespace fpdf;

require __DIR__ . '/TTFParser.php';

/**
 * @psalm-type FontInfoType = array{
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
 *
 * @psalm-type MapType = array{uv: int, name: string}
 *
 * @psalm-type RangeType = array{0: int, 1: int, 2: int, 3: int}
 */
class MakeFont
{

    public function makeFont(
        string $fontFile,
        string $enc = 'cp1252',
        bool   $embed = true,
        bool   $subset = true
    ): void
    {
        // Generate a font definition file
        if (!\file_exists($fontFile)) {
            $this-> error('Font file not found: ' . $fontFile);
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
                $this-> error('Unrecognized font file extension: ' . $ext);
        }

        $map = $this->loadMap($enc);

        if ('TrueType' === $type) {
            $info =$this-> getInfoFromTrueType($fontFile, $embed, $subset, $map);
        } else {
            $info =$this-> getInfoFromType1($fontFile, $embed, $map);
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
     * @psalm-return FontInfoType
     */
    private function createEmptyFont():array
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
     * @psalm-param array<int, MapType> $map
     * @psalm-return FontInfoType
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
                    if ('.notdef' !== $v['name']) {
                        $chars[] = $v['uv'];
                    }
                }
                $ttf->subset($chars);
                $info['Data'] = $ttf->build();
            } else {
                $info['Data'] = (string)\file_get_contents($fontFile);
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
            if ('.notdef' !== $v['name']) {
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
     * @psalm-param array<int, MapType> $map
     * @psalm-return FontInfoType
     */
    private function getInfoFromType1(string $fontFile, bool $embed, array $map): array
    {
        $cw = [];
        $info = $this->createEmptyFont();
        if ($embed) {
            $handle = \fopen($fontFile, 'r');
            if (!\is_resource($handle)) {
                $this->error('Unable to open file: ' . $fontFile);
            }

            // Read the first segment
            /** @psalm-var array{marker: int, size: int} $lines */
            $lines = \unpack('Cmarker/Ctype/Vsize', (string) \fread($handle, 6));
            if (128 !== $lines['marker']) {
                \fclose($handle);
                $this-> error('Font file is not a valid binary Type1');
            }
            /** @psalm-var positive-int $size1 */
            $size1 = $lines['size'];
            $data = (string) \fread($handle, $size1);
            // Read the second segment
            /** @psalm-var array{marker: int, size: int} $lines */
            $lines = \unpack('Cmarker/Ctype/Vsize', (string) \fread($handle, 6));
            if (128 !== $lines['marker']) {
                \fclose($handle);
                $this->error('Font file is not a valid binary Type1');
            }
            /** @psalm-var positive-int $size2 */
            $size2 = $lines['size'];
            $data .= (string) \fread($handle, $size2);
            \fclose($handle);

            $info['Data'] = $data;
            $info['Size1'] = $size1;
            $info['Size2'] = $size2;
        }

        $afm = \substr($fontFile, 0, -3) . 'afm';
        if (!\file_exists($afm)) {
            $this->error('AFM font file not found: ' . $afm);
        }
        $lines = \file($afm);
        if (false === $lines || [] === $lines) {
            $this->error('AFM file empty or not readable');
        }
        foreach ($lines as $line) {
            $e = \explode(' ', \rtrim($line));
            if (\count($e) < 2) {
                continue;
            }
            $entry = $e[0];
            if ('C' === $entry) {
                $w = (int) $e[4];
                $name = $e[7];
                $cw[$name] = $w;
            } elseif ('FontName' === $entry) {
                $info['FontName'] = $e[1];
            } elseif ('Weight' === $entry) {
                $info['Weight'] = $e[1];
            } elseif ('ItalicAngle' === $entry) {
                $info['ItalicAngle'] = (int)$e[1];
            } elseif ('Ascender' === $entry) {
                $info['Ascender'] = (int)$e[1];
            } elseif ('Descender' === $entry) {
                $info['Descender'] = (int)$e[1];
            } elseif ('UnderlineThickness' === $entry) {
                $info['UnderlineThickness'] = (int)$e[1];
            } elseif ('UnderlinePosition' === $entry) {
                $info['UnderlinePosition'] = (int)$e[1];
            } elseif ('IsFixedPitch' === $entry) {
                $info['IsFixedPitch'] = ('true' === $e[1]);
            } elseif ('FontBBox' === $entry) {
                $info['FontBBox'] = [(int)$e[1], (int)$e[2], (int)$e[3], (int)$e[4]];
            } elseif ('CapHeight' === $entry) {
                $info['CapHeight'] = (int)$e[1];
            } elseif ('StdVW' === $entry) {
                $info['StdVW'] = (int)$e[1];
            }
        }

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
        $info['MissingWidth'] = isset($cw['.notdef']) ? $cw['.notdef'] : 0;
        $widths = \array_fill(0, 256, $info['MissingWidth']);
        foreach ($map as $c => $v) {
            if ('.notdef' !== $v['name']) {
                if (isset($cw[$v['name']])) {
                    $widths[$c] = $cw[$v['name']];
                } else {
                    $this-> warning('Character ' . $v['name'] . ' is missing');
                }
            }
        }
        $info['Widths'] = $widths;

        return $info;
    }

    /**
     * @psalm-return array<int, MapType>
     */
    private function loadMap(string $enc): array
    {
        $file = \sprintf('%s/map/%s.map', __DIR__, \strtolower($enc));
        $lines = \file($file);
        if (false === $lines || [] === $lines) {
            $this->error('Encoding not found: ' . $enc);
        }
        $map = \array_fill(0, 256, ['uv' => -1, 'name' => '.notdef']);
        foreach ($lines as $line) {
            $e = \explode(' ', \rtrim($line));
            $c = (int)\hexdec(\substr($e[0], 1));
            $uv = (int)\hexdec(\substr($e[1], 2));
            $name = $e[2];
            $map[$c] = ['uv' => $uv, 'name' => $name];
        }

        return $map;
    }

    /**
     * @psalm-param array<int, MapType> $map
     * @psalm-param FontInfoType $info
     */
    private function makeDefinitionFile(
        string $file,
        string $type,
        string $enc,
        bool   $embed,
        bool   $subset,
        array  $map,
        array  $info
    ): void
    {
        $s = "<?php\n";
        $s .= '$type = \'' . $type . "';\n";
        $s .= '$name = \'' . ($info['FontName'] ?? '') . "';\n";
        $s .= '$enc = \'' . $enc . "';\n";
        $s .= '$up = ' . $info['UnderlinePosition'] . ";\n";
        $s .= '$ut = ' . $info['UnderlineThickness'] . ";\n";

        if ($embed) {
            $s .= '$file = \'' . $info['File'] . "';\n";
            if ('Type1' === $type) {
                $s .= '$size1 = ' . $info['Size1'] . ";\n";
                $s .= '$size2 = ' . $info['Size2'] . ";\n";
            } else {
                $s .= '$originalsize = ' . $info['OriginalSize'] . ";\n";
                if ($subset) {
                    $s .= "\$subsetted = true;\n";
                }
            }
        }

        $s .= '$desc = ' . $this->makeFontDescriptor($info) . ";\n";
        $s .= '$cw = ' . $this->makeWidthArray($info['Widths']) . ";\n";
        $diff = $this->makeFontEncoding($map);
        if ('' !== $diff) {
            $s .= '$diff = \'' . $diff . "';\n";
        }
        $s .= '$uv = ' . $this->makeUnicodeArray($map) . ";\n";

        $this->saveToFile($file, $s, 't');
    }

    /**
     * @psalm-param FontInfoType $info
     */
    private function makeFontDescriptor(array $info): string
    {
        // Ascent
        $fd = "[\n\t'Ascent' => " . ($info['Ascender'] ?? 0);
        // Descent
        $fd .= ",\n\t'Descent' => " . ($info['Descender'] ?? 0);
        // CapHeight
        $fd .= ",\n\t'CapHeight' => " . ($info['CapHeight'] ?? $info['Ascender'] ?? 0);

        // Flags
        $flags = 0;
        if ($info['IsFixedPitch']) {
            $flags += 1 << 0;
        }
        $flags += 1 << 5;
        if (0 !== $info['ItalicAngle']) {
            $flags += 1 << 6;
        }
        $fd .= ",\n\t'Flags' => " . $flags;
        // FontBBox
        $fd .= ",\n\t'FontBBox' => '[" . \implode(' ', $info['FontBBox']) . "]'";
        // ItalicAngle
        $fd .= ",\n\t'ItalicAngle' => " . $info['ItalicAngle'];
        // StemV
        if (isset($info['StdVW'])) {
            $stemv = $info['StdVW'];
        } elseif ($info['Bold']) {
            $stemv = 120;
        } else {
            $stemv = 70;
        }
        $fd .= ",\n\t'StemV' => " . $stemv;
        // MissingWidth
        $fd .= ",\n\t'MissingWidth' => " . $info['MissingWidth'] . "\n]";

        return $fd;
    }

    /**
     * @psalm-param array<int, MapType> $map
     */
    private function makeFontEncoding(array $map): string
    {
        // Build differences from reference encoding
        $ref = $this->loadMap('cp1252');
        $s = '';
        $last = 0;
        for ($c = 32; $c <= 255; ++$c) {
            if ($map[$c]['name'] !== $ref[$c]['name']) {
                if ($c !== $last + 1) {
                    $s .= $c . ' ';
                }
                $last = $c;
                $s .= '/' . $map[$c]['name'] . ' ';
            }
        }

        return \rtrim($s);
    }

    /**
     * @psalm-param array<int, MapType> $map
     */
    private function makeUnicodeArray(array $map): string
    {
        /** @psalm-var RangeType[] $ranges */
        $ranges = [];
        /** @psalm-var RangeType|null $range */
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

        $s = '';
        foreach ($ranges as $current) {
            if ('' !== $s) {
                $s .= ",\n\t";
            } else {
                $s = "[\n\t";
            }
            $s .= $current[0] . ' => ';
            $nb = $current[1] - $current[0] + 1;
            if ($nb > 1) {
                $s .= "[" . $current[2] . ', ' . $nb . "]";
            } else {
                $s .= $current[2];
            }
        }
        $s .= "\n]";

        return $s;
    }

    /**
     * @psalm-param int[] $widths
     */
    private function makeWidthArray(array $widths): string
    {
        $s = "[\n\t";
        for ($c = 0; $c <= 255; ++$c) {
            if ("'" === \chr($c)) {
                $s .= "'\\''";
            } elseif ('\\' === \chr($c)) {
                $s .= "'\\\\'";
            } elseif ($c >= 32 && $c <= 126) {
                $s .= "'" . \chr($c) . "'";
            } else {
                $s .= "chr($c)";
            }
            $s .= ' => ' . $widths[$c];
            if ($c < 255) {
                $s .= ",\n\t";
            }
//            if (($c + 1) % 22 === 0) {
//                $s .= "\n\t";
//            }
        }
        $s .= "\n]";

        return $s;
    }


    private function message(string $message, string $severity = ''): void
    {
        if (\PHP_SAPI === 'cli') {
            if ('' !== $severity) {
                echo "$severity: ";
            }
            echo "$message\n";
        } else {
            if ('' !== $severity) {
                echo "<b>$severity</b>: ";
            }
            echo "$message<br>";
        }
    }

    private function round(float $factor, float $value):int
    {
        return (int)\round($factor * $value)    ;
    }

    private function saveToFile(string $file, string $data, string $mode): void
    {
        $handle = \fopen($file, 'w' . $mode);
        if (!\is_resource($handle)) {
            $this-> error('Unable to open file: ' . $file);
        }
        \fwrite($handle, $data);
        \fclose($handle);
    }

    private function warning(string $message): void
    {
        $this->message($message, 'Warning');
    }
}
