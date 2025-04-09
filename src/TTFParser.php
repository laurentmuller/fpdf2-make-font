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
 */
class TTFParser
{
    private const TAG_NAMES = [
        'cmap', 'cvt ', 'fpgm', 'glyf',
        'head', 'hhea', 'hmtx', 'loca',
        'maxp', 'name', 'post', 'prep',
    ];

    public bool $bold = false;
    public int $capHeight = 0;
    /** @psalm-var array<int, int>  */
    public array $chars = [];
    public bool $embeddable = false;
    /** @psalm-var array<int, array{
     * name: string|int,
     * w: int,
     * lsb: int,
     * length: int,
     * offset: int,
     * ssid: int,
     * components? : array<int, int>}> */
    public array $glyphs = [];
    public bool $isFixedPitch = false;
    public int $italicAngle = 0;
    public string $postScriptName = '';
    public int $typoAscender = 0;
    public int $typoDescender = 0;
    public int $underlinePosition = 0;
    public int $underlineThickness = 0;
    public int $unitsPerEm = 0;
    public int $xMax = 0;
    public int $xMin = 0;
    public int $yMax = 0;
    public int $yMin = 0;
    protected bool $glyphNames = false;
    /** @psalm-var resource|closed-resource|false */
    protected mixed $handle;
    protected int $indexToLocFormat = 0;
    protected int $numberOfHMetrics = 0;
    protected int $numGlyphs = 0;
    /** @psalm-var array<int, int>  */
    protected array $subsettedChars = [];
    /** @psalm-var array<int, int>  */
    protected array $subsettedGlyphs = [];
    /** @psalm-var array<string, array{
     * offset: int,
     * length: int,
     * data: string,
     * checkSum: string}> */
    protected array $tables = [];

