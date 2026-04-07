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

readonly class FileLinesParser
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @return string[]
     */
    public function getLines(string $fileName): array
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
}
