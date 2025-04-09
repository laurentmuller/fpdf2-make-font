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

require __DIR__ . '/../vendor/autoload.php';

use fpdf\MakeFont;

if (\PHP_SAPI === 'cli') {
    // Command-line interface
    \ini_set('log_errors', '0');
    if (1 === $argc) {
        exit("Usage: php make.php fontFile [encoding] [embed] [subset]\n");
    }

    $fontFile = $argv[1];
    $enc = $argv[2] ?? 'cp1252';
    $embed = (bool) ($argv[3] ?? true);
    $subset = (bool) ($argv[4] ?? true);

    $makeFont = new MakeFont();
    $makeFont->makeFont($fontFile, $enc, $embed, $subset);
}
