<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\Partner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('url')->label('Website')->url(),
                Select::make('tier')->options(Partner::TIERS)->default('partner')->required()->native(false),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                CmsFields::image('logo', 'Logo', 'partners', 'logo'),
                Toggle::make('is_active')->label('Tampilkan')->default(true),
            ]),
        ]);
    }
}
