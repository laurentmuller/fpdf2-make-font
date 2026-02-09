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
 * Class to parse a TTF font.
 *
 * @phpstan-type GlyphType = array{
 *    name: string|int,
 *    width: int,
 *    lsb: int,
 *    length: int,
 *    offset: int,
 *    ssid: int,
 *    components?: array<int, int>}
 * @phpstan-type TableType = array{
 *    offset: int,
 *    length: int,
 *    data: string,
 *    checkSum: string}
 * @phpstan-type CmapType = array{
 *    0: int[],
 *    1: int[],
 *    2: int[],
 *    3: int[],
 *    4: string}
 * @phpstan-type CmapSegmentType = array<int, int[]>
 */
class TTFParser extends FileReader
{
    private const string TAG_CMAP = 'cmap';
    private const string TAG_CVT = 'cvt';
    private const string TAG_FPGM = 'fpgm';
    private const string TAG_GLYF = 'glyf';
    private const string TAG_HEAD = 'head';
    private const string TAG_HHEA = 'hhea';
    private const string TAG_HMTX = 'hmtx';
    private const string TAG_LOCA = 'loca';
    private const string TAG_MAXP = 'maxp';
    private const string TAG_NAME = 'name';
    private const string TAG_OS2 = 'OS/2';
    private const string TAG_POST = 'post';
    private const string TAG_PREP = 'prep';

    private const array TAGS_NAME = [
        self::TAG_CMAP,
        self::TAG_CVT,
        self::TAG_FPGM,
        self::TAG_GLYF,
        self::TAG_HEAD,
        self::TAG_HHEA,
        self::TAG_HMTX,
        self::TAG_LOCA,
        self::TAG_MAXP,
        self::TAG_NAME,
        self::TAG_POST,
        self::TAG_PREP,
    ];

    public bool $bold = false;
    public int $capHeight = 0;
    /** @phpstan-var array<int, int>  */
    public array $chars = [];
    public bool $embeddable = false;
    /** @phpstan-var array<int, GlyphType> */
    public array $glyphs = [];
    public bool $isFixedPitch = false;
    public int $italicAngle = 0;
    public string $postScriptName = '';
    public int $typoAscender = 0;
    public int $typoDescender = 0;
    public int $underlinePosition = 0;
    public int $underlineThickness = 0;
    public float $unitsPerEm = 1000.0;
    public int $xMax = 0;
    public int $xMin = 0;
    public int $yMax = 0;
    public int $yMin = 0;
    private bool $glyphNames = false;
    private int $indexToLocFormat = 0;
    private int $numberOfHMetrics = 0;
    private int $numGlyphs = 0;
    /** @phpstan-var array<int, int>  */
    private array $subsettedChars = [];
    /** @phpstan-var array<int, int>  */
    private array $subsettedGlyphs = [];
    /** @phpstan-var array<self::TAG_*, TableType> */
    private array $tables = [];

    /**
     * Build the font definition.
     */
    public function build(): string
    {
        $this->buildCmap();
        $this->buildHhea();
        $this->buildHmtx();
        $this->buildLoca();
        $this->buildGlyf();
        $this->buildMaxp();
        $this->buildPost();

        return $this->buildFont();
    }

    /**
     * Parse the font file.
     */
    public function parse(): void
    {
        $this->parseOffsetTable();
        $this->parseHead();
        $this->parseHhea();
        $this->parseMaxp();
        $this->parseHmtx();
        $this->parseLoca();
        $this->parseGlyf();
        $this->parseCmap();
        $this->parseName();
        $this->parseOS2();
        $this->parsePost();
    }

    /**
     * Scale a value from the font units to the user units.
     */
    public function scale(float $value): int
    {
        return (int) \round($value * 1000.0 / $this->unitsPerEm);
    }

    /**
     * @phpstan-param array<int, int> $chars
     */
    public function subset(array $chars): void
    {
        $this->subsettedGlyphs = [];
        $this->subsettedChars = [];
        $this->addGlyph(0);
        foreach ($chars as $char) {
            if (isset($this->chars[$char])) {
                $this->subsettedChars[] = $char;
                $this->addGlyph($this->chars[$char]);
            }
        }
    }

    private function addGlyph(int $id): void
    {
        $glyph = &$this->glyphs[$id];
        if (0 !== $glyph['ssid']) {
            return;
        }
        $glyph['ssid'] = $this->getSubsettedGlyphsCount();
        $this->subsettedGlyphs[] = $id;
        if (!isset($glyph['components'])) {
            return;
        }
        foreach ($glyph['components'] as $cid) {
            $this->addGlyph($cid);
        }
    }

