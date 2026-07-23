<?php

declare(strict_types=1);

namespace Rimba\Base\Actions;

class PutFingerPrint
{
    public static function make(array $payload): string
    {
        return sha1(json_encode($payload));
    }
}
