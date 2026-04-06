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

class TTFCMapFormat
{
    /**
     * @param int[] $startCount
     * @param int[] $endCount
     * @param int[] $idDelta
     * @param int[] $idRangeOffset
     */
    public function __construct(
        public array $startCount,
        public array $endCount,
        public array $idDelta,
        public array $idRangeOffset,
        public string $glyphIdArray,
    ) {
    }
}