    private function buildCmap(): void
    {
        if ([] === $this->subsettedChars) {
            return;
        }

        // divide charset in contiguous segments
        $segments = $this->buildCmapSegments();
        [$startCount, $endCount, $idDelta, $idRangeOffset, $glyphIdArray] = $this->buildCmapFormat($segments);
        $segmentsCount = \count($segments);

        $entrySelector = 0;
        $n = $segmentsCount;
        while (1 !== $n) {
            $n >>= 1;
            ++$entrySelector;
        }
        $searchRange = (1 << $entrySelector) * 2;
        $rangeShift = 2 * $segmentsCount - $searchRange;
        $cmap = \pack('nnnn', 2 * $segmentsCount, $searchRange, $entrySelector, $rangeShift);
        foreach ($endCount as $value) {
            $cmap .= \pack('n', $value);
        }
        $cmap .= \pack('n', 0); // reservedPad
        foreach ($startCount as $value) {
            $cmap .= \pack('n', $value);
        }
        foreach ($idDelta as $value) {
            $cmap .= \pack('n', $value);
        }
        foreach ($idRangeOffset as $value) {
            $cmap .= \pack('n', $value);
        }
        $cmap .= $glyphIdArray;

        $data = \pack('nn', 0, 1); // version, numTables
        $data .= \pack('nnN', 3, 1, 12); // platformID, encodingID, offset
        $data .= \pack('nnn', 4, 6 + \strlen($cmap), 0); // format, length, language
        $data .= $cmap;
        $this->setTable(self::TAG_CMAP, $data);
    }

    /**
     * @phpstan-param CmapSegmentType $segments
     *
     * @phpstan-return CmapType
     */
    private function buildCmapFormat(array $segments): array
    {
        $startCount = [];
        $endCount = [];
        $idDelta = [];
        $idRangeOffset = [];
        $glyphIdArray = '';
        $segmentsCount = \count($segments);
        for ($i = 0; $i < $segmentsCount; ++$i) {
            [$start, $end] = $segments[$i];
            $startCount[] = $start;
            $endCount[] = $end;
            if ($start !== $end) {
                // segment with multiple chars
                $idDelta[] = 0;
                $idRangeOffset[] = \strlen($glyphIdArray) + ($segmentsCount - $i) * 2;
                for ($c = $start; $c <= $end; ++$c) {
                    $ssid = $this->glyphs[$this->chars[$c]]['ssid'];
                    $glyphIdArray .= \pack('n', $ssid);
                }
                continue;
            }
            // segment with a single char
            $ssid = $start < 0xFFFF ? $this->glyphs[$this->chars[$start]]['ssid'] : 0;
            $idDelta[] = $ssid - $start;
            $idRangeOffset[] = 0;
        }

        return [
            $startCount,
            $endCount,
            $idDelta,
            $idRangeOffset,
            $glyphIdArray,
        ];
    }

    /**
     * @phpstan-return CmapSegmentType
     */
    private function buildCmapSegments(): array
    {
        $chars = $this->subsettedChars;
        \sort($chars);
        $segments = [];
        $segment = [$chars[0], $chars[0]];
        for ($i = 1, $charsCount = \count($chars); $i < $charsCount; ++$i) {
            if ($chars[$i] > $segment[1] + 1) {
                $segments[] = $segment;
                $segment = [$chars[$i], $chars[$i]];
                continue;
            }
            ++$segment[1];
        }
        $segments[] = $segment;
        $segments[] = [0xFFFF, 0xFFFF];

        return $segments;
    }

    private function buildFont(): string
    {
        // filter tables
        $tables = \array_intersect_key($this->tables, \array_flip(self::TAGS_NAME));
        $offset = 12 + 16 * \count($tables);

        // compute data
        foreach ($tables as $tag => &$table) {
            if ('' === $table['data']) {
                $table = $this->loadTable($tag);
            }
            $table['offset'] = $offset;
            $offset += \strlen($table['data']);
            $this->tables[$tag] = $table;
        }

        // compute offset table
        $offsetTable = $this->getTableOffset($tables);

        // compute checkSumAdjustment (0xB1B0AFBA - font checkSum)
        $checkSumAdjustment = $this->getCheckSumAdjustment($offsetTable, $tables);
        $this->tables[self::TAG_HEAD]['data'] = \substr_replace($this->tables[self::TAG_HEAD]['data'], $checkSumAdjustment, 8, 4);

        // data
        $data = \implode('', \array_column($tables, 'data'));

        return $offsetTable . $data;
    }

