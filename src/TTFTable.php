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

class TTFTable
{
    public function __construct(
        public int $offset,
        public int $length,
        public string $checksum,
        public string $data,
    ) {
    }
}
