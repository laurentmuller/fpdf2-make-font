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

require __DIR__ . '/Legacy/makefont.php';

use fpdf\FontMaker;
use fpdf\MakeFontException;
use PHPUnit\Framework\TestCase;

class FontMakerTest extends TestCase
{
    private const IGNORED_KEY = ['file', 'originalsize'];

    private string $fontPath;
    private string $targetPath;

    #[\Override]
    protected function setUp(): void
    {
        $this->fontPath = __DIR__ . '/fonts/';
        $this->targetPath = __DIR__ . '/targets/';
        if (!\is_dir($this->targetPath)) {
            \mkdir($this->targetPath);
        }
        \chdir($this->targetPath);
    }

    public function testComic(): void
    {
        $name = 'ComicNeue-BoldItalic';
        $this->generateOldFont($name);
        $this->generateNewFont($name);
        $this->compareFont($name);
    }

    public function testEmbedNotSubset(): void
    {
        $name = 'helvetica_no_subset';
        $this->generateOldFont(name: $name, subset: false);
        $this->generateNewFont(name: $name, subset: false);
        $this->compareFont($name);
    }

    public function testEmptyTables(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Table not found: head.');
        $name = 'empty_tables';
        $this->generateNewFont($name);
    }

    public function testFixedPitch(): void
    {
        $name = 'FixedPitch';
        $this->generateOldFont(name: $name);
        $this->generateNewFont(name: $name);
        $this->compareFont($name);
    }

    public function testFontType1(): void
    {
        $name = 'FontType1';
        $this->generateOldFont(name: $name, ext: 'pfb');
        $this->generateNewFont(name: $name, ext: 'pfb');
        $this->compareFont($name);
    }

    public function testFontType1AfmNotFound(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File not found:.*afm_not_found.afm.$/');
        $name = 'afm_not_found';
        $this->generateNewFont(name: $name, ext: 'pfb');
    }

    public function testFontType1Empty(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File empty or not readable:.*empty.afm.$/');
        $name = 'empty';
        $this->generateNewFont(name: $name, ext: 'pfb');
    }

