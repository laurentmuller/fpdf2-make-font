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

use fpdf\LogLevel;
use PHPUnit\Framework\TestCase;

class LogLevelTest extends TestCase
{
    public function testIsHigher(): void
    {
        $levelInfo = LogLevel::INFO;
        $levelWarning = LogLevel::WARNING;
        $levelError = LogLevel::ERROR;

        self::assertTrue($levelWarning->isHigher($levelInfo));
        self::assertTrue($levelError->isHigher($levelInfo));
        self::assertTrue($levelError->isHigher($levelWarning));

        self::assertFalse($levelInfo->isHigher($levelWarning));
        self::assertFalse($levelInfo->isHigher($levelError));
        self::assertFalse($levelWarning->isHigher($levelError));
    }

    public function testIsLower(): void
    {
        $levelInfo = LogLevel::INFO;
        $levelWarning = LogLevel::WARNING;
        $levelError = LogLevel::ERROR;

        self::assertTrue($levelInfo->isLower($levelWarning));
        self::assertTrue($levelInfo->isLower($levelError));
        self::assertTrue($levelWarning->isLower($levelError));

        self::assertFalse($levelError->isLower($levelInfo));
        self::assertFalse($levelWarning->isLower($levelInfo));
    }

    public function testMax(): void
    {
        $actual = LogLevel::max(LogLevel::INFO, LogLevel::WARNING);
        self::assertSame(LogLevel::WARNING, $actual);

        $actual = LogLevel::max(LogLevel::INFO, LogLevel::ERROR);
        self::assertSame(LogLevel::ERROR, $actual);

        $actual = LogLevel::max(LogLevel::WARNING, LogLevel::ERROR);
        self::assertSame(LogLevel::ERROR, $actual);

        $actual = LogLevel::max(LogLevel::WARNING, LogLevel::ERROR, LogLevel::INFO);
        self::assertSame(LogLevel::ERROR, $actual);
    }
}
