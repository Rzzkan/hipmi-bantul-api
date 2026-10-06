<?php

namespace App\Filament\Support;

use App\Support\ImageOptimizer;
use App\Support\Media;
use App\Support\RemoteImage;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Reusable field groups — the Laravel/Filament equivalent of Payload's
 * shared `fields/` folder (link, linkGroup, slugField, publishing sidebar).
 */
class CmsFields
{
    public static function title(string $label = 'Judul'): TextInput
    {
        return TextInput::make('title')
            ->label($label)
            ->required()
            ->maxLength(200)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                if ($operation === 'create' || blank($get('slug'))) {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    public static function slug(string $prefix = '/'): TextInput
    {
        return TextInput::make('slug')
            ->label('Slug (URL)')
            ->prefix($prefix)
            ->helperText('Otomatis dari judul. Gunakan huruf kecil & tanda hubung.')
            ->alphaDash()
            ->maxLength(200)
            ->unique(ignoreRecord: true);
    }

    public static function richText(string $name = 'richText', string $label = 'Konten'): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3', 'blockquote'],
                ['bulletList', 'orderedList'],
                ['attachFiles', 'table'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(Media::diskName())
            ->fileAttachmentsDirectory('editor')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(ImageOptimizer::ACCEPTED_MIME)
            ->fileAttachmentsMaxSize(ImageOptimizer::MAX_UPLOAD_KB)
            // compress images inserted in articles (and avoid setVisibility, which R2 doesn't support)
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file) => ImageOptimizer::store($file, 'editor', 'editor'));
    }

    /**
     * Image field: upload from the computer OR fetch from a URL ("Ambil dari URL").
     * Every image is compressed server-side with a preset sized for where it is shown
     * (see App\Support\ImageOptimizer::PRESETS) before it is stored on the media disk (R2).
     */
    public static function image(string $name, string $label, string $directory, string $preset = 'cover'): FileUpload
    {
        $p = ImageOptimizer::preset($preset);
        $size = $p['fit'] === 'cover' ? "{$p['width']}×{$p['height']} px" : "maks. {$p['width']}px";
        $accepted = $preset === 'logo' ? [...ImageOptimizer::ACCEPTED_MIME, 'image/svg+xml'] : ImageOptimizer::ACCEPTED_MIME;

        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->imageEditor()
            ->acceptedFileTypes($accepted)
            ->disk(Media::diskName())
            ->directory($directory)
            ->visibility('public')
            ->maxSize(ImageOptimizer::MAX_UPLOAD_KB)
            ->helperText('Pilih dari komputer/HP atau klik "Ambil dari URL". Otomatis dikompres ke '.strtoupper($p['format'])." {$size}.")
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => ImageOptimizer::store($file, $preset, $directory))
            ->hintAction(
                Action::make('fromUrl')
                    ->label('Ambil dari URL')
                    ->icon(Heroicon::OutlinedLink)
                    ->modalHeading("{$label}: ambil dari URL")
                    ->modalDescription('Tempel link gambar (termasuk link Google Drive/Dropbox yang dibagikan publik). Gambar akan diunduh, dikompres, lalu disimpan di penyimpanan website.')
                    ->modalSubmitActionLabel('Ambil gambar')
                    ->schema([
                        TextInput::make('url')->label('URL gambar')->url()->required()->placeholder('https://…/foto.jpg'),
                    ])
                    ->action(function (array $data, FileUpload $component) use ($preset, $directory) {
                        try {
                            $path = ImageOptimizer::store(RemoteImage::fetch($data['url']), $preset, $directory);
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal mengambil gambar')
                                ->body($e instanceof \InvalidArgumentException ? $e->getMessage() : 'Periksa kembali URL-nya.')->send();

                            return;
                        }

                        $component->state([(string) Str::uuid() => $path]);
                        Notification::make()->success()->title('Gambar berhasil diambil & dikompres')->send();
                    }),
            );
    }

    /** Payload `link` field (custom URL only, internal paths allowed e.g. /agenda). */
    public static function linkFields(): array
    {
        return [
            TextInput::make('label')->label('Teks tombol')->required(),
            TextInput::make('url')->label('URL')->required()->placeholder('/daftar atau https://...'),
            Select::make('appearance')->label('Gaya')->options([
                'default' => 'Emas (utama)',
                'accent' => 'Hijau',
                'outline' => 'Outline emas',
            ])->default('default')->native(false),
            Toggle::make('newTab')->label('Buka di tab baru')->inline(false),
        ];
    }

    /** Payload `linkGroup`. */
    public static function links(string $name = 'links', int $max = 3): Repeater
    {
        return Repeater::make($name)
            ->label('Tombol / Link')
            ->schema([Grid::make(4)->schema(static::linkFields())])
            ->maxItems($max)
            ->defaultItems(0)
            ->collapsible()
            ->itemLabel(fn (array $state) => $state['label'] ?? null);
    }

    /** Sidebar "Publish" section (Payload drafts & publishedAt). */
    public static function publishSection(): Section
    {
        return Section::make('Publikasi')
            ->schema([
                Select::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published'])
                    ->default('draft')
                    ->required()
                    ->native(false),
                DateTimePicker::make('published_at')
                    ->label('Tanggal terbit')
                    ->helperText('Kosongkan = otomatis saat dipublish. Isi tanggal ke depan untuk jadwal terbit.')
                    ->seconds(false),
            ]);
    }
}
