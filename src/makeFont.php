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

if (\PHP_VERSION_ID < 80200) {
    echo 'Error: makeFont requires PHP 8.2 or newer.';

    return;
}

if (1 === $argc) {
    $help = <<<HELP
            makeFont v1.0.0
            ---------------
            Usage:
                php makeFont fontFile [encoding [embed [subset]]]

            Options:
                fontFile  The path to the '.ttf', '.otf' or '.pfb' file.
                encoding  The name of the encoding to use. The default value is 'cp1252'.
                embed     Whether to embed the font or not. The default value is 'true'.
                subset    Whether to subset the font or not. The default value is 'true'.
        HELP;
    echo $help;

    return;
}

$file = __DIR__ . '/../vendor/autoload.php';
if (\is_file($file)) {
    require_once $file;
}

try {
    $fontFile = $argv[1];
    $encoding = $argv[2] ?? FontMaker::DEFAULT_ENCODING;
    $embed = (bool) ($argv[3] ?? true);
    $subset = (bool) ($argv[4] ?? true);
    $fontMaker = new FontMaker();
    $fontMaker->makeFont($fontFile, $encoding, $embed, $subset);
    foreach ($fontMaker->getLogs() as $log) {
        echo \sprintf("%s\n", $log);
    }
} catch (MakeFontException $e) {
    echo $e->getMessage();
}