    /**
     * @throws \Exception
     */
    public function __construct(string $file)
    {
        $this->handle =\fopen($file, 'r');
        if (!\is_resource($this->handle)) {
            $this->error('Unable to open file: ' . $file);
        }
    }

    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }

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
     * @psalm-param array<int, int> $chars
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
        if (!isset($this->glyphs[$id]['ssid'])) {
            $this->glyphs[$id]['ssid'] = \count($this->subsettedGlyphs);
            $this->subsettedGlyphs[] = $id;
            if (isset($this->glyphs[$id]['components'])) {
                foreach ($this->glyphs[$id]['components'] as $cid) {
                    $this->addGlyph($cid);
                }
            }
        }
    }

    private function buildCmap(): void
    {
        if ([] === $this->subsettedChars) {
            return;
        }

        // Divide charset in contiguous segments
        $chars = $this->subsettedChars;
        \sort($chars);
        $segments = [];
        $segment = [$chars[0], $chars[0]];
        for ($i = 1, $counter = \count($chars); $i < $counter; ++$i) {
            if ($chars[$i] > $segment[1] + 1) {
                $segments[] = $segment;
                $segment = [$chars[$i], $chars[$i]];
            } else {
                ++$segment[1];
            }
        }
        $segments[] = $segment;
        $segments[] = [0xFFFF, 0xFFFF];
        $segCount = \count($segments);

        // Build a Format 4 sub-table
        $startCount = [];
        $endCount = [];
        $idDelta = [];
        $idRangeOffset = [];
        $glyphIdArray = '';
        for ($i = 0; $i < $segCount; ++$i) {
            [$start, $end] = $segments[$i];
            $startCount[] = $start;
            $endCount[] = $end;
            if ($start !== $end) {
                // Segment with multiple chars
                $idDelta[] = 0;
                $idRangeOffset[] = \strlen($glyphIdArray) + ($segCount - $i) * 2;
                for ($c = $start; $c <= $end; ++$c) {
                    $ssid = $this->glyphs[$this->chars[$c]]['ssid'];
                    $glyphIdArray .= \pack('n', $ssid);
                }
            } else {
                // Segment with a single char
                $ssid = $start < 0xFFFF ? $this->glyphs[$this->chars[$start]]['ssid'] : 0;
                $idDelta[] = $ssid - $start;
                $idRangeOffset[] = 0;
            }
        }
        $entrySelector = 0;
        $n = $segCount;
        while (1 !== $n) {
            $n >>= 1;
            ++$entrySelector;
        }
        $searchRange = (1 << $entrySelector) * 2;
        $rangeShift = 2 * $segCount - $searchRange;
        $cmap = \pack('nnnn', 2 * $segCount, $searchRange, $entrySelector, $rangeShift);
        foreach ($endCount as $val) {
            $cmap .= \pack('n', $val);
        }
        $cmap .= \pack('n', 0); // reservedPad
        foreach ($startCount as $val) {
            $cmap .= \pack('n', $val);
        }
        foreach ($idDelta as $val) {
            $cmap .= \pack('n', $val);
        }
        foreach ($idRangeOffset as $val) {
            $cmap .= \pack('n', $val);
        }
        $cmap .= $glyphIdArray;

        $data = \pack('nn', 0, 1); // version, numTables
        $data .= \pack('nnN', 3, 1, 12); // platformID, encodingID, offset
        $data .= \pack('nnn', 4, 6 + \strlen($cmap), 0); // format, length, language
        $data .= $cmap;
        $this->setTable('cmap', $data);
    }

    private function buildFont(): string
    {
        $tags = [];
        foreach (self::TAG_NAMES as $tag) {
            if (isset($this->tables[$tag])) {
                $tags[] = $tag;
            }
        }
        $numTables = \count($tags);
        $offset = 12 + 16 * $numTables;
        foreach ($tags as $tag) {
            if (!isset($this->tables[$tag]['data'])) {
                $this->loadTable($tag);
            }
            $this->tables[$tag]['offset'] = $offset;
            $offset += \strlen($this->tables[$tag]['data']);
        }

        // Build offset table
        $entrySelector = 0;
        $n = $numTables;
        while (1 !== $n) {
            $n >>= 1;
            ++$entrySelector;
        }
        $searchRange = 16 * (1 << $entrySelector);
        $rangeShift = 16 * $numTables - $searchRange;
        $offsetTable = \pack('nnnnnn', 1, 0, $numTables, $searchRange, $entrySelector, $rangeShift);
        foreach ($tags as $tag) {
            $table = $this->tables[$tag];
            $offsetTable .= $tag . $table['checkSum'] . \pack('NN', $table['offset'], $table['length']);
        }

        // Compute checkSumAdjustment (0xB1B0AFBA - font checkSum)
        $s = $this->checkSum($offsetTable);
        foreach ($tags as $tag) {
            $s .= $this->tables[$tag]['checkSum'];
        }
        /** @var int[] $a */
        $a = \unpack('n2', $this->checkSum($s));
        $high = 0xB1B0 + ($a[1] ^ 0xFFFF);
        $low = 0xAFBA + ($a[2] ^ 0xFFFF) + 1;
        $checkSumAdjustment = \pack('nn', $high + ($low >> 16), $low);
        $this->tables['head']['data'] = \substr_replace($this->tables['head']['data'], $checkSumAdjustment, 8, 4);

        $font = $offsetTable;
        foreach ($tags as $tag) {
            $font .= $this->tables[$tag]['data'];
        }

        return $font;
    }

    private function buildGlyf(): void
    {
        $tableOffset = $this->tables['glyf']['offset'];
        $data = '';
        foreach ($this->subsettedGlyphs as $id) {
            $glyph = $this->glyphs[$id];
            $this->seekFile($tableOffset + $glyph['offset']);
            $glyph_data = $this->read($glyph['length']);
            if (isset($glyph['components'])) {
                // Composite glyph
                foreach ($glyph['components'] as $offset => $cid) {
                    $ssid = $this->glyphs[$cid]['ssid'];
                    $glyph_data = \substr_replace($glyph_data, \pack('n', $ssid), $offset, 2);
                }
            }
            $data .= $glyph_data;
        }
        $this->setTable('glyf', $data);
    }

    private function buildHhea(): void
    {
        $this->loadTable('hhea');
        $numberOfHMetrics = \count($this->subsettedGlyphs);
        $data = \substr_replace($this->tables['hhea']['data'], \pack('n', $numberOfHMetrics), 4 + 15 * 2, 2);
        $this->setTable('hhea', $data);
    }

    private function buildHmtx(): void
    {
        $data = '';
        foreach ($this->subsettedGlyphs as $id) {
            $glyph = $this->glyphs[$id];
            $data .= \pack('nn', $glyph['w'], $glyph['lsb']);
        }
        $this->setTable('hmtx', $data);
    }

    private function buildLoca(): void
    {
        $data = '';
        $offset = 0;
        foreach ($this->subsettedGlyphs as $id) {
            if (0 === $this->indexToLocFormat) {
                $data .= \pack('n', $offset / 2);
            } else {
                $data .= \pack('N', $offset);
            }
            $offset += $this->glyphs[$id]['length'];
        }
        if (0 === $this->indexToLocFormat) {
            $data .= \pack('n', $offset / 2);
        } else {
            $data .= \pack('N', $offset);
        }
        $this->setTable('loca', $data);
    }

    private function buildMaxp(): void
    {
        $this->loadTable('maxp');
        $numGlyphs = \count($this->subsettedGlyphs);
        $data = \substr_replace($this->tables['maxp']['data'], \pack('n', $numGlyphs), 4, 2);
        $this->setTable('maxp', $data);
    }

    private function buildPost(): void
    {
        $this->seekTag('post');
        if ($this->glyphNames) {
            // Version 2.0
            $numberOfGlyphs = \count($this->subsettedGlyphs);
            $numNames = 0;
            $names = '';
            $data = $this->read(2 * 4 + 2 * 2 + 5 * 4);
            $data .= \pack('n', $numberOfGlyphs);
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
            // Version 3.0
            $this->skip(4);
            $data = "\x00\x03\x00\x00";
            $data .= $this->read(4 + 2 * 2 + 5 * 4);
        }
        $this->setTable('post', $data);
    }

    private function checkSum(string $s): string
    {
        $n = \strlen($s);
        $high = 0;
        $low = 0;
        for ($i = 0; $i < $n; $i += 4) {
            $high += (\ord($s[$i]) << 8) + \ord($s[$i + 1]);
            $low += (\ord($s[$i + 2]) << 8) + \ord($s[$i + 3]);
        }

        return \pack('nn', $high + ($low >> 16), $low);
    }

    private function error(string $msg): never
    {
        throw new \RuntimeException($msg);
    }

    private function isBitSet(int $value, int $mask):bool
    {
        return ($value & $mask) === $mask;
    }

    private function loadTable(string $tag): void
    {
        $this->seekTag($tag);
        $length = $this->tables[$tag]['length'];
        $n = $length % 4;
        if ($n > 0) {
            $length += 4 - $n;
        }
        $this->tables[$tag]['data'] = $this->read($length);
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    private function parseCmap(): void
    {
        $this->seekTag('cmap');
        $this->skip(2); // version
        $numTables = $this->readUShort();
        $offset31 = 0;
        for ($i = 0; $i < $numTables; ++$i) {
            $platformID = $this->readUShort();
            $encodingID = $this->readUShort();
            $offset = $this->readULong();
            if (3 === $platformID && 1 === $encodingID) {
                $offset31 = $offset;
            }
        }
        if (0 === $offset31) {
            $this->error('No Unicode encoding found');
        }

        $startCount = [];
        $endCount = [];
        $idDelta = [];
        $idRangeOffset = [];
        $this->chars = [];
        $this->seekFile($this->tables['cmap']['offset'] + $offset31);
        $format = $this->readUShort();
        if (4 !== $format) {
            $this->error('Unexpected subtable format: ' . $format);
        }
        $this->skip(2 * 2); // length, language
        $segCount = $this->readUShort() / 2;
        $this->skip(3 * 2); // searchRange, entrySelector, rangeShift
        for ($i = 0; $i < $segCount; ++$i) {
            $endCount[$i] = $this->readUShort();
        }
        $this->skip(2); // reservedPad
        for ($i = 0; $i < $segCount; ++$i) {
            $startCount[$i] = $this->readUShort();
        }
        for ($i = 0; $i < $segCount; ++$i) {
            $idDelta[$i] = $this->readShort();
        }
        // @phpstan-ignore argument.type
        $offset = (int) \ftell($this->handle);
        for ($i = 0; $i < $segCount; ++$i) {
            $idRangeOffset[$i] = $this->readUShort();
        }

        for ($i = 0; $i < $segCount; ++$i) {
            $c1 = $startCount[$i];
            $c2 = $endCount[$i];
            $d = $idDelta[$i];
            $ro = $idRangeOffset[$i];
            if ($ro > 0) {
                $this->seekFile( $offset + 2 * $i + $ro);
            }
            for ($c = $c1; $c <= $c2; ++$c) {
                if (0xFFFF === $c) {
                    break;
                }
                if ($ro > 0) {
                    $gid = $this->readUShort();
                    if ($gid > 0) {
                        $gid += $d;
                    }
                } else {
                    $gid = $c + $d;
                }
                if ($gid >= 65536) {
                    $gid -= 65536;
                }
                if ($gid > 0) {
                    $this->chars[$c] = $gid;
                }
            }
        }
    }

    private function parseGlyf(): void
    {
        $tableOffset = $this->tables['glyf']['offset'];
        foreach ($this->glyphs as &$glyph) {
            if ($glyph['length'] > 0) {
                $this->seekFile( $tableOffset + $glyph['offset']);
                if ($this->readShort() < 0) {
                    // Composite glyph
                    $this->skip(4 * 2); // xMin, yMin, xMax, yMax
                    $offset = 5 * 2;
                    $a = [];
                    do {
                        $flags = $this->readUShort();
                        $index = $this->readUShort();
                        $a[$offset + 2] = $index;
                        if ($this->isBitSet($flags, 1)) { // ARG_1_AND_2_ARE_WORDS
                            $skip = 2 * 2;
                        } else {
                            $skip = 2;
                        }
                        if ($this->isBitSet($flags, 8)) { // WE_HAVE_A_SCALE
                            $skip += 2;
                        } elseif ($this->isBitSet($flags, 64)) { // WE_HAVE_AN_X_AND_Y_SCALE
                            $skip += 2 * 2;
                        } elseif ($this->isBitSet($flags, 128)) { // WE_HAVE_A_TWO_BY_TWO
                            $skip += 4 * 2;
                        }
                        $this->skip($skip);
                        $offset += 2 * 2 + $skip;
                    } while ($flags & 32); // MORE COMPONENTS
                    $glyph['components'] = $a;
                }
            }
        }
    }

    private function parseHead(): void
    {
        $this->seekTag('head');
        $this->skip(3 * 4); // version, fontRevision, checkSumAdjustment
        $magicNumber = $this->readULong();
        if (0x5F0F3CF5 !== $magicNumber) {
            $this->error('Incorrect magic number');
        }
        $this->skip(2); // flags
        $this->unitsPerEm = $this->readUShort();
        $this->skip(2 * 8); // created, modified
        $this->xMin = $this->readShort();
        $this->yMin = $this->readShort();
        $this->xMax = $this->readShort();
        $this->yMax = $this->readShort();
        $this->skip(3 * 2); // macStyle, lowestRecPPEM, fontDirectionHint
        $this->indexToLocFormat = $this->readShort();
    }

    private function parseHhea(): void
    {
        $this->seekTag('hhea');
        $this->skip(4 + 15 * 2);
        $this->numberOfHMetrics = $this->readUShort();
    }

    private function parseHmtx(): void
    {
        $this->seekTag('hmtx');
        $this->glyphs = [];
        $advanceWidth = 0;
        for ($i = 0; $i < $this->numberOfHMetrics; ++$i) {
            $advanceWidth = $this->readUShort();
            $lsb = $this->readShort();
            $this->glyphs[$i] = [
                'name' => '',
                'w' => $advanceWidth,
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
                'w' => $advanceWidth,
                'lsb' => $lsb,
                'length' => 0,
                'offset' => 0,
                'ssid' => 0,
            ];
        }
    }

    /**
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    private function parseLoca(): void
    {
        $this->seekTag('loca');
        $offsets = [];
        if (0 === $this->indexToLocFormat) {
            // Short format
            for ($i = 0; $i <= $this->numGlyphs; ++$i) {
                $offsets[] = 2 * $this->readUShort();
            }
        } else {
            // Long format
            for ($i = 0; $i <= $this->numGlyphs; ++$i) {
                $offsets[] = $this->readULong();
            }
        }
        for ($i = 0; $i < $this->numGlyphs; ++$i) {
            $this->glyphs[$i]['offset'] = $offsets[$i];
            $this->glyphs[$i]['length'] = $offsets[$i + 1] - $offsets[$i];
        }
    }

    private function parseMaxp(): void
    {
        $this->seekTag('maxp');
        $this->skip(4);
        $this->numGlyphs = $this->readUShort();
    }

    private function parseName(): void
    {
        $this->seekTag('name');
        $tableOffset = $this->tables['name']['offset'];
        $this->postScriptName = '';
        $this->skip(2); // format
        $count = $this->readUShort();
        $stringOffset = $this->readUShort();
        for ($i = 0; $i < $count; ++$i) {
            $this->skip(3 * 2); // platformID, encodingID, languageID
            $nameID = $this->readUShort();
            $length = $this->readUShort();
            $offset = $this->readUShort();
            if (6 === $nameID) {
                // PostScript name
                $this->seekFile( $tableOffset + $stringOffset + $offset);
                $s = $this->read($length);
                $s = \str_replace(\chr(0), '', $s);
                $s = \preg_replace('|[ \[\](){}<>/%]|', '', $s);
                $this->postScriptName = (string) $s;
                break;
            }
        }
        if ('' === $this->postScriptName) {
            $this->error('PostScript name not found');
        }
    }

    private function parseOffsetTable(): void
    {
        $version = $this->read(4);
        if ('OTTO' === $version) {
            $this->error('OpenType fonts based on PostScript outlines are not supported');
        }
        if ("\x00\x01\x00\x00" !== $version) {
            $this->error('Unrecognized file format');
        }
        $numTables = $this->readUShort();
        $this->skip(3 * 2); // searchRange, entrySelector, rangeShift
        $this->tables = [];
        for ($i = 0; $i < $numTables; ++$i) {
            $tag = $this->read(4);
            $checkSum = $this->read(4);
            $offset = $this->readULong();
            $length = $this->readULong();
            $this->tables[$tag] = [
                'offset' => $offset,
                'data' => '',
                'length' => $length,
                'checkSum' => $checkSum,
            ];
        }
    }

    private function parseOS2(): void
    {
        $this->seekTag('OS/2');
        $version = $this->readUShort();
        $this->skip(3 * 2); // xAvgCharWidth, usWeightClass, usWidthClass
        $fsType = $this->readUShort();
        $this->embeddable = (2 !== $fsType) && ($fsType & 0x200) === 0;
        $this->skip(11 * 2 + 10 + 4 * 4 + 4);
        $fsSelection = $this->readUShort();
        $this->bold = ($fsSelection & 32) !== 0;
        $this->skip(2 * 2); // usFirstCharIndex, usLastCharIndex
        $this->typoAscender = $this->readShort();
        $this->typoDescender = $this->readShort();
        if ($version >= 2) {
            $this->skip(3 * 2 + 2 * 4 + 2);
            $this->capHeight = $this->readShort();
        } else {
            $this->capHeight = 0;
        }
    }

    private function parsePost(): void
    {
        $this->seekTag('post');
        $version = $this->readULong();
        $this->italicAngle = $this->readShort();
        $this->skip(2); // Skip decimal part
        $this->underlinePosition = $this->readShort();
        $this->underlineThickness = $this->readShort();
        $this->isFixedPitch = (0 !== $this->readULong());
        if (0x20000 === $version) {
            // Extract glyph names
            $this->skip(4 * 4); // min/max usage
            $this->skip(2); // numberOfGlyphs
            $glyphNameIndex = [];
            $names = [];
            $numNames = 0;
            for ($i = 0; $i < $this->numGlyphs; ++$i) {
                $index = $this->readUShort();
                $glyphNameIndex[] = $index;
                if ($index >= 258 && $index - 257 > $numNames) {
                    $numNames = $index - 257;
                }
            }
            for ($i = 0; $i < $numNames; ++$i) {
                $len = \ord($this->read(1));
                $names[] = $this->read($len);
            }
            foreach ($glyphNameIndex as $i => $index) {
                $this->glyphs[$i]['name'] = $index >= 258 ? $names[$index - 258] : $index;
            }
            $this->glyphNames = true;
        } else {
            $this->glyphNames = false;
        }
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    private function read(int $n): string
    {
        // @phpstan-ignore argument.type
        return $n > 0 ?  (string) \fread($this->handle, $n) : '';
    }

    private function readShort(): int
    {
        /** @psalm-var array{n: int} $a */
        $a =  $this->unpack('nn', 2);
        $v = $a['n'];
        if ($v >= 0x8000) {
            $v -= 65536;
        }

        return $v;
    }

    private function readULong(): int
    {
        /** @psalm-var array{N: int} $a */
        $a = $this->unpack('NN', 4);

        return $a['N'];
    }

    private function readUShort(): int
    {
        /** @psalm-var array{n: int} $a */
        $a =  $this->unpack('nn', 2);

        return $a['n'];
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    private function seekFile(int $offset, int $whence = \SEEK_SET): void
    {
        // @phpstan-ignore argument.type
        \fseek($this->handle, $offset, $whence);
    }

    private function seekTag(string $tag): void
    {
        if (!isset($this->tables[$tag])) {
            $this->error('Table not found: ' . $tag);
        }
        $this->seekFile($this->tables[$tag]['offset']);
    }

    private function setTable(string $tag, string $data): void
    {
        $length = \strlen($data);
        $n = $length % 4;
        if ($n > 0) {
            $data = \str_pad($data, $length + 4 - $n, "\x00");
        }
        $this->tables[$tag]['data'] = $data;
        $this->tables[$tag]['length'] = $length;
        $this->tables[$tag]['checkSum'] = $this->checkSum($data);
    }

    private function skip(int $offset): void
    {
        $this->seekFile($offset, \SEEK_CUR);
    }

    /**
     * @psalm-return array<string, int>
     */
    private function unpack(string $format, int $len): array
    {
        /** @psalm-var array<string, int> */
        return (array) \unpack($format, $this->read($len));
    }
}
