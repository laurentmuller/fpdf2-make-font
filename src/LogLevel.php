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

enum LogLevel: int
{
    case ERROR = 2;
    case INFO = 0;
    case WARNING = 1;

    /**
     * Gets a value indicating if this level is higher than the given level
     */
    public function isHigher(LogLevel $level): bool
    {
        return $this->value > $level->value;
    }

    /**
     * Gets a value indicating if this level is lower than the given level
     */
    public function isLower(LogLevel $level): bool
    {
        return $this->value < $level->value;
    }

    /**
     * Gets the level that have the maximum value.
     */
    public static function max(LogLevel ... $levels): LogLevel
    {
        $result = LogLevel::INFO;
        foreach ($levels as $level) {
            if ($level->isHigher($result)) {
                $result = $level;
            }
        }

        return $result;
    }
}
