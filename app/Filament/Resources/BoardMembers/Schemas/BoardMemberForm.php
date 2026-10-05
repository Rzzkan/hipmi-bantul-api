<?php

namespace App\Filament\Resources\BoardMembers\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\BoardMember;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BoardMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Data pengurus')->columnSpan(2)->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('Nama lengkap')->required(),
                    TextInput::make('position')->label('Jabatan')->required()->placeholder('Ketua Bidang OKK'),
                    Select::make('division')->label('Bidang')->options(BoardMember::DIVISIONS)->native(false),
                    TextInput::make('company')->label('Perusahaan / Usaha'),
                    TextInput::make('instagram')->label('Instagram')->prefix('@'),
                    TextInput::make('linkedin')->label('LinkedIn URL')->url(),
                ]),
                Textarea::make('bio')->label('Bio singkat')->rows(3)->maxLength(500),
            ]),
            Section::make()->columnSpan(1)->schema([
                CmsFields::image('photo', 'Foto', 'board')->avatar()->imageCropAspectRatio('1:1'),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }
}
