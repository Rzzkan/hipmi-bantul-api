<?php

namespace App\Filament\Pages;

use App\Filament\Support\CmsFields;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Payload "Globals" (Header, Footer, Site settings) in one page.
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan Situs';

    protected static ?string $title = 'Pengaturan Situs';

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site' => Setting::get('site', []),
            'header' => Setting::get('header', []),
            'footer' => Setting::get('footer', []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('globals')->tabs([
                    Tab::make('Identitas & Kontak')->icon(Heroicon::OutlinedIdentification)->schema([
                        Grid::make(2)->schema([
                            TextInput::make('site.name')->label('Nama organisasi')->default('BPC HIPMI Bantul')->required(),
                            TextInput::make('site.tagline')->label('Tagline'),
                            TextInput::make('site.email')->label('Email')->email(),
                            TextInput::make('site.phone')->label('Telepon'),
                            TextInput::make('site.whatsapp')->label('WhatsApp (format 62...)')->placeholder('6281234567890'),
                            TextInput::make('site.maps_url')->label('Link Google Maps')->url(),
                        ]),
                        Textarea::make('site.address')->label('Alamat sekretariat')->rows(2),
                        Repeater::make('site.socials')->label('Media sosial')->schema([
                            Select::make('platform')->options([
                                'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
                                'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X / Twitter',
                            ])->required()->native(false),
                            TextInput::make('url')->url()->required(),
                        ])->columns(2)->defaultItems(0),
                        Grid::make(2)->schema([
                            CmsFields::image('site.logo', 'Logo', 'site', 'logo'),
                            CmsFields::image('site.meta_image', 'Gambar share default (OG)', 'site', 'og'),
                        ]),
                    ]),
                    Tab::make('Header')->icon(Heroicon::OutlinedBars3)->schema([
                        CmsFields::links('header.nav_items', 8)->label('Menu navigasi'),
                        Grid::make(4)->statePath('header.cta')->schema(CmsFields::linkFields())
                            ->columnSpanFull(),
                    ]),
                    Tab::make('Footer')->icon(Heroicon::OutlinedRectangleGroup)->schema([
                        Textarea::make('footer.about')->label('Deskripsi singkat')->rows(3),
                        CmsFields::links('footer.nav_items', 10)->label('Link footer'),
                        TextInput::make('footer.copyright')->label('Teks copyright')->placeholder('© BPC HIPMI Bantul'),
                    ]),
                ])->persistTabInQueryString(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['site', 'header', 'footer'] as $key) {
            Setting::put($key, $state[$key] ?? []);
        }

        Notification::make()->success()->title('Pengaturan tersimpan')->send();
    }
}
