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
    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
    $directory = new RecursiveDirectoryIterator($source, $flags);
    $iterator = new RecursiveIteratorIterator($directory);
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $content = \php_strip_whitespace($path);
        $relativePath = \substr($path, $offset);
        $phar[$relativePath] = $content;
    }
}

/*
 * Run before: composer install --no-dev --classmap-authoritative
 * Run after: composer install
 */
try {
    $root_dir = \str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__);
    $build_dir = $root_dir . '/build';
    $phar_file = $build_dir . '/makeFont.phar';
    $offset = \strlen($root_dir) + 1;

    if (!\is_dir($build_dir) && !\mkdir($build_dir)) {
        echo "Unable to create the output directory: $build_dir.\n";
        exit(1);
    }

    if (\is_file($phar_file) && !\unlink($phar_file)) {
        echo "Unable to remove the old Phar: $phar_file.\n";
        exit(1);
    }

    // create phar
    $phar = new Phar($phar_file);
    $phar->setStub("<?php
        require 'phar://' . __FILE__ . '/src/makeFont.php';
        __HALT_COMPILER();
     ");

    // add the src and vendor files
    $phar->startBuffering();
    addFiles($phar, $root_dir . '/src', $offset);
    addFiles($phar, $root_dir . '/vendor', $offset);
    $phar->stopBuffering();

    // compress
    $phar->compressFiles(Phar::GZ);
    // Make the file executable
    \chmod($phar_file, 0o770);

    echo "$phar_file successfully created." . \PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage();
}
