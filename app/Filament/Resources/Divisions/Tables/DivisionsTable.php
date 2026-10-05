<?php

namespace App\Filament\Resources\Divisions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DivisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->prefix('Bidang ')->sortable(),
                TextColumn::make('name')->label('Nama bidang')->searchable()->wrap(),
                TextColumn::make('compartments_count')->label('Kompartemen')->counts('compartments')->badge(),
                TextColumn::make('members_count')->label('Pengurus')->counts('members')->badge()->color('gray'),
                ToggleColumn::make('is_active')->label('Tampil'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
