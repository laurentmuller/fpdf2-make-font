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

use fpdf\FontMaker;

require __DIR__ . '/../vendor/autoload.php';

function createZipFile(string $zipFile, string $phpFile, string $compressedFile): string
{
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFile($phpFile);
    $zip->addFile($compressedFile);
    $zip->close();

    return $zipFile;
}

function sendFile(string $file): void
{
    \header('Expires: 0');
    \header('Pragma: public');
    \header('Content-Transfer-Encoding: binary');
    \header('Content-Description: File Transfer');
    \header('Content-Type: application/octet-stream');
    \header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    \header('Content-Disposition: attachment; filename=' . \basename($file));
    \header('Content-Length: ' . \filesize($file));
    \ob_clean();
    \flush();
    \readfile($file);
}

function getTempDir(): string
{
    $directory = \realpath(__DIR__ . '/../cache');
    if (!\is_dir($directory) && !\mkdir($directory)) {
        throw new RuntimeException('Unable to create a temporary directory.');
    }
    \chdir($directory);

    return $directory;
}

function isEmbed(): bool
{
    return \filter_var($_POST['embed'] ?? 'false', \FILTER_VALIDATE_BOOLEAN);
}

function isSubset(): bool
{
    return \filter_var($_POST['subset'] ?? 'false', \FILTER_VALIDATE_BOOLEAN);
}

function getEncoding(): string
{
    return \htmlspecialchars($_POST['encoding']);
}

function getFontFile(): array
{
    return $_FILES['fontFile'];
}

function removeFile(string $file): string
{
    if (\is_file($file) && !\unlink($file)) {
        throw new RuntimeException("Unable to remove existing file: $file.");
    }

    return $file;
}

$file = getFontFile();
$encoding = getEncoding();
$embed = isEmbed();
$subset = isSubset();

$name = $file['name'];
$source = $file['tmp_name'];
$target = getTempDir() . '/' . $name;
if (!\move_uploaded_file($source, $target)) {
    throw new RuntimeException("Unable to copy the font file: $name.");
}

$baseName = \substr(\basename($target), 0, -3);
$phpFile = removeFile($baseName . 'php');
$compressedFile = removeFile($baseName . 'z');

$maker = new FontMaker();
$maker->makeFont($target, $encoding, $embed, $subset);
if (\file_exists($compressedFile)) {
    $zipFile = $baseName . 'zip';
    $phpFile = createZipFile($zipFile, $phpFile, $compressedFile);
}
sendFile($phpFile);
