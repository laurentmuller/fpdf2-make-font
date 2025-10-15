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

readonly class Log implements \Stringable
{
    /**
     * @param string   $message the message
     * @param LogLevel $level   the log level
     */
    public function __construct(
        public string $message,
        public LogLevel $level = LogLevel::INFO,
    ) {}

    #[\Override]
    public function __toString(): string
    {
        return \sprintf('%s. %s', $this->level->name, $this->message);
    }
}
