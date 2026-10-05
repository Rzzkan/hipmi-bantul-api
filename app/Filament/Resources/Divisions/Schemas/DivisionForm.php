<?php

namespace App\Filament\Resources\Divisions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DivisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Bidang')->columnSpan(2)->schema([
                Grid::make(4)->schema([
                    TextInput::make('number')->label('Nomor')->numeric()->minValue(1)->prefix('Bidang')->columnSpan(1),
                    TextInput::make('name')->label('Nama bidang')->required()->maxLength(200)->columnSpan(3),
                ]),
                Textarea::make('description')->label('Deskripsi singkat (opsional)')->rows(2),
                Repeater::make('compartments')
                    ->label('Kompartemen')
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->schema([TextInput::make('name')->label('Nama kompartemen')->required()])
                    ->addActionLabel('Tambah kompartemen')
                    ->defaultItems(0)
                    ->reorderable()
                    ->helperText('Satu bidang bisa punya lebih dari satu kompartemen. Pengurus kompartemen diatur di menu Pengurus.'),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                Toggle::make('is_active')->label('Tampilkan di website')->default(true),
            ]),
        ]);
    }
}
