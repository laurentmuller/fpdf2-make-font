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

use fpdf\FileWriter;
use fpdf\MakeFontException;
use PHPUnit\Framework\TestCase;

class FileWriterTest extends TestCase
{
    public function testFileNotFound(): void
    {
        $this->expectException(MakeFontException::class);
        self::expectExceptionMessage('Unable to open file: ///.txt.');
        new FileWriter("///.txt");
    }
}
