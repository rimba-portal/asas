<?php

declare(strict_types=1);

namespace Rimba\Base\Contracts;

interface AfterJsonSeed
{
    public function afterJsonSeed(array $payload): void;
}
