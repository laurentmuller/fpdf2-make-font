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

class FontMaker
{
    /** The default encoding ('cp1252'). */
    public const string DEFAULT_ENCODING = 'cp1252';

    private const string FONT_TRUE_TYPE = 'TrueType';
    private const string FONT_TYPE_1 = 'Type1';

    /** @var Log[] */
    private array $logs = [];
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

    /**
     * Gets the log entries.
     *
     * @return Log[]
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    public function makeFont(
        string $fontFile,
        string $encoding = self::DEFAULT_ENCODING,
        bool $embed = true,
        bool $subset = true
    ): void {
        $this->logs = [];
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
            $data = (string) \gzcompress($font->data);
            $this->saveToFile($compressedFile, $data, 'b');
            $font->file = $compressedFile;
            $this->message(\sprintf($this->trans('info_compressed_generated'), $compressedFile));
        }

        $jsonFile = $baseName . '.json';
        $this->makeDefinitionFile($jsonFile, $type, $encoding, $embed, $subset, $map, $font);

        $this->message(\sprintf($this->trans('info_file_generated'), $jsonFile));
    }

    public function setLocale(string $locale = Translator::DEFAULT_LOCALE): self
    {
        if ($locale !== $this->getLocale() && Translator::isAllowedLocale($locale)) {
            $this->translator = new Translator($locale);
        }

        return $this;
    }

    /**
     * @param array<int, MapEntry> $map
     */
    private function getFontFromTrueType(string $fontFile, bool $embed, array $map, bool $subset): FontInfo
    {
        $parser = new TTFParser(file: $fontFile, translator: $this->translator);
        $parser->parse();

        $font = new FontInfo();
        if ($embed) {
            if (!$parser->embeddable) {
                throw $this->translator->instance('error_license');
            }
            if ($subset) {
                $chars = [];
                foreach ($map as $entry) {
                    if ($entry->isName()) {
                        $chars[] = $entry->uv;
                    }
                }
                $parser->subset($chars);
                $font->data = $parser->build();
            } else {
                $font->data = (string) \file_get_contents($fontFile);
            }
            $font->originalSize = $font->getDataLength();
        }
        $font->fontName = $parser->postScriptName;
        $font->bold = $parser->bold;
        $font->italicAngle = $parser->italicAngle;
        $font->fixedPitch = $parser->isFixedPitch;
        $font->ascender = $parser->getAscender();
        $font->descender = $parser->getDescender();
        $font->underlineThickness = $parser->getUnderlineThickness();
        $font->underlinePosition = $parser->getUnderlinePosition();
        $font->fontBBox = $parser->getBBox();
        $font->capHeight = $parser->getCapHeight();
        $font->missingWidth = $parser->getMissingWidth();
        $widths = \array_fill(0, 256, $font->missingWidth);
        foreach ($map as $index => $value) {
            if (!$value->isName()) {
                continue;
            }
            $uv = $value->uv;
            if ($parser->isCharSet($uv)) {
                $id = $parser->chars[$uv];
                $widths[$index] = $parser->getGlyphWidth($id);
            } else {
                $this->warning(\sprintf($this->trans('warning_character_missing'), $value->name));
            }
        }
        $font->widths = $widths;

        return $font;
    }

    /**
     * @param array<int, MapEntry> $map
     */
    private function getFontFromType1(string $fontFile, bool $embed, array $map): FontInfo
    {
        $font = new FontInfo();
        if ($embed) {
            $reader = new SegmentReader(file: $fontFile, translator: $this->translator);
            $reader->updateFont($font);
            $reader->close();
        }

        $afmFile = \substr($fontFile, 0, -3) . 'afm';
        $cw = $this->parseAfmFile($afmFile, $font);

        if (!isset($font->fontName)) {
            throw $this->translator->instance('error_font_name');
        }
        if (!$font->isAscender()) {
            $font->ascender = $font->fontBBox[3];
        }
        if (!$font->isDescender()) {
            $font->descender = $font->fontBBox[1];
        }
        $font->bold = $font->isWeight() && 1 === \preg_match('/bold|black/i', $font->weight);
        $font->missingWidth = $cw[MapEntry::NOT_DEF] ?? 0;
        $widths = \array_fill(0, 256, $font->missingWidth);
        foreach ($map as $index => $value) {
            if (!$value->isName()) {
                continue;
            }
            if (!isset($cw[$value->name])) {
                $this->warning(\sprintf($this->trans('warning_character_missing'), $value->name));
                continue;
            }
            $widths[$index] = $cw[$value->name];
        }
        $font->widths = $widths;

        return $font;
    }

