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
use fpdf\MakeFontException;

require __DIR__ . '/../vendor/autoload.php';

function createZipFile(string $zipFile, string $phpFile, string $compressedFile): void
{
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFile($phpFile);
    $zip->addFile($compressedFile);
    $zip->close();
}

function uploadFile(string $tempDir, array $file): ?string
{
    if ('' === $file['name']) {
        return null;
    }
    $name = $file['name'];
    $source = $file['tmp_name'];
    $target = $tempDir . '/' . $name;
    if (!\move_uploaded_file($source, $target)) {
        throw new RuntimeException("Unable to upload the file: $name.");
    }

    return $target;
}

function sendFile(string $file): void
{
    \header('Expires: 0');
    \header('Pragma: public');
    \header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    \header('Content-Transfer-Encoding: binary');
    \header('Content-Description: File Transfer');
    \header('Content-Length: ' . \filesize($file));
    \header('Content-Type: application/octet-stream');
    \header('Content-Disposition: attachment; filename=' . \basename($file));

    \ob_clean();
    \flush();
    \readfile($file);
}

function getTempDir(): string
{
    $directory = __DIR__ . '/../cache/upload';
    if (!\is_dir($directory) && !\mkdir($directory)) {
        throw new RuntimeException('Unable to create a temporary directory.');
    }
    $directory = \realpath($directory);
    \chdir($directory);

    return $directory;
}

function removeTempDir(string $directory): void
{
    if (\is_dir($directory)) {
        \array_map('unlink', \glob($directory . '/*.*'));
        \rmdir($directory);
    }
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

function getAfmFile(): array
{
    return $_FILES['afmFile'] ?? ['name' => ''];
}

/**
 * @phpstan-return ($file is string ? string : null)
 */
function removeFile(?string $file): string
{
    if (null !== $file && \is_file($file) && !\unlink($file)) {
        throw new RuntimeException("Unable to remove existing file: $file.");
    }

    return $file;
}

// files
$zipFile = null;
$tempDir = getTempDir();
$fontFile = uploadFile($tempDir, getFontFile());
$afmFile = uploadFile($tempDir, getAfmFile());

// others values
$embed = isEmbed();
$subset = isSubset();
$encoding = getEncoding();

// target files
$baseName = \substr(\basename($fontFile), 0, -3);
$phpFile = removeFile($baseName . 'php');
$compressedFile = removeFile($baseName . 'z');

try {
    // convert
    $maker = new FontMaker();
    $maker->makeFont(\basename($fontFile), $encoding, $embed, $subset);
    if (\file_exists($compressedFile)) {
        $zipFile = $baseName . 'zip';
        createZipFile($zipFile, $phpFile, $compressedFile);
    }
    // send
    sendFile($zipFile ?? $phpFile);
} catch (MakeFontException $e) {
    echo $e->getMessage();
} finally {
    removeTempDir($tempDir);
}
