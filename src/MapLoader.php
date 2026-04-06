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

readonly class MapLoader
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @return string[]
     */
    public function getFileLines(string $fileName): array
    {
        if (!\file_exists($fileName)) {
            throw $this->translator->format('error_file_not_found', $fileName);
        }
        $lines = \file($fileName, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines || [] === $lines) {
            throw $this->translator->format('error_file_empty', $fileName);
        }

        return $lines;
    }

    /**
     * @return array<int, MapEntry>
     */
    public function getFileMap(string $encoding): array
    {
        $fileName = \sprintf('%s/map/%s.map', __DIR__, \strtolower($encoding));
        $lines = $this->getFileLines($fileName);

        $map = \array_fill(0, 256, new MapEntry());
        foreach ($lines as $line) {
            $values = \explode(' ', $line);
            $key = (int) \hexdec(\substr($values[0], 1));
            $uv = (int) \hexdec(\substr($values[1], 2));
            $name = \rtrim($values[2]);
            $map[$key] = new MapEntry($uv, $name);
        }

        return $map;
    }
}
