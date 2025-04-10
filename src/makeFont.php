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

$name = \basename(__FILE__, '.php');

// @phpstan-ignore smaller.alwaysFalse
if (\PHP_VERSION_ID < 80200) {
    echo "Error: $name requires PHP 8.2 or newer.";
    exit(1);
}

if (\is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (1 === $argc) {
    echo "Usage: php $name fontFile [encoding] [embed] [subset]\n";

    return;
}

$fontFile = $argv[1];
$enc = $argv[2] ?? 'cp1252';
$embed = (bool) ($argv[3] ?? true);
$subset = (bool) ($argv[4] ?? true);
$fontMaker = new FontMaker();

try {
    $fontMaker->makeFont($fontFile, $enc, $embed, $subset);
} catch (MakeFontException $e) {
    echo $e->getMessage();
}
