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

use fpdf\MakeFont;
use PHPUnit\Framework\TestCase;

class TestMakeFont extends TestCase
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

    public function testHelvetica(): void
    {
        $name = 'helvetica';
        $this->generateFont($name, false);
        $this->compareFont($name);
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

    private function generateFont(string $name, bool $embed = true): void
    {
        $fontFile = $this->fonts . $name . '.ttf';
        $makeFont = new MakeFont();
        $makeFont->makeFont(fontFile: $fontFile, embed: $embed);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $file): array
    {
        if (!\file_exists($file)) {
            self::fail('Unable to find file: ' . $file);
        }
        include $file;

        /** @psalm-var array<string, mixed> */
        return \get_defined_vars();
    }
}
