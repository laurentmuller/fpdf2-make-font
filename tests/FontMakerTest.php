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

namespace fpdf\Tests;

use fpdf\FontMaker;
use fpdf\MakeFontException;
use PHPUnit\Framework\TestCase;

class FontMakerTest extends TestCase
{
    private const IGNORED_KEY = ['file', 'originalsize'];

    private string $fonts;
    private string $sources;
    private string $targets;

    #[\Override]
    protected function setUp(): void
    {
        $this->fonts = __DIR__ . '/fonts/';
        $this->sources = __DIR__ . '/sources/';
        $this->targets = __DIR__ . '/targets/';

        if (!\is_dir($this->targets)) {
            \mkdir($this->targets);
        }
        \chdir($this->targets);
    }

    public function testComic(): void
    {
        $name = 'ComicNeue-BoldItalic';
        $this->generateFont($name);
        $this->compareFont($name);
    }

    public function testEmbedNotSubset(): void
    {
        $name = 'helvetica_no_subset';
        $this->generateFont(name: $name, subset: false);
        $this->compareFont($name);
    }

    public function testEmptyTables(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Table not found: head.');
        $fontFile = $this->fonts . 'empty_tables.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testFixedPitch(): void
    {
        $name = 'FixedPitch';
        $file = $this->generateFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testFontType1(): void
    {
        $name = 'FontType1';
        $this->generateFont(name: $name, ext: 'pfb');
        $this->compareFont($name);
    }

    public function testFontType1AfmNotFound(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File not found:.*afm_not_found.afm.$/');
        $name = 'afm_not_found';
        $this->generateFont(name: $name, ext: 'pfb');
    }

    public function testFontType1Empty(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File empty or not readable:.*empty.afm.$/');
        $name = 'empty';
        $this->generateFont(name: $name, ext: 'pfb');
    }

    public function testFontType1NoAscender(): void
    {
        $name = 'FontNoAscender';
        $file = $this->generateFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testFontType1NoDescender(): void
    {
        $name = 'FontNoDescender';
        $file = $this->generateFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testFontType1NoName(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font name missing in AFM file.');
        $name = 'no_name';
        $this->generateFont(name: $name, ext: 'pfb');
    }

    public function testGetEncodings(): void
    {
        $encodings = FontMaker::getEncodings();
        self::assertContains(FontMaker::DEFAULT_ENCODING, $encodings);
    }

    public function testHelvetica(): void
    {
        $name = 'helvetica';
        $this->generateFont(name: $name, embed: false);
        $this->compareFont($name);
    }

    public function testHelvetica1258(): void
    {
        $name = 'helvetica1258';
        $this->generateFont(name: $name, encoding: 'cp1258', embed: false);
        $this->compareFont($name);
    }

    public function testInvalidEncoding(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File not found:.*fake.map.$/');
        $fontFile = $this->fonts . 'times.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile, 'fake');
    }

    public function testInvalidExtension(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Unrecognized font file extension: php.');
        $fontFile = __FILE__;
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidFontFile(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('File not found: fake.txt.');
        $fontFile = 'fake.txt';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidMagicNumber(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Incorrect magic number: 0xFFFFFFFF.');
        $fontFile = $this->fonts . 'invalid_magic_number.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidMarker(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font file is not a valid binary Type1.');
        $name = 'invalid_marker';
        $this->generateFont(name: $name, ext: 'pfb');
    }

    public function testInvalidOttoFont(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('OpenType font based on PostScript outlines is not supported.');
        $fontFile = $this->fonts . 'otto_header.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidPostScriptName(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('PostScript name not found.');
        $fontFile = $this->fonts . 'invalid_post_script_name.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidVersion(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Unrecognized file version: 0xABCDEFFF.');
        $fontFile = $this->fonts . 'invalid_version.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testLocale(): void
    {
        $maker = new FontMaker();
        $actual = $maker->getLocale();
        self::assertSame('en', $actual);

        $maker->setLocale('fr');
        $actual = $maker->getLocale();
        self::assertSame('fr', $actual);
    }

    public function testNotEmbeddable(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font license does not allow embedding.');
        $name = 'not_embeddable';
        $this->generateFont($name);
    }

    public function testRobotoRegular(): void
    {
        $name = 'Roboto-Regular';
        $this->generateFont($name);
        $this->compareFont($name);
    }

    public function testRobotoThin(): void
    {
        $name = 'Roboto-Thin';
        $this->generateFont($name);
        $this->compareFont($name);
    }

    public function testRussian(): void
    {
        $name = 'russian';
        $this->generateFont(name: $name, ext: 'otf', encoding: 'KOI8-R');
        $this->compareFont($name);
    }

    public function testStdVW(): void
    {
        $name = 'StdVW';
        $file = $this->generateFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testThai(): void
    {
        $name = 'thai';
        $this->generateFont(name: $name, encoding: 'cp874');
        $this->compareFont($name);
    }

    private function compareFont(string $name): void
    {
        $sourceFile = $this->sources . $name . '.php';
        $targetFile = $this->targets . $name . '.php';
        $source = $this->load($sourceFile);
        $target = $this->load($targetFile);
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($source, $target, self::IGNORED_KEY);
    }

    private function generateFont(
        string $name,
        string $ext = 'ttf',
        string $encoding = FontMaker::DEFAULT_ENCODING,
        bool $embed = true,
        bool $subset = true
    ): string {
        $fontFile = $this->fonts . $name . '.' . $ext;
        $fontMaker = new FontMaker();
        $fontMaker->makeFont(fontFile: $fontFile, encoding: $encoding, embed: $embed, subset: $subset);

        return $fontFile;
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    private function load(string $file): array
    {
        if (!\file_exists($file)) {
            self::fail('File not found: ' . $file);
        }
        include $file;

        /** @phpstan-var array<string, mixed> */
        return \get_defined_vars();
    }
}
