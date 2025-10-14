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
    public function __construct(
        string $file,
        string $mode = '',
        Translator $translator = new Translator()
    ) {
        parent::__construct($file, 'r' . $mode, $translator);
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

    public function unpackInt(string $format, int $length): int
    {
        /** @phpstan-var array<int> $values */
        $values = (array) \unpack($format, $this->read($length));

        return $values[1];
    }



    protected function readShort(): int
    {
        $value = $this->readUShort();
        if ($value >= 0x008000) {
            $value -= 0x010000;
        }

        return $value;
    }

    protected function readULong(): int
    {
        return $this->unpackInt('N', 4);
    }

    protected function readUShort(): int
    {
        return $this->unpackInt('n', 2);
    }

}
