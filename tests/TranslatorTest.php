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

use fpdf\Translator;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
    public function testFormat(): void
    {
        $translator = new Translator();
        $exception = $translator->format('error_file_empty', 'file');
        $actual = $exception->getMessage();
        self::assertSame('File empty or not readable: file.', $actual);
    }

    public function testInstance(): void
    {
        $translator = new Translator();
        $exception = $translator->instance('error_unknown');
        $actual = $exception->getMessage();
        self::assertSame('Unknown error.', $actual);
    }

    public function testKeyNotDefined(): void
    {
        $translator = new Translator();
        $actual = $translator->get('fake');
        self::assertSame('Unknown error.', $actual);
    }

    public function testLocaleFrench(): void
    {
        $translator = new Translator('fr');
        $actual = $translator->get('error_unknown');
        self::assertSame('Erreur inconnue.', $actual);
    }

    public function testLocaleNotDefined(): void
    {
        $translator = new Translator('fake');
        $actual = $translator->get('error_unknown');
        self::assertSame('Unknown error.', $actual);
    }
}
