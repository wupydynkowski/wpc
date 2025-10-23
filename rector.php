<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
};