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

use Rector\CodingStyle\Rector\ArrowFunction\StaticArrowFunctionRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Closure\StaticClosureRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingConstructorRector;
use Rector\DeadCode\Rector\ConstFetch\RemovePhpVersionIdCheckRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
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
    NewlineAfterStatementRector::class,
    NewlineBeforeNewAssignSetRector::class,
    // don't separate constants
    NewlineBetweenClassLikeStmtsRector::class,
    // don't rename exception
    CatchExceptionNameMatchingTypeRector::class,
    // allow delegate constructor
    RemoveParentDelegatingConstructorRector::class,
];

$sets = [
    // global
    SetList::PHP_82,
    SetList::CODE_QUALITY,
    SetList::CODING_STYLE,
    SetList::DEAD_CODE,
    SetList::INSTANCEOF,
    SetList::PRIVATIZATION,
    SetList::TYPE_DECLARATION,
    // PHP-Unit
    PHPUnitSetList::PHPUNIT_110,
    PHPUnitSetList::PHPUNIT_CODE_QUALITY,
];

$rules = [
    // static closure and arrow functions
    StaticClosureRector::class,
    StaticArrowFunctionRector::class,
    // must be removed when using SetList::PHP_83
    AddOverrideAttributeToOverriddenMethodsRector::class,
];

return RectorConfig::configure()
    ->withCache(__DIR__ . '/cache/rector')
    ->withRootFiles()
    ->withPaths($paths)
    ->withSkip($skip)
    ->withSets($sets)
    ->withRules($rules)
    ->withComposerBased(
        phpunit: true
    )->withAttributesSets(
        phpunit: true
    );
