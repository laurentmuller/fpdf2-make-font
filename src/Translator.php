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

    private string $locale = self::DEFAULT_LOCALE;

    /**
     * @var array<string, string>
     */
    private array $messages;

    public function __construct(string $locale = self::DEFAULT_LOCALE)
    {
        $this->messages = $this->loadFile(self::DEFAULT_LOCALE);
        if (self::DEFAULT_LOCALE !== $locale) {
            $this->merge($locale);
        }
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
        $file = \sprintf('%s/i18n/%s.json', __DIR__, \strtolower($locale));
        if (!\file_exists($file)) {
            return [];
        }

        $content = (string) \file_get_contents($file);

        /** @phpstan-var array<string, string> */
        return \json_decode($content, true);
    }

    private function merge(string $locale): void
    {
        $custom = $this->loadFile($locale);
        if ([] === $custom) {
            return;
        }
        $this->messages = \array_merge($this->messages, $custom);
        $this->locale = $locale;
    }
}
