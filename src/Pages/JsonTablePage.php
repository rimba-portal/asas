<?php

declare(strict_types=1);

namespace Rimba\Base\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Rimba\Base\Services\JsonStore;
use RuntimeException;
use Throwable;

abstract class JsonTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'bites::pages.json-table';

    protected static string $store;

    abstract protected static function sourcePath(): string;

    public static function repository(): JsonStore
    {
        return new JsonStore(
            store: static::$store,
            source: static::sourcePath(),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(
                fn (): array => static::repository()->all()
            )
            ->recordUrl(
                fn (array $record): ?string => filled($record['url'] ?? null)
                        ? $record['url']
                        : null
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->weight('medium'),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),

                TextColumn::make('url')
                    ->label('URL')
                    ->copyable()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(
                        fn (array $record): array => $record
                    )
                    ->schema(
                        fn (Schema $schema): Schema => $this->recordForm($schema)
                    )
                    ->action(
                        function (
                            array $data,
                            array $record,
                        ): void {
                            try {
                                static::repository()->update(
                                    key: $record['key'],
                                    data: $data,
                                );

                                $this->resetTable();

                                Notification::make()
                                    ->title('Record updated')
                                    ->success()
                                    ->send();
                            } catch (Throwable $throwable) {
                                $this->sendErrorNotification($throwable);
                            }
                        }
                    ),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(
                        function (array $record): void {
                            try {
                                static::repository()->delete(
                                    $record['key']
                                );

                                $this->resetTable();

                                Notification::make()
                                    ->title('Record deleted')
                                    ->success()
                                    ->send();
                            } catch (Throwable $throwable) {
                                $this->sendErrorNotification($throwable);
                            }
                        }
                    ),
            ])
            ->emptyStateHeading('No records')
            ->emptyStateDescription(
                'Create the first record using the button above.'
            )
            ->emptyStateIcon('heroicon-o-document-text');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create')
                ->icon('heroicon-o-plus')
                ->schema(
                    fn (Schema $schema): Schema => $this->recordForm($schema)
                )
                ->action(function (array $data): void {
                    try {
                        static::repository()->create($data);

                        $this->resetTable();

                        Notification::make()
                            ->title('Record created')
                            ->success()
                            ->send();
                    } catch (Throwable $throwable) {
                        $this->sendErrorNotification($throwable);
                    }
                }),
        ];
    }

    protected function recordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Key')
                    ->required()
                    ->maxLength(100)
                    ->helperText(
                        'Permanent unique identifier, for example: people'
                    )
                    ->dehydrateStateUsing(
                        static fn (mixed $state): string => Str::slug((string) $state)
                    ),

                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('description')
                    ->label('Description')
                    ->maxLength(500),

                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->maxLength(2048)
                    ->placeholder('/staff/people'),
            ])
            ->columns(1);
    }

    protected function sendErrorNotification(
        Throwable $exception
    ): void {
        report($exception);

        Notification::make()
            ->title('Unable to save record')
            ->body(
                $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'An unexpected error occurred.'
            )
            ->danger()
            ->send();
    }
}
