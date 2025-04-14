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

use fpdf\FileHandler;
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

    public function testFileHandlerNotFound(): void
    {
        $this->expectException(MakeFontException::class);
        self::expectExceptionMessage('Unable to open file: fake.txt.');
        new FileHandler('fake.txt');
    }

    public function testFontType1(): void
    {
        $name = 'FontType1';
        $this->generateFont(name: $name, ext: 'pfb');
        $this->compareFont($name);
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
        $this->generateFont(name: $name, embed: false, encoding: 'cp1258');
        $this->compareFont($name);
    }

    public function testInvalidEncoding(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Encoding not found: fake');
        $fontFile = $this->fonts . 'times.ttf';
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile, 'fake');
    }

    public function testInvalidExtension(): void
    {
        $fontFile = __FILE__;
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Unrecognized font file extension: php.');
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidFontFile(): void
    {
        $fontFile = 'fake.txt';
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('Font file not found: fake.txt.');
        $fontMaker = new FontMaker();
        $fontMaker->makeFont($fontFile);
    }

    public function testInvalidOttoFont(): void
    {
        self::expectException(MakeFontException::class);
        self::expectExceptionMessage('OpenType font based on PostScript outlines is not supported.');
        $fontFile = $this->fonts . 'otto_header.ttf';
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
        bool $embed = true,
        string $ext = 'ttf',
        string $encoding = FontMaker::DEFAULT_ENCODING
    ): void {
        $fontFile = $this->fonts . $name . '.' . $ext;
        $fontMaker = new FontMaker();
        $fontMaker->makeFont(fontFile: $fontFile, encoding: $encoding, embed: $embed);
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
