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

class FileHandler
{
    /**
     * @phpstan-var resource|closed-resource
     */
    private mixed $handle;

    public function __construct(string $file, string $mode = 'r', Translator $translator = new Translator())
    {
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

    public function read(int $length): string
    {
        return $length > 0 ? (string) \fread($this->getHandle(), $length) : '';
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        \fseek($this->getHandle(), $offset, $whence);
    }

    public function skip(int $offset): void
    {
        $this->seek($offset, \SEEK_CUR);
    }

    public function tell(): int
    {
        return (int) \ftell($this->getHandle());
    }

    /**
     * @phpstan-return array<string, int>
     */
    public function unpack(string $format, int $length): array
    {
        /** @phpstan-var array<string, int> */
        return (array) \unpack($format, $this->read($length));
    }

    public function write(string $data): void
    {
        \fwrite($this->getHandle(), $data);
    }

    /**
     * @return resource
     */
    protected function getHandle(): mixed
    {
        /** @psalm-var resource */
        return $this->handle;
    }
}
