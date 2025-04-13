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

if (\PHP_VERSION_ID < 80200) { // @phpstan-ignore smaller.alwaysFalse
    echo "Error: $name requires PHP 8.2 or newer.";
    exit(1);
}

//if (\is_file(__DIR__ . '/../vendor/autoload.php')) {
//    require __DIR__ . '/../vendor/autoload.php';
//}

if (1 === $argc) {
    echo "Usage: php $name fontFile [encoding] [embed] [subset]\n";

    return;
}

try {
    $fontFile = $argv[1];
    $encoding = $argv[2] ?? FontMaker::DEFAULT_ENCODING;
    $embed = (bool) ($argv[3] ?? true);
    $subset = (bool) ($argv[4] ?? true);
    $fontMaker = new FontMaker();
    $fontMaker->makeFont($fontFile, $encoding, $embed, $subset);
} catch (MakeFontException $e) {
    echo $e->getMessage();
}
