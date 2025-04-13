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

try {
    $root_dir = str_replace('\\', '/', __DIR__);
    $build_dir = $root_dir . '/build';
    $phar_name = $build_dir . '/makeFont.phar';

    if (!is_dir($build_dir) && !mkdir($build_dir)) {
        echo "Unable to create the output directory: '$build_dir'.\n";
        die(1);
    }

    if (is_file($phar_name) && !unlink($phar_name)) {
        echo "Unable to remove the old Phar: '$phar_name'.\n";
        die(1);
    }

    // require_once $root_dir . '/vendor/autoload.php';

    // create phar
    $phar = new Phar($phar_name);

    // start buffering, mandatory to modify the stub to add shebang
    $phar->startBuffering();

    // Create the default stub from makeFont.php entrypoint
    $defaultStub = $phar->createDefaultStub('makeFont.php');

    // Add the rest of the src files
    $src_dir = $root_dir . '/src';
    $offset = strlen($src_dir) + 1;
    $directory = new \RecursiveDirectoryIterator($src_dir);
    $iterator = new \RecursiveIteratorIterator($directory);
    foreach ($iterator as $file) {
        if (!is_file($file->getPathname())) {
            continue;
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', (string) $file);
        $relativePath = substr($path, $offset);
        $phar[$relativePath] = php_strip_whitespace($path);
        echo $relativePath . "\n";
    }

    // $phar->buildFromDirectory($root_dir . '/src');

    // Customize the stub to add the shebang
    $stub = "#!/usr/bin/env php \n" . $defaultStub;

    // Add the stub
    $phar->setStub($stub);

    $phar->stopBuffering();

    // plus - compressing it into gzip
    $phar->compressFiles(Phar::GZ);

    // Make the file executable
    // \chmod($phar_file, 0o770);
    // __DIR__ .

    echo "$phar_name successfully created" . \PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage();
}