    private function buildGlyf(): void
    {
        $data = '';
        $tableOffset = $this->tables[self::TAG_GLYF]['offset'];
        foreach ($this->subsettedGlyphs as $id) {
            $glyph = $this->glyphs[$id];
            $this->seek($tableOffset + $glyph['offset']);
            $glyphData = $this->read($glyph['length']);
            if (isset($glyph['components'])) {
                // composite glyph
                foreach ($glyph['components'] as $offset => $cid) {
                    $ssid = $this->glyphs[$cid]['ssid'];
                    $glyphData = \substr_replace($glyphData, \pack('n', $ssid), $offset, 2);
                }
            }
            $data .= $glyphData;
        }
        $this->setTable(self::TAG_GLYF, $data);
    }

    private function buildHhea(): void
    {
        $this->loadTable(self::TAG_HHEA);
        $subsettedGlyphsCount = $this->getSubsettedGlyphsCount();
        $data = \substr_replace($this->tables[self::TAG_HHEA]['data'], \pack('n', $subsettedGlyphsCount), 4 + 15 * 2, 2);
        $this->setTable(self::TAG_HHEA, $data);
    }

    private function buildHmtx(): void
    {
        $data = '';
        foreach ($this->subsettedGlyphs as $id) {
            $glyph = $this->glyphs[$id];
            $data .= \pack('nn', $glyph['width'], $glyph['lsb']);
        }
        $this->setTable(self::TAG_HMTX, $data);
    }

    private function buildLoca(): void
    {
        $data = '';
        $offset = 0;
        $callback = 0 === $this->indexToLocFormat
            ? static fn(int $offset): string => \pack('n', $offset / 2)
            : static fn(int $offset): string => \pack('N', $offset);
        foreach ($this->subsettedGlyphs as $id) {
            $data .= $callback($offset);
            $offset += $this->glyphs[$id]['length'];
        }
        $data .= $callback($offset);
        $this->setTable(self::TAG_LOCA, $data);
    }

    private function buildMaxp(): void
    {
        $this->loadTable(self::TAG_MAXP);
        $subsettedGlyphsCount = $this->getSubsettedGlyphsCount();
        $data = \substr_replace($this->tables[self::TAG_MAXP]['data'], \pack('n', $subsettedGlyphsCount), 4, 2);
        $this->setTable(self::TAG_MAXP, $data);
    }

    private function buildPost(): void
    {
        $this->seekTag(self::TAG_POST);
        if ($this->glyphNames) {
            // version 2.0
            $subsettedGlyphsCount = $this->getSubsettedGlyphsCount();
            $numNames = 0;
            $names = '';
            $data = $this->read(32);
            $data .= \pack('n', $subsettedGlyphsCount);
            foreach ($this->subsettedGlyphs as $id) {
                $name = $this->glyphs[$id]['name'];
                if (\is_string($name)) {
                    $data .= \pack('n', 258 + $numNames);
                    $names .= \chr(\strlen($name)) . $name;
                    ++$numNames;
                } else {
                    $data .= \pack('n', $name);
                }
            }
            $data .= $names;
        } else {
            // version 3.0
            $this->skip(4);
            $data = "\x00\x03\x00\x00";
            $data .= $this->read(28);
        }
        $this->setTable(self::TAG_POST, $data);
    }

    private function checkSum(string $str): string
    {
        $high = 0;
        $low = 0;
        for ($i = 0, $len = \strlen($str); $i < $len; $i += 4) {
            $high += (\ord($str[$i]) << 8) + \ord($str[$i + 1]);
            $low += (\ord($str[$i + 2]) << 8) + \ord($str[$i + 3]);
        }

        return \pack('nn', $high + ($low >> 16), $low);
    }

    /**
     * @phpstan-param TableType[] $tables
     */
    private function getCheckSumAdjustment(string $offsetTable, array $tables): string
    {
        $cs = $this->checkSum($offsetTable);
        $cs .= \implode('', \array_column($tables, 'checkSum'));

        /** @var int[] $values */
        $values = \unpack('n2', $this->checkSum($cs));
        $high = 0xB1B0 + ($values[1] ^ 0xFFFF);
        $low = 0xAFBA + ($values[2] ^ 0xFFFF) + 1;

        return \pack('nn', $high + ($low >> 16), $low);
    }

