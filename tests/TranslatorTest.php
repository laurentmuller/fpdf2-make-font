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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private const int INT_VALUE = 0x010203;
    private const string STR_VALUE = 'value';

    /**
     * @return \Generator<int, array{0: string, 1: bool}>
     */
    public static function getAllowedLocales(): \Generator
    {
        yield ['en', true];
        yield ['fr', true];
        yield ['fake', false];
    }

    /**
     * @return \Generator<int, array{0: string, 1: string, 2?: string|int}>
     */
    public static function getEnglishExceptions(): \Generator
    {
        yield [
            'error_extension',
            'Unrecognized font file extension: value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_empty',
            'File empty or not readable: value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_not_found',
            'File not found: value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_open',
            'Unable to open file: value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_version',
            'Unrecognized file version: 0x010203.',
            self::INT_VALUE,
        ];
        yield [
            'error_font_name',
            'Font name missing in AFM file.',
        ];
        yield [
            'error_invalid_type',
            'Font file is not a valid binary Type1.',
        ];
        yield [
            'error_license',
            'Font license does not allow embedding.',
        ];
        yield [
            'error_magic_number',
            'Incorrect magic number: 0x00010203.',
            self::INT_VALUE,
        ];
        yield [
            'error_open_type_unsupported',
            'OpenType font based on PostScript outlines is not supported.',
        ];
        yield [
            'error_postscript_not_found',
            'PostScript name not found.',
        ];
        yield [
            'error_table_format',
            'Invalid table format: 10.',
            10,
        ];
        yield [
            'error_table_not_found',
            'Table not found: value.',
            self::STR_VALUE,
        ];
        yield [
            'error_unicode_not_found',
            'No Unicode encoding found.',
        ];
        yield [
            'error_unknown',
            'Unknown error.',
        ];
        yield [
            'info',
            'Information',
        ];
        yield [
            'info_compressed_generated',
            'Font file compressed generated: value.',
            self::STR_VALUE,
        ];
        yield [
            'info_file_generated',
            'Font file definition generated: value.',
            self::STR_VALUE,
        ];
        yield [
            'warning',
            'Warning',
        ];
        yield [
            'warning_character_missing',
            "Character 'value' is missing.",
            self::STR_VALUE,
        ];
    }

    /**
     * @return \Generator<int, array{0: string, 1: string, 2?: string|int}>
     */
    public static function getFrenchExceptions(): \Generator
    {
        yield [
            'error_extension',
            'Extension de fichier de police non reconnue : value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_empty',
            'Fichier vide ou illisible : value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_not_found',
            'Fichier non trouvé : value.',
            self::STR_VALUE,
        ];
        yield [
            'error_file_open',
            "Impossible d'ouvrir le fichier : value.",
            self::STR_VALUE,
        ];
        yield [
            'error_file_version',
            'Version de fichier non reconnue : 0x010203.',
            self::INT_VALUE,
        ];
        yield [
            'error_font_name',
            'Le nom de la police est manquant dans le fichier AFM.',
        ];
        yield [
            'error_invalid_type',
            "Le fichier de polices n'est pas un fichier binaire valide de type 1.",
        ];
        yield [
            'error_license',
            "La licence de la police n'autorise pas l'intégration.",
        ];
        yield [
            'error_magic_number',
            'Numéro magique incorrect : 0x00010203.',
            self::INT_VALUE,
        ];
        yield [
            'error_open_type_unsupported',
            'Les polices OpenType basées sur des contours PostScript ne sont pas prises en charge.',
        ];
        yield [
            'error_postscript_not_found',
            "Aucun nom PostScript n'a été trouvé.",
        ];
        yield [
            'error_table_format',
            'Format de tableau invalid : 10.',
            10,
        ];
        yield [
            'error_table_not_found',
            'Table non trouvée : value.',
            self::STR_VALUE,
        ];
        yield [
            'error_unicode_not_found',
            "Aucun encodage Unicode n'a été trouvé.",
        ];
        yield [
            'error_unknown',
            'Erreur inconnue.',
        ];
        yield [
            'info',
            'Information',
        ];
        yield [
            'info_compressed_generated',
            'Définition du fichier compressé généré : value.',
            self::STR_VALUE,
        ];
        yield [
            'info_file_generated',
            'Définition du fichier de police généré : value.',
            self::STR_VALUE,
        ];
        yield [
            'warning',
            'Avertissement',
        ];
        yield [
            'warning_character_missing',
            "Le caractère 'value' est manquant.",
            self::STR_VALUE,
        ];
    }

    #[DataProvider('getEnglishExceptions')]
    public function testEnglishException(string $key, string $expected, string|int|null $value = null): void
    {
        $translator = new Translator('en');
        $exception = null === $value ? $translator->instance($key) : $translator->format($key, $value);
        $actual = $exception->getMessage();
        self::assertSame($expected, $actual);
    }

    public function testFormat(): void
    {
        $translator = new Translator();
        $exception = $translator->format('error_file_empty', 'file');
        $actual = $exception->getMessage();
        self::assertSame('File empty or not readable: file.', $actual);
    }

    #[DataProvider('getFrenchExceptions')]
    public function testFrenchException(string $key, string $expected, string|int|null $value = null): void
    {
        $translator = new Translator('fr');
        $exception = null === $value ? $translator->instance($key) : $translator->format($key, $value);
        $actual = $exception->getMessage();
        self::assertSame($expected, $actual);
    }

    public function testGetLocale(): void
    {
        $translator = new Translator();
        $actual = $translator->getLocale();
        self::assertSame('en', $actual);

        $translator = new Translator('fr');
        $actual = $translator->getLocale();
        self::assertSame('fr', $actual);

        $translator = new Translator('fake');
        $actual = $translator->getLocale();
        self::assertSame('en', $actual);
    }

    public function testInstance(): void
    {
        $translator = new Translator();
        $exception = $translator->instance('error_unknown');
        $actual = $exception->getMessage();
        self::assertSame('Unknown error.', $actual);
    }

    #[DataProvider('getAllowedLocales')]
    public function testIsAllowedLocale(string $locale, bool $expected): void
    {
        $actual = Translator::isAllowedLocale($locale);
        self::assertSame($expected, $actual);
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

    public function testSprintfNoValue(): void
    {
        $translator = new Translator();
        $format = $translator->get('error_unknown');
        $actual = \sprintf($format, self::STR_VALUE, self::INT_VALUE);
        self::assertSame($format, $actual);
    }
}
