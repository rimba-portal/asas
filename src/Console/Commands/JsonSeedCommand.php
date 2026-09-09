<?php

declare(strict_types=1);

namespace Rimba\Base\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Rimba\Base\Support\JsonSeedThruModel;

#[Signature('rimba:seed-json {path?}')]
class JsonSeedCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path')
            ?? database_path('seeds');

        $jsonSeedThruModel = new JsonSeedThruModel($path);
        $jsonSeedThruModel->setContainer(app());
        $jsonSeedThruModel->setCommand($this);

        $jsonSeedThruModel->run();

        return self::SUCCESS;
    }
}
