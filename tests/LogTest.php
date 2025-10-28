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

use fpdf\Log;
use fpdf\LogLevel;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testLogDefault(): void
    {
        $log = new Log('Message');
        $actual = (string) $log;
        self::assertSame('INFO. Message', $actual);
    }

    public function testLogError(): void
    {
        $log = new Log('Message', LogLevel::ERROR);
        $actual = (string) $log;
        self::assertSame('ERROR. Message', $actual);
    }

    public function testLogWarning(): void
    {
        $log = new Log('Message', LogLevel::WARNING);
        $actual = (string) $log;
        self::assertSame('WARNING. Message', $actual);
    }
}
