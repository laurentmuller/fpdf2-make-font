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

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ConstFetch\RemovePhpVersionIdCheckRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

$paths = [
    __DIR__ . '/src',
    __DIR__ . '/tests',
    __DIR__ . '/rector.php',
];

$skip = [
    RemovePhpVersionIdCheckRector::class,
    PreferPHPUnitThisCallRector::class,
    __DIR__ . '/tests/Legacy',
    __DIR__ . '/tests/targets',
    // no space before or after statements
    NewlineBeforeNewAssignSetRector::class,
    // don't separate constants
    NewlineBetweenClassLikeStmtsRector::class,
    // don't rename exception
    CatchExceptionNameMatchingTypeRector::class,
];

$sets = [
    // global
    SetList::CODE_QUALITY,
    SetList::CODING_STYLE,
    SetList::DEAD_CODE,
    SetList::PRIVATIZATION,
    SetList::TYPE_DECLARATION,
    // PHP-Unit
    PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    PHPUnitSetList::PHPUNIT_MOCK_TO_STUB,
    PHPUnitSetList::PHPUNIT_NARROW_ASSERTS,
];

return RectorConfig::configure()
    ->withCache(__DIR__ . '/cache/rector')
    ->withRootFiles()
    ->reportUnusedSkips()
    ->withPhpSets(php83: true)
    ->withPaths($paths)
    ->withSkip($skip)
    ->withSets($sets)
    ->withComposerBased(
        phpunit: true
    )->withAttributesSets(
        phpunit: true
    );
