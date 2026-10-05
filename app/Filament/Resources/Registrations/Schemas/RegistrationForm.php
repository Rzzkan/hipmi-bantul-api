<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Data pendaftar')->columnSpan(2)->schema([
                Grid::make(2)->schema([
                    Select::make('type')->label('Jenis')->options(Registration::TYPES)->disabled(),
                    Select::make('event_id')->label('Agenda')->relationship('event', 'title')->disabled()
                        ->visible(fn ($record) => $record?->type === 'event'),
                    Select::make('program_id')->label('Program')->relationship('program', 'title')->disabled()
                        ->visible(fn ($record) => $record?->type === 'program'),
                    TextInput::make('name')->label('Nama'),
                    TextInput::make('email')->email(),
                    TextInput::make('phone')->label('No. HP / WA'),
                    TextInput::make('company_name')->label('Nama usaha'),
                    TextInput::make('business_field')->label('Bidang usaha'),
                    TextInput::make('position')->label('Jabatan di usaha'),
                    TextInput::make('age')->label('Usia')->numeric(),
                ]),
                Textarea::make('address')->label('Alamat')->rows(2),
                Textarea::make('message')->label('Pesan / motivasi')->rows(3),
            ]),
            Section::make('Tindak lanjut')->columnSpan(1)->schema([
                Select::make('status')->options(Registration::STATUSES)->required()->native(false),
                Textarea::make('admin_notes')->label('Catatan internal')->rows(5),
            ]),
        ]);
    }
}
