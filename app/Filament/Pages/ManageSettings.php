<?php

namespace App\Filament\Pages;

use App\Filament\Support\CmsFields;
use App\Models\Setting;
use App\Support\Media;
use App\Support\MediaPaths;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
            'media' => ['public_url' => Setting::get(MediaPaths::SETTING_KEY, [])['public_url'] ?? null],
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
                    Tab::make('Media (R2)')->icon(Heroicon::OutlinedPhoto)->schema([
                        Callout::make(fn () => Media::diskName() === 'r2' ? 'Gambar disimpan di Cloudflare R2' : 'Gambar masih disimpan di server (MEDIA_DISK='.Media::diskName().')')
                            ->description(fn () => Media::diskName() === 'r2'
                                ? 'Database hanya menyimpan path gambar (mis. posts/01jabc.webp). URL lengkap dibentuk dari Base URL di bawah, jadi mengganti domain/bucket cukup ubah di sini.'
                                : 'Base URL R2 baru dipakai setelah MEDIA_DISK=r2 di file .env server. Path gambar tetap tersimpan tanpa domain.')
                            ->{Media::diskName() === 'r2' ? 'info' : 'warning'}(),
                        TextInput::make('media.public_url')
                            ->label('Base URL publik bucket R2')
                            ->url()
                            ->placeholder(fn () => config('filesystems.disks.r2.url') ?: 'https://media.hipmibantul.com')
                            ->helperText(fn () => 'Custom domain (disarankan, mis. https://media.hipmibantul.com) atau https://pub-xxxx.r2.dev — tanpa nama folder. '
                                .'Kosongkan untuk memakai R2_PUBLIC_URL dari .env ('.(env('R2_PUBLIC_URL') ?: 'belum diisi').'). '
                                .'Folder di bucket: "'.trim((string) config('filesystems.disks.r2.root'), '/').'/" ditambahkan otomatis.')
                            ->suffixAction(
                                Action::make('testMediaUrl')
                                    ->label('Tes')
                                    ->icon(Heroicon::OutlinedSignal)
                                    ->tooltip('Upload file uji ke R2 lalu buka lewat Base URL ini')
                                    ->action(fn ($state) => $this->testMediaUrl($state)),
                            ),
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

        $this->saveMediaSettings($state['media']['public_url'] ?? null);

        Notification::make()->success()->title('Pengaturan tersimpan')->send();
    }

    /** Keeps old base URLs so stray full URLs from earlier can still be converted back to paths. */
    protected function saveMediaSettings(?string $url): void
    {
        $current = Setting::get(MediaPaths::SETTING_KEY, []);
        $old = $current['public_url'] ?? config('filesystems.disks.r2.url');
        $url = filled($url) ? rtrim($url, '/') : null;

        if (($current['public_url'] ?? null) === $url) {
            return;
        }

        $previous = collect($current['previous_urls'] ?? [])->push($old)->filter()->reject(fn ($u) => $u === $url)->unique()->values()->all();
        Setting::put(MediaPaths::SETTING_KEY, ['public_url' => $url, 'previous_urls' => $previous]);
        config(['filesystems.disks.r2.url' => $url ?: env('R2_PUBLIC_URL')]);
        MediaPaths::applyConfiguredBaseUrl();
    }

    /** Writes a small file to the bucket and fetches it through the given public base URL. */
    public function testMediaUrl(?string $baseUrl): void
    {
        $baseUrl = rtrim((string) ($baseUrl ?: config('filesystems.disks.r2.url')), '/');
        if ($baseUrl === '') {
            Notification::make()->warning()->title('Isi Base URL dulu')->send();

            return;
        }

        $disk = Media::diskName();
        $probe = '_cek-media-'.Str::lower(Str::random(8)).'.txt';
        $token = Str::random(24);
        $root = $disk === 'r2' ? trim((string) config('filesystems.disks.r2.root'), '/') : '';
        $url = $disk === 'r2' ? $baseUrl.'/'.($root !== '' ? $root.'/' : '').$probe : Storage::disk($disk)->url($probe);

        try {
            Storage::disk($disk)->put($probe, $token, ['ContentType' => 'text/plain']);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Gagal menulis ke penyimpanan')->body('Cek R2_ACCESS_KEY_ID / SECRET / BUCKET / ENDPOINT di .env. '.Str::limit($e->getMessage(), 160))->persistent()->send();

            return;
        }

        try {
            $response = Http::timeout(10)->get($url);
            $ok = $response->successful() && trim($response->body()) === $token;
        } catch (Throwable) {
            $ok = false;
            $response = null;
        } finally {
            rescue(fn () => Storage::disk($disk)->delete($probe), report: false);
        }

        $ok
            ? Notification::make()->success()->title('Base URL berfungsi')->body("Gambar akan tampil dari {$baseUrl}")->send()
            : Notification::make()->danger()->title('File uji tidak bisa dibuka lewat Base URL')
                ->body('Dicoba: '.$url.($response ? ' (HTTP '.$response->status().')' : '').'. Pastikan Public access bucket aktif (custom domain / r2.dev) dan URL tanpa nama folder.')
                ->persistent()->send();
    }
}
