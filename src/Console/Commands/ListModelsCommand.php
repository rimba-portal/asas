<?php

declare(strict_types=1);

namespace Bites\Base\Console\Commands;

use Bites\Base\Services\GetModelInfo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bites:list-models')]
#[Description('Command description')]
class ListModelsCommand extends Command
{
    public function handle(): int
    {
        $this->info('Discovered Models');
        $this->newLine();

        foreach (GetModelInfo::tableMap() as $table => $model) {
            $this->line(sprintf(
                '%-40s %s',
                $table,
                $model
            ));
        }

        return self::SUCCESS;
    }
}
