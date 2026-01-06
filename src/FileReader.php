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

class FileReader extends FileHandle
{
    public function __construct(string $file, string $mode = '', Translator $translator = new Translator())
    {
        parent::__construct($file, 'r' . $mode, $translator);
    }

    public function read(int $length): string
    {
        return $length > 0 ? (string) \fread($this->handle, $length) : '';
    }

    public function readShort(): int
    {
        $value = $this->readUShortBigEndian();

        return $value >= 0x008000 ? $value - 0x010000 : $value;
    }

    /**
     * Unsigned char.
     */
    public function readUChar(): int
    {
        return $this->unpackInt('C', 1);
    }

    /**
     * Unsigned long (always 32 bit, big endian byte order).
     */
    public function readULongBigEndian(): int
    {
        return $this->unpackInt('N', 4);
    }

    /**
     * Unsigned long (always 32 bit, little endian byte order).
     */
    public function readULongLittleEndian(): int
    {
        return $this->unpackInt('V', 4);
    }

    /**
     * Unsigned short (always 16 bit, big endian byte order).
     */
    public function readUShortBigEndian(): int
    {
        return $this->unpackInt('n', 2);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        \fseek($this->handle, $offset, $whence);
    }

    public function skip(int $offset): void
    {
        $this->seek($offset, \SEEK_CUR);
    }

    public function tell(): int
    {
        return (int) \ftell($this->handle);
    }

    public function unpackInt(string $format, int $length): int
    {
        /** @var int[] $values */
        $values = (array) \unpack($format, $this->read($length));

        return $values[1];
    }
}
