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

class Translator
{
    public const DEFAULT_LOCALE = 'en';

    /**
     * @var array<string, string>
     */
    private readonly array $messages;

    public function __construct(private readonly string $locale = self::DEFAULT_LOCALE)
    {
        $messages = $this->loadFile(self::DEFAULT_LOCALE);
        if (self::DEFAULT_LOCALE !== $locale) {
            $messages = \array_merge($messages, $this->loadFile($locale));
        }
        $this->messages = $messages;
    }

    public function format(string $key, string|int ...$values): MakeFontException
    {
        return MakeFontException::format($this->get($key), ...$values);
    }

    public function get(string $key): string
    {
        return $this->messages[$key] ?? $this->messages['error_unknown'] ?? $key;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function instance(string $key): MakeFontException
    {
        return MakeFontException::instance($this->get($key));
    }

    /**
     * @phpstan-return array<string, string>
     */
    private function loadFile(string $locale): array
    {
        $file = \sprintf('%s/i18n/%s.ini', __DIR__, \strtolower($locale));
        if (!\file_exists($file)) {
            return [];
        }

        /** @phpstan-var array<string, string> */
        return (array) \parse_ini_file($file);
    }
}