    private function getSubsettedGlyphsCount(): int
    {
        return \count($this->subsettedGlyphs);
    }

    /**
     * @phpstan-param array<string, TableType> $tables
     */
    private function getTableOffset(array $tables): string
    {
        $tagsCount = \count($tables);
        $entrySelector = 0;
        $n = $tagsCount;
        while (1 !== $n) {
            $n >>= 1;
            ++$entrySelector;
        }

        $searchRange = 16 * (1 << $entrySelector);
        $rangeShift = 16 * $tagsCount - $searchRange;
        $offsetTable = \pack('nnnnnn', 1, 0, $tagsCount, $searchRange, $entrySelector, $rangeShift);
        foreach ($tables as $tag => $table) {
            $offsetTable .= $tag . $table['checkSum'] . \pack('NN', $table['offset'], $table['length']);
        }

        return $offsetTable;
    }

    private function isBitSet(int $value, int $mask): bool
    {
        return ($value & $mask) === $mask;
    }

    /**
     * @phpstan-param self::TAG_* $tag
     *
     * @phpstan-return TableType
     */
    private function loadTable(string $tag): array
    {
        $this->seekTag($tag);
        $table = &$this->tables[$tag];
        $length = $table['length'];
        $padding = $length % 4;
        if ($padding > 0) {
            $length += 4 - $padding;
        }
        $table['data'] = $this->read($length);

        return $table;
    }

    /**
     * Character to Glyph Index Mapping Table (cmap).
     */
    private function parseCmap(): void
    {
        $this->seekTag(self::TAG_CMAP);
        $this->skip(2); // version
        $numTables = $this->readUShortBigEndian();
        $offset31 = 0;
        for ($i = 0; $i < $numTables; ++$i) {
            $platformID = $this->readUShortBigEndian();
            $encodingID = $this->readUShortBigEndian();
            $offset = $this->readULongBigEndian();
            if (3 === $platformID && 1 === $encodingID) {
                $offset31 = $offset;
            }
        }
        if (0 === $offset31) {
            throw $this->translator->instance('error_unicode_not_found');
        }

        $startCount = [];
        $endCount = [];
        $idDelta = [];
        $idRangeOffset = [];
        $this->chars = [];
        $this->seek($this->tables[self::TAG_CMAP]['offset'] + $offset31);
        $format = $this->readUShortBigEndian();
        if (4 !== $format) {
            throw $this->translator->format('error_table_format', $format);
        }
        $this->skip(4); // length, language
        $segCount = $this->readUShortBigEndian() / 2;
        $this->skip(6); // searchRange, entrySelector, rangeShift
        for ($i = 0; $i < $segCount; ++$i) {
            $endCount[$i] = $this->readUShortBigEndian();
        }
        $this->skip(2); // reservedPad
        for ($i = 0; $i < $segCount; ++$i) {
            $startCount[$i] = $this->readUShortBigEndian();
        }
        for ($i = 0; $i < $segCount; ++$i) {
            $idDelta[$i] = $this->readShort();
        }
        $offset = $this->tell();
        for ($i = 0; $i < $segCount; ++$i) {
            $idRangeOffset[$i] = $this->readUShortBigEndian();
        }

        for ($i = 0; $i < $segCount; ++$i) {
            $c1 = $startCount[$i];
            $c2 = $endCount[$i];
            $d = $idDelta[$i];
            $ro = $idRangeOffset[$i];
            if ($ro > 0) {
                $this->seek($offset + 2 * $i + $ro);
            }
            for ($c = $c1; $c <= $c2; ++$c) {
                if (0xFFFF === $c) {
                    break;
                }
                if ($ro > 0) {
                    $gid = $this->readUShortBigEndian();
                    if ($gid > 0) {
                        $gid += $d;
                    }
                } else {
                    $gid = $c + $d;
                }
                if ($gid >= 0x010000) {
                    $gid -= 0x010000;
                }
                if ($gid > 0) {
                    $this->chars[$c] = $gid;
                }
            }
        }
    }

