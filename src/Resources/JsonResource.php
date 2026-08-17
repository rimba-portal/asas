<?php

declare(strict_types=1);

namespace Rimba\Base\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rimba\Base\Resources\Pages\ManageJsonRecords;
use Rimba\Base\Services\JsonStore;
use Rimba\Foundation\FoundationServiceProvider;

abstract class JsonResource extends Resource
{
    protected static string $store;

    public static function repository(): JsonStore
    {
        return new JsonStore(
            store: static::$store,
            source: FoundationServiceProvider::jsonPath(
                static::$store
            ),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('description'),

                TextInput::make('url')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(
                fn (array $record) => $record['url']
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('description')
                    ->wrap(),

                TextColumn::make('url')
                    ->copyable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJsonRecords::route('/'),
        ];
    }
}
