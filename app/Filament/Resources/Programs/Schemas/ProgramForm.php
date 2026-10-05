<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\Program;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make([
                Section::make()->schema([
                    CmsFields::title('Nama program'),
                    CmsFields::slug('/program/'),
                    Textarea::make('summary')->label('Ringkasan')->rows(2)->maxLength(300),
                    CmsFields::richText('description', 'Deskripsi lengkap'),
                    Repeater::make('benefits')->label('Benefit untuk peserta')
                        ->simple(TextInput::make('item')->required())
                        ->defaultItems(0),
                ]),
            ])->columnSpan(2),
            Group::make([
                CmsFields::publishSection(),
                Section::make('Pengaturan')->schema([
                    Select::make('category')->label('Kategori')->options(Program::CATEGORIES)->default('okk')->required()->native(false),
                    TextInput::make('price')->label('Biaya')->numeric()->prefix('Rp')->default(0)->helperText('0 = gratis'),
                    Toggle::make('registration_open')->label('Pendaftaran dibuka')->default(true),
                    Toggle::make('is_featured')->label('Unggulan'),
                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                    CmsFields::image('cover_image', 'Gambar sampul', 'programs'),
                ]),
            ])->columnSpan(1),
        ]);
    }
}