    /**
     * @return array<int, MapEntry>
     */
    private function loadMap(string $encoding): array
    {
        $loader = new MapLoader($this->translator);

        return $loader->getFileMap($encoding);
    }

    /**
     * @param array<int, MapEntry> $map
     */
    private function makeDefinitionFile(
        string $file,
        string $type,
        string $encoding,
        bool $embed,
        bool $subset,
        array $map,
        FontInfo $font
    ): void {
        $data = [
            'type' => $type,
            'name' => $font->getFontName(),
            'enc' => $encoding,
            'up' => $font->underlinePosition,
            'ut' => $font->underlineThickness,
        ];

        if ($embed) {
            $data['file'] = $font->file;
            if (self::FONT_TYPE_1 === $type) {
                $data['size1'] = $font->size1;
                $data['size2'] = $font->size2;
            } else {
                $data['originalsize'] = $font->originalSize;
                if ($subset) {
                    $data['subsetted'] = true;
                }
            }
        }

        $data['desc'] = $this->makeFontDescriptor($font);
        $data['cw'] = $font->widths;
        $data['uv'] = $this->makeUnicode($map);
        if (self::DEFAULT_ENCODING !== $encoding) {
            $diff = $this->makeFontEncoding($map);
            if ('' !== $diff) {
                $data['diff'] = $diff;
            }
        }

        /** @phpstan-var non-empty-string $output */
        $output = \json_encode($data, \JSON_FORCE_OBJECT | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        $this->saveToFile($file, $output);
    }

    /**
     * @return array<string, int|int[]>
     */
    private function makeFontDescriptor(FontInfo $font): array
    {
        // Flags
        $flags = 0;
        if ($font->fixedPitch) {
            $flags += 1 << 0;
        }
        $flags += 1 << 5;
        if ($font->isItalicAngle()) {
            $flags += 1 << 6;
        }

        // StemV
        if ($font->isStdVW()) {
            $stemv = $font->stdVW;
        } elseif ($font->bold) {
            $stemv = 120;
        } else {
            $stemv = 70;
        }

        return [
            'Flags' => $flags,
            'Ascent' => $font->getAscender(),
            'Descent' => $font->getDescender(),
            'CapHeight' => $font->getCapHeight(),
            'ItalicAngle' => $font->italicAngle,
            'MissingWidth' => $font->missingWidth,
            'StemV' => $stemv,
            'FontBBox' => $font->fontBBox,
        ];
    }

    /**
     * Build differences from reference encoding.
     *
     * @param array<int, MapEntry> $map
     */
    private function makeFontEncoding(array $map): string
    {
        $last = 0;
        $output = '';
        $source = $this->loadMap(self::DEFAULT_ENCODING);
        for ($ch = 32; $ch <= 255; ++$ch) {
            if ($map[$ch]->name === $source[$ch]->name) {
                continue;
            }
            if ($ch !== $last + 1) {
                $output .= \sprintf('%d ', $ch);
            }
            $output .= \sprintf('/%s ', $map[$ch]->name);
            $last = $ch;
        }

        return \rtrim($output);
    }

    /**
     * @param array<int, MapEntry> $map
     *
     * @return array<int, int|int[]>
     */
    private function makeUnicode(array $map): array
    {
        /** @var int[][] $ranges */
        $ranges = [];
        /** @var int[]|null $range */
        $range = null;
        foreach ($map as $c => $entry) {
            if (!$entry->isUv()) {
                continue;
            }
            $uv = $entry->uv;
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

        $data = [];
        foreach ($ranges as $current) {
            $nb = $current[1] - $current[0] + 1;
            $data[$current[0]] = $nb > 1 ? [$current[2], $nb] : $current[2];
        }

        return $data;
    }

    private function message(string $message, LogLevel $level = LogLevel::INFO): void
    {
        $this->logs[] = new Log($message, $level);
    }

    /**
     * @return array<string, int>
     */
    private function parseAfmFile(string $afmFile, FontInfo $font): array
    {
        $cw = [];
        $loader = new MapLoader($this->translator);
        $lines = $loader->getFileLines($afmFile);
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
                    $font->fontBBox = [(int) $values[1], (int) $values[2], (int) $values[3], (int) $values[4]];
                    break;
            }
        }

        return $cw;
    }

    private function saveToFile(string $file, string $data, string $mode = ''): void
    {
        $handler = new FileWriter($file, $mode);
        $handler->write($data);
        $handler->close();
    }

    private function trans(string $key): string
    {
        return $this->translator->get($key);
    }

    private function warning(string $message): void
    {
        $this->message($message, LogLevel::WARNING);
    }
}
