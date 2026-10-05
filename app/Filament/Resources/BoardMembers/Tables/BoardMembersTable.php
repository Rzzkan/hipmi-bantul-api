<?php

namespace App\Filament\Resources\BoardMembers\Tables;

use App\Models\BoardMember;
use App\Models\Division;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class BoardMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular(),
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (BoardMember $m) => $m->company),
                TextColumn::make('position')->label('Jabatan')->searchable(),
                TextColumn::make('division.name')->label('Bidang')->wrap()->toggleable()
                    ->description(fn (BoardMember $m) => $m->compartment?->name ? 'Kompartemen '.$m->compartment->name : null),
                TextColumn::make('socials')->label('Sosmed')
                    ->state(fn (BoardMember $m) => collect(['IG' => $m->instagram, 'TikTok' => $m->tiktok, 'in' => $m->linkedin])->filter()->keys()->implode(' · ') ?: '—')
                    ->color('gray'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->groups([
                Group::make('level')->label('Tingkat')
                    ->getTitleFromRecordUsing(fn (BoardMember $m) => BoardMember::LEVELS[$m->level] ?? $m->level),
                Group::make('division.name')->label('Bidang'),
            ])
            ->defaultGroup('level')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('level')->label('Tingkat')->options(BoardMember::LEVELS),
                SelectFilter::make('division_id')->label('Bidang')
                    ->options(fn () => Division::query()->ordered()->get()->mapWithKeys(fn ($d) => [$d->id => $d->label])),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