    public function testFontType1NoAscender(): void
    {
        $name = 'FontNoAscender';
        $file = $this->generateNewFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testFontType1NoDescender(): void
    {
        $name = 'FontNoDescender';
        $file = $this->generateNewFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testFontType1NoName(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font name missing in AFM file.');
        $name = 'no_name';
        $this->generateNewFont(name: $name, ext: 'pfb');
    }

    public function testGetEncodings(): void
    {
        $encodings = FontMaker::getEncodings();
        self::assertContains(FontMaker::DEFAULT_ENCODING, $encodings);
    }

    public function testHelvetica(): void
    {
        $name = 'helvetica';
        $this->generateOldFont(name: $name, embed: false);
        $this->generateNewFont(name: $name, embed: false);
        $this->compareFont($name);
    }

    public function testHelvetica1258(): void
    {
        $name = 'helvetica1258';
        $this->generateOldFont(name: $name, encoding: 'cp1258', embed: false);
        $this->generateNewFont(name: $name, encoding: 'cp1258', embed: false);
        $this->compareFont($name);
    }

    public function testInvalidEncoding(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessageMatches('/^File not found:.*fake.map.$/');
        $name = 'times';
        $this->generateNewFont(name: $name, encoding: 'fake');
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
        $name = 'invalid_magic_number';
        $this->generateNewFont($name);
    }

    public function testInvalidMarker(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font file is not a valid binary Type1.');
        $name = 'invalid_marker';
        $this->generateNewFont(name: $name, ext: 'pfb');
    }

    public function testInvalidOttoFont(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('OpenType font based on PostScript outlines is not supported.');
        $name = 'otto_header';
        $this->generateNewFont($name);
    }

    public function testInvalidPostScriptName(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('PostScript name not found.');
        $name = 'invalid_post_script_name';
        $this->generateNewFont($name);
    }

    public function testInvalidTableFormat(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Invalid table format: 255.');
        $name = 'invalid_table_format';
        $this->generateNewFont($name);
    }

    public function testInvalidVersion(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Unrecognized file version: 0xABCDEFFF.');
        $name = 'invalid_version';
        $this->generateNewFont($name);
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
        $this->generateNewFont($name);
    }

    public function testNotFixedPitch(): void
    {
        $name = 'NotFixedPitch';
        $file = $this->generateNewFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testRobotoRegular(): void
    {
        $name = 'Roboto-Regular';
        $this->generateOldFont($name);
        $this->generateNewFont($name);
        $this->compareFont($name);
    }

    public function testRobotoThin(): void
    {
        $name = 'Roboto-Thin';
        $this->generateOldFont($name);
        $this->generateNewFont($name);
        $this->compareFont($name);
    }

    public function testRussian(): void
    {
        $name = 'russian';
        $this->generateOldFont(name: $name, ext: 'otf', encoding: 'KOI8-R');
        $this->generateNewFont(name: $name, ext: 'otf', encoding: 'KOI8-R');
        $this->compareFont($name);
    }

    public function testStdVW(): void
    {
        $name = 'StdVW';
        $file = $this->generateNewFont(name: $name, ext: 'pfb');
        self::assertFileExists($file);
    }

    public function testThai(): void
    {
        $name = 'thai';
        $this->generateOldFont(name: $name, encoding: 'cp874');
        $this->generateNewFont(name: $name, encoding: 'cp874');
        $this->compareFont($name);
    }

    private function compareFont(string $name): void
    {
        $sourceFile = $this->targetPath . $name . '.php';
        $targetFile = $this->targetPath . $name . '.json';
        $sourceContent = $this->loadSource($sourceFile);
        $targetContent = $this->loadTarget($targetFile);
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($sourceContent, $targetContent, self::IGNORED_KEY);
    }

    private function generateNewFont(
        string $name,
        string $ext = 'ttf',
        string $encoding = FontMaker::DEFAULT_ENCODING,
        bool $embed = true,
        bool $subset = true
    ): string {
        $fontFile = $this->fontPath . $name . '.' . $ext;
        $fontMaker = new FontMaker();
        $fontMaker->makeFont(fontFile: $fontFile, encoding: $encoding, embed: $embed, subset: $subset);

        return $fontFile;
    }

    private function generateOldFont(
        string $name,
        string $ext = 'ttf',
        string $encoding = FontMaker::DEFAULT_ENCODING,
        bool $embed = true,
        bool $subset = true
    ): void {
        $fontFile = $this->fontPath . $name . '.' . $ext;
        MakeFont($fontFile, $encoding, $embed, $subset); // @phpstan-ignore-line function.notFound
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    private function loadSource(string $file): array
    {
        if (!\file_exists($file)) {
            self::fail('File not found: ' . $file);
        }

        include $file;
        /** @phpstan-var array{cw: array<string, int>, desc: array<string, string>, ...<string, mixed>} $source */
        $source = \get_defined_vars();
        $source['cw'] = \array_values($source['cw']);
        $fontBBox = \substr($source['desc']['FontBBox'], 1, -1);
        $source['desc']['FontBBox'] = \array_map('intval', \explode(' ', $fontBBox));
        \ksort($source);
        \ksort($source['desc']);

        return $source;
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    private function loadTarget(string $file): array
    {
        if (!\file_exists($file)) {
            self::fail('File not found: ' . $file);
        }

        $content = \file_get_contents($file);
        self::assertIsString($content);
        /** @phpstan-var array{cw: array<string, int>, desc: array<string, mixed>, ...<string, mixed>} $source */
        $source = \json_decode(json: $content, associative: true, flags: \JSON_OBJECT_AS_ARRAY);
        \ksort($source);
        \ksort($source['desc']);

        return $source;
    }
}
