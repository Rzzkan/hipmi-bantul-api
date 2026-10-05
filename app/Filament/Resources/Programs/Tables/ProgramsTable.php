<?php

namespace App\Filament\Resources\Programs\Tables;

use App\Models\Program;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Program')->searchable(),
                TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(fn ($state) => Program::CATEGORIES[$state] ?? $state),
                TextColumn::make('registrations_count')->label('Pendaftar')->counts('registrations'),
                IconColumn::make('is_featured')->label('Unggulan')->boolean(),
                IconColumn::make('registration_open')->label('Buka')->boolean(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([SelectFilter::make('category')->options(Program::CATEGORIES)])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
