<?php

declare(strict_types=1);

namespace Rimba\Base\Support;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class LinkViewResolver
{
    public static function attach(
        Table $table,
        string $resourceClass,
        string $pathColumn = 'file_path',
    ): Table {
        return $table
            ->recordUrl(
                fn (Model $record)
                    => Str::startsWith($record->{$pathColumn}, ['http://', 'https://'])
                        ? $record->{$pathColumn}
                        : $resourceClass::getUrl('view', ['record' => $record])
            )
            ->openRecordUrlInNewTab(
                fn (Model $record)
                    => Str::startsWith($record->{$pathColumn}, ['http://', 'https://'])
            );
    }
}