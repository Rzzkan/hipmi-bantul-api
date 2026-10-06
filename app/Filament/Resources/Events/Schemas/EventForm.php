<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Support\CmsFields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make([
                Section::make()->schema([
                    CmsFields::title('Nama agenda'),
                    CmsFields::slug('/agenda/'),
                    Textarea::make('excerpt')->label('Ringkasan')->rows(2)->maxLength(300),
                    CmsFields::richText('description', 'Deskripsi lengkap'),
                ]),
                Section::make('Waktu & Tempat')->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('start_at')->label('Mulai')->required()->seconds(false),
                        DateTimePicker::make('end_at')->label('Selesai')->seconds(false)->after('start_at'),
                    ]),
                    TextInput::make('location')->label('Lokasi')->placeholder('Pendopo Parasamya, Bantul'),
                    TextInput::make('location_url')->label('Link Google Maps')->url(),
                ]),
            ])->columnSpan(2),
            Group::make([
                CmsFields::publishSection(),
                Section::make('Tiket & Pendaftaran')->schema([
                    TextInput::make('price')->label('Harga tiket')->numeric()->prefix('Rp')->default(0)
                        ->helperText('0 = gratis'),
                    TextInput::make('quota')->label('Kuota peserta')->numeric()->minValue(1)
                        ->helperText('Kosongkan jika tidak dibatasi'),
                    Toggle::make('registration_open')->label('Pendaftaran dibuka')->default(true),
                ]),
                Section::make()->schema([CmsFields::image('cover_image', 'Poster / sampul', 'events', 'poster')]),
            ])->columnSpan(1),
        ]);
    }
}
