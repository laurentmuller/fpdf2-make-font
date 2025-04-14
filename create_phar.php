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

function addFiles(Phar $phar, string $baseDirectory, string $sourceDirectory): void
{
    $offset = \strlen($baseDirectory) + 1;
    $fullPath = $baseDirectory . $sourceDirectory;
    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
    $directory = new RecursiveDirectoryIterator($fullPath, $flags);
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
    $baseDirectory = \str_replace(\DIRECTORY_SEPARATOR, '/', __DIR__);
    $buildDirectory = $baseDirectory . '/build';
    $pharFile = $buildDirectory . '/makeFont.phar';

    if (!\is_dir($buildDirectory) && !\mkdir($buildDirectory)) {
        echo "Unable to create the output directory: $buildDirectory.\n";
        exit(1);
    }

    if (\is_file($pharFile) && !\unlink($pharFile)) {
        echo "Unable to remove the old Phar: $pharFile.\n";
        exit(1);
    }

    // create phar
    $phar = new Phar($pharFile);
    $phar->setStub("<?php
        require 'phar://' . __FILE__ . '/src/makeFont.php';
        __HALT_COMPILER();
     ");

    // add the src and vendor files
    $phar->startBuffering();
    addFiles($phar, $baseDirectory, '/src');
    addFiles($phar, $baseDirectory, '/vendor');
    $phar->stopBuffering();

    // compress
    $phar->compressFiles(Phar::GZ);

    // Make the file executable
    \chmod($pharFile, 0o770);

    echo "$pharFile successfully created.";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
