<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Agenda')->searchable()->limit(50),
                TextColumn::make('start_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('price')->label('Harga')->formatStateUsing(fn ($state) => $state ? 'Rp '.number_format($state, 0, ',', '.') : 'Gratis'),
                TextColumn::make('registrations_count')->label('Pendaftar')->counts('registrations')
                    ->formatStateUsing(fn ($state, $record) => $record->quota ? "{$state} / {$record->quota}" : $state),
                IconColumn::make('registration_open')->label('Buka')->boolean(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
            ])
            ->defaultSort('start_at', 'desc')
            ->filters([
                Filter::make('upcoming')->label('Akan datang')->query(fn (Builder $q) => $q->where('start_at', '>=', now()->startOfDay())),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
