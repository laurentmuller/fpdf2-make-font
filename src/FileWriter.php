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

class FileWriter extends FileHandle
{
    public function __construct(string $file, string $mode = '', Translator $translator = new Translator())
    {
        parent::__construct($file, 'w' . $mode, $translator);
    }

    public function write(string $data): void
    {
        \fwrite($this->handle, $data);
    }
}
