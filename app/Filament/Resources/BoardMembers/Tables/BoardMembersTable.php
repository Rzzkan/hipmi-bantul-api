<?php

namespace App\Filament\Resources\BoardMembers\Tables;

use App\Models\BoardMember;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BoardMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('position')->label('Jabatan')->searchable(),
                TextColumn::make('division')->label('Bidang')->badge()->formatStateUsing(fn ($state) => BoardMember::DIVISIONS[$state] ?? $state),
                TextColumn::make('company')->label('Usaha')->toggleable(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([SelectFilter::make('division')->label('Bidang')->options(BoardMember::DIVISIONS)])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
