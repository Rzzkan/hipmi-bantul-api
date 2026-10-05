<?php

namespace App\Filament\Resources\BoardMembers\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\BoardMember;
use App\Models\Compartment;
use App\Models\Division;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BoardMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Posisi dalam struktur')->columnSpan(2)->schema([
                Grid::make(2)->schema([
                    Select::make('level')
                        ->label('Tingkat')
                        ->options(BoardMember::LEVELS)
                        ->default(BoardMember::LEVEL_INTI)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('compartment_id', null);
                            $set('position', null);
                        }),
                    TextInput::make('position')
                        ->label('Jabatan')
                        ->required()
                        ->datalist(fn (Get $get) => BoardMember::POSITION_SUGGESTIONS[$get('level')] ?? [])
                        ->placeholder(fn (Get $get) => BoardMember::POSITION_SUGGESTIONS[$get('level')][0] ?? 'Jabatan'),
                    Select::make('division_id')
                        ->label('Bidang')
                        ->options(fn () => Division::query()->ordered()->get()->mapWithKeys(fn ($d) => [$d->id => $d->label]))
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('compartment_id', null))
                        ->visible(fn (Get $get) => $get('level') !== BoardMember::LEVEL_INTI)
                        ->required(fn (Get $get) => $get('level') !== BoardMember::LEVEL_INTI),
                    Select::make('compartment_id')
                        ->label('Kompartemen')
                        ->options(fn (Get $get) => Compartment::query()
                            ->where('division_id', $get('division_id'))
                            ->orderBy('sort_order')->pluck('name', 'id'))
                        ->native(false)
                        ->helperText(fn (Get $get) => $get('division_id') ? 'Belum ada? Tambahkan di menu Bidang & Kompartemen.' : 'Pilih bidang dulu.')
                        ->visible(fn (Get $get) => $get('level') === BoardMember::LEVEL_KOMPARTEMEN)
                        ->required(fn (Get $get) => $get('level') === BoardMember::LEVEL_KOMPARTEMEN),
                ]),
            ]),
            Section::make()->columnSpan(1)->schema([
                CmsFields::image('photo', 'Foto', 'board')->avatar()->imageCropAspectRatio('1:1'),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                Toggle::make('is_active')->label('Tampilkan di website')->default(true),
            ]),
            Section::make('Data pengurus')->columnSpan(2)->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Nama lengkap')->required(),
                    TextInput::make('company')->label('Perusahaan / Usaha'),
                ]),
                Textarea::make('bio')->label('Bio singkat')->rows(3)->maxLength(500),
            ]),
            Section::make('Media sosial')
                ->description('Boleh isi @username atau link lengkap.')
                ->columnSpan(2)
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('instagram')->label('Instagram')->placeholder('@username'),
                        TextInput::make('tiktok')->label('TikTok')->placeholder('@username'),
                        TextInput::make('linkedin')->label('LinkedIn')->placeholder('https://linkedin.com/in/…'),
                    ]),
                ]),
        ]);
    }
}
