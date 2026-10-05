<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

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
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory('editor');
    }

    public static function image(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->imageEditor()
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->maxSize(4096);
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
