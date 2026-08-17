<?php

declare(strict_types=1);

namespace Rimba\Base\Resources\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ManageJsonRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [

            Action::make('create')

                ->label('Create')

                ->schema(
                    $this->getResource()::form(
                        app(Schema::class)
                    )->getComponents()
                )

                ->action(function (array $data): void {

                    $this->getResource()::repository()
                        ->create($data);

                    Notification::make()
                        ->success()
                        ->title('Saved')
                        ->send();
                }),
        ];
    }
}
