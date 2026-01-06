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

class FileHandle
{
    /**
     * @phpstan-var resource|closed-resource
     */
    protected mixed $handle;

    public function __construct(
        string $file,
        string $mode,
        protected readonly Translator $translator = new Translator()
    ) {
        $handle = \fopen($file, $mode);
        if (!\is_resource($handle)) {
            throw $translator->format('error_file_open', $file);
        }
        $this->handle = $handle;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }
}