    /**
     * Glyph Data (glyf).
     */
    private function parseGlyf(): void
    {
        $tableOffset = $this->tables[self::TAG_GLYF]['offset'];
        foreach ($this->glyphs as &$glyph) {
            if (0 === $glyph['length']) {
                continue;
            }
            $this->seek($tableOffset + $glyph['offset']);
            if ($this->readShort() >= 0) {
                continue;
            }
            // composite glyph
            $this->skip(8); // xMin, yMin, xMax, yMax
            $offset = 10;
            $components = [];
            do {
                $flags = $this->readUShortBigEndian();
                $index = $this->readUShortBigEndian();
                $components[$offset + 2] = $index;
                if ($this->isBitSet($flags, 1)) { // arg 1 and arg 2 are words
                    $skip = 4;
                } else {
                    $skip = 2;
                }
                if ($this->isBitSet($flags, 8)) { // scale present
                    $skip += 2;
                } elseif ($this->isBitSet($flags, 64)) { // x and y scale
                    $skip += 4;
                } elseif ($this->isBitSet($flags, 128)) { // two by two
                    $skip += 8;
                }
                $this->skip($skip);
                $offset += 2 * 2 + $skip;
            } while ($flags & 32); // more components
            $glyph['components'] = $components;
        }
    }

    /**
     * Font Header Table (head).
     */
    private function parseHead(): void
    {
        $this->seekTag(self::TAG_HEAD);
        $this->skip(12); // version, fontRevision, checkSumAdjustment
        $magicNumber = $this->readULongBigEndian();
        if (0x5F0F3CF5 !== $magicNumber) {
            throw $this->translator->format('error_magic_number', $magicNumber);
        }
        $this->skip(2); // flags
        $this->unitsPerEm = $this->readUShortBigEndian();
        $this->skip(16); // created, modified
        $this->xMin = $this->readShort();
        $this->yMin = $this->readShort();
        $this->xMax = $this->readShort();
        $this->yMax = $this->readShort();
        $this->skip(6); // macStyle, lowestRecPPEM, fontDirectionHint
        $this->indexToLocFormat = $this->readShort();
    }

    /**
     *  Horizontal Header Table (hhea).
     */
    private function parseHhea(): void
    {
        $this->seekTag(self::TAG_HHEA);
        $this->skip(34);
        $this->numberOfHMetrics = $this->readUShortBigEndian();
    }

    /**
     * Horizontal Metrics Table (hmtx).
     */
    private function parseHmtx(): void
    {
        $this->seekTag(self::TAG_HMTX);
        $this->glyphs = [];
        $width = 0;
        for ($i = 0; $i < $this->numberOfHMetrics; ++$i) {
            $width = $this->readUShortBigEndian();
            $lsb = $this->readShort();
            $this->glyphs[$i] = [
                'name' => '',
                'width' => $width,
                'lsb' => $lsb,
                'length' => 0,
                'offset' => 0,
                'ssid' => 0,
            ];
        }
        for ($i = $this->numberOfHMetrics; $i < $this->numGlyphs; ++$i) {
            $lsb = $this->readShort();
            $this->glyphs[$i] = [
                'name' => '',
                'width' => $width,
                'lsb' => $lsb,
                'length' => 0,
                'offset' => 0,
                'ssid' => 0,
            ];
        }
    }

    /**
     * Index to Location (loca).
     */
    private function parseLoca(): void
    {
        $this->seekTag(self::TAG_LOCA);

        $offsets = [];
        $callback = 0 === $this->indexToLocFormat
            ? fn(): int => 2 * $this->readUShortBigEndian()  // short format
            : fn(): int => $this->readULongBigEndian(); // long format
        for ($i = 0; $i <= $this->numGlyphs; ++$i) {
            $offsets[] = $callback();
        }

        for ($i = 0; $i < $this->numGlyphs; ++$i) {
            $this->glyphs[$i] = \array_merge(
                $this->glyphs[$i],
                [
                    'offset' => $offsets[$i],
                    'length' => $offsets[$i + 1] - $offsets[$i],
                ]
            );
        }
    }

    /**
     * Maximum Profile (maxp).
     */
    private function parseMaxp(): void
    {
        $this->seekTag(self::TAG_MAXP);
        $this->skip(4);
        $this->numGlyphs = $this->readUShortBigEndian();
    }

    /**
     * Naming Table (name).
     */
    private function parseName(): void
    {
        $this->seekTag(self::TAG_NAME);
        $tableOffset = $this->tables[self::TAG_NAME]['offset'];
        $this->postScriptName = '';
        $this->skip(2); // format
        $count = $this->readUShortBigEndian();
        $stringOffset = $this->readUShortBigEndian();
        for ($i = 0; $i < $count; ++$i) {
            $this->skip(6); // platformID, encodingID, languageID
            $nameID = $this->readUShortBigEndian();
            $length = $this->readUShortBigEndian();
            $offset = $this->readUShortBigEndian();
            if (6 === $nameID) {
                // PostScript name
                $this->seek($tableOffset + $stringOffset + $offset);
                $name = $this->read($length);
                $name = \str_replace(\chr(0), '', $name);
                $name = (string) \preg_replace('|[ \[\](){}<>/%]|', '', $name);
                $this->postScriptName = $name;
                break;
            }
        }
        if ('' === $this->postScriptName) {
            throw $this->translator->instance('error_postscript_not_found');
        }
    }

