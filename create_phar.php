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

if (!\class_exists('Phar')) {
    echo "Enable Phar extension.\n";
    exit(1);
}
if (\ini_get('phar.readonly')) {
    echo "Set directive 'phar.readonly=off'.\n";
    exit(1);
}

function addFiles(Phar $phar, string $source, int $offset): void
{
    $directory = new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
    $iterator = new RecursiveIteratorIterator($directory);
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $relativePath = \substr($path, $offset);
        $strip = \php_strip_whitespace($path);
        $phar[$relativePath] = $strip;
    }
}

try {
    $root_dir = \str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__);
    $build_dir = $root_dir . '/build';
    $phar_name = $build_dir . '/makeFont.phar';

    if (!\is_dir($build_dir) && !\mkdir($build_dir)) {
        echo "Unable to create the output directory: $build_dir.\n";
        exit(1);
    }

    if (\is_file($phar_name) && !\unlink($phar_name)) {
        echo "Unable to remove the old Phar: $phar_name.\n";
        exit(1);
    }

    // create phar
    $phar = new Phar($phar_name);
    $phar->setStub("<?php
        require 'phar://' . __FILE__ . '/src/makeFont.php';
        __HALT_COMPILER();
     ");

    $offset = \strlen($root_dir) + 1;

    // add the src files
    addFiles($phar, $root_dir . '/src', $offset);

    // add the vendor files
    addFiles($phar, $root_dir . '/vendor', $offset);

    $phar->stopBuffering();
    $phar->compressFiles(Phar::GZ);

    // Make the file executable
    \chmod($phar_name, 0o770);

    echo "$phar_name successfully created." . \PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage();
}
