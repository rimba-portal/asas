<?php

declare(strict_types=1);

namespace Rimba\Base\Traits;

use Rimba\Base\Support\JsonRepository;

trait InteractsWithJsonFile
{
    protected static string $jsonFile;

    protected static function repository(): JsonRepository
    {
        return new JsonRepository(
            static::$jsonFile
        );
    }
}