    private function parseOffsetTable(): void
    {
        $version = $this->readULongBigEndian();
        if (0x4F54544F === $version) { // 'OTTO'
            throw $this->translator->instance('error_open_type_unsupported');
        }
        if (0x010000 !== $version) { // TrueType outlines
            throw $this->translator->format('error_file_version', $version);
        }
        $numTables = $this->readUShortBigEndian();
        $this->skip(6); // searchRange, entrySelector, rangeShift
        $this->tables = [];
        for ($i = 0; $i < $numTables; ++$i) {
            /** @phpstan-var self::TAG_* $tag */
            $tag = $this->read(4);
            $checkSum = $this->read(4);
            $offset = $this->readULongBigEndian();
            $length = $this->readULongBigEndian();
            $this->tables[$tag] = [
                'offset' => $offset,
                'data' => '',
                'length' => $length,
                'checkSum' => $checkSum,
            ];
        }
    }

    /**
     * OS/2 and Windows Metrics Table (OS/2).
     */
    private function parseOS2(): void
    {
        $this->seekTag(self::TAG_OS2);
        $version = $this->readUShortBigEndian();
        $this->skip(6); // xAvgCharWidth, usWeightClass, usWidthClass
        $fsType = $this->readUShortBigEndian();
        $this->embeddable = (2 !== $fsType) && ($fsType & 0x200) === 0;
        $this->skip(52);
        $fsSelection = $this->readUShortBigEndian();
        $this->bold = ($fsSelection & 32) !== 0;
        $this->skip(4); // usFirstCharIndex, usLastCharIndex
        $this->typoAscender = $this->readShort();
        $this->typoDescender = $this->readShort();
        if ($version >= 2) {
            $this->skip(16);
            $this->capHeight = $this->readShort();
        } else {
            $this->capHeight = 0;
        }
    }

    /**
     * PostScript Table (post).
     */
    private function parsePost(): void
    {
        $this->seekTag(self::TAG_POST);
        $version = $this->readULongBigEndian();
        $this->italicAngle = $this->readShort();
        $this->skip(2); // skip decimal part
        $this->underlinePosition = $this->readShort();
        $this->underlineThickness = $this->readShort();
        $this->isFixedPitch = (0 !== $this->readULongBigEndian());
        $this->glyphNames = false;
        if (0x20000 !== $version) {
            return;
        }

        // extract glyph names
        $this->glyphNames = true;
        $this->skip(18); // min/max usage, numberOfGlyphs, numberOfGlyphs

        $names = [];
        $namesCount = 0;
        $glyphNameIndex = [];
        for ($i = 0; $i < $this->numGlyphs; ++$i) {
            $index = $this->readUShortBigEndian();
            $glyphNameIndex[] = $index;
            if ($index >= 258 && $index - 257 > $namesCount) {
                $namesCount = $index - 257;
            }
        }
        for ($i = 0; $i < $namesCount; ++$i) {
            $len = \ord($this->read(1));
            $names[] = $this->read($len);
        }
        foreach ($glyphNameIndex as $i => $index) {
            $this->glyphs[$i]['name'] = $index >= 258 ? $names[$index - 258] : $index;
        }
    }

    /**
     * @phpstan-param self::TAG_* $tag
     */
    private function seekTag(string $tag): void
    {
        if (!isset($this->tables[$tag])) {
            throw $this->translator->format('error_table_not_found', $tag);
        }
        $this->seek($this->tables[$tag]['offset']);
    }

    /**
     * @phpstan-param self::TAG_* $tag
     */
    private function setTable(string $tag, string $data): void
    {
        $length = \strlen($data);
        $padding = $length % 4;
        if ($padding > 0) {
            $data = \str_pad($data, $length + 4 - $padding, "\x00");
        }
        $this->tables[$tag] = [
            'offset' => 0,
            'data' => $data,
            'length' => $length,
            'checkSum' => $this->checkSum($data),
        ];
    }
}
