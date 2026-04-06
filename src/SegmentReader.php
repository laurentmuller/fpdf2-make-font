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

class SegmentReader extends FileReader
{
    public function updateFont(FontInfo $font): void
    {
        // read the first segment
        $size1 = $this->readSegment();
        $data1 = $this->read($size1);

        // read the second segment
        $size2 = $this->readSegment();
        $data2 = $this->read($size2);

        $font->data = $data1 . $data2;
        $font->size1 = $size1;
        $font->size2 = $size2;
    }

    private function readSegment(): int
    {
        $marker = $this->readUChar();
        $this->skip(1); // type
        $size = $this->readULongLittleEndian();
        if (128 !== $marker) {
            throw $this->translator->instance('error_invalid_type');
        }

        return $size;
    }
}
