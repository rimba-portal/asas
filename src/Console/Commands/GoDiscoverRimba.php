<?php

namespace Rimba\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Rimba\Base\Actions\DiscoverRimbaPackages;

#[Signature('bites:discover-rimba')]
#[Description('Clear and instantly rebuild the Rimba package discovery cache')]
class RecacheRimbaPackagesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscoverRimbaPackages $discoverer): int
    {
        $this->info('Scanning filesystem and rebuilding Rimba cache...');

        // Run the action with forceRefresh set to true
        $packages = $discoverer->execute(forceRefresh: true);

        if (empty($packages)) {
            $this->warn('No packages were found inside vendor/rimba.');

            return Command::SUCCESS;
        }

        // Format data nicely for display
        $rows = [];
        foreach ($packages as $package => $namespace) {
            $rows[] = [$package, $namespace];
        }

        $this->table(['Package String', 'Generated Namespace'], $rows);
        $this->info('🚀 Rimba cache successfully rebuilt!');

        return Command::SUCCESS;
    }
}
