<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\BoardMember;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Mirrors Payload's `Pages` collection: tabs Hero / Content (layout blocks) / SEO.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make()->schema([
                        CmsFields::title('Judul halaman'),
                        CmsFields::slug('/')->helperText('Gunakan "home" untuk halaman beranda.'),
                    ]),
                    Tabs::make('page')->tabs([
                        Tab::make('Hero')->icon(Heroicon::OutlinedSparkles)->schema(static::hero()),
                        Tab::make('Konten')->icon(Heroicon::OutlinedSquares2x2)->schema([static::layout()]),
                        Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                            TextInput::make('meta_title')->label('Meta title')->maxLength(70),
                            Textarea::make('meta_description')->label('Meta description')->maxLength(160)->rows(3),
                            CmsFields::image('meta_image', 'Gambar share (OG image)', 'seo'),
                        ]),
                    ])->persistTabInQueryString(),
                ])->columnSpan(2),
                Group::make([CmsFields::publishSection()])->columnSpan(1),
            ]);
    }

    protected static function hero(): array
    {
        return [
            Select::make('hero.type')
                ->label('Tipe hero')
                ->options([
                    'none' => 'Tanpa hero',
                    'highImpact' => 'High impact (gambar penuh layar)',
                    'mediumImpact' => 'Medium impact (teks + gambar)',
                    'lowImpact' => 'Low impact (teks saja)',
                ])
                ->default('lowImpact')
                ->native(false)
                ->live(),
            TextInput::make('hero.eyebrow')->label('Label kecil di atas judul')->placeholder('BPC HIPMI Bantul')
                ->visible(fn (Get $get) => $get('hero.type') !== 'none'),
            CmsFields::richText('hero.richText', 'Teks hero')
                ->helperText('Gunakan Heading 2 untuk judul. Tebalkan (Bold) sebagian kata judul untuk efek gradasi emas → hijau.')
                ->visible(fn (Get $get) => $get('hero.type') !== 'none'),
            CmsFields::links('hero.links', 2)
                ->visible(fn (Get $get) => $get('hero.type') !== 'none'),
            CmsFields::image('hero.media', 'Gambar hero', 'hero')
                ->visible(fn (Get $get) => in_array($get('hero.type'), ['highImpact', 'mediumImpact'])),
        ];
    }

    protected static function layout(): Builder
    {
        return Builder::make('layout')
            ->label('Layout blocks')
            ->blocks([
                Block::make('content')
                    ->label('Konten (kolom)')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        Select::make('background')->label('Latar')->options([
                            'default' => 'Default', 'muted' => 'Abu-abu lembut', 'brand' => 'Warna brand',
                        ])->default('default')->native(false),
                        Repeater::make('columns')
                            ->label('Kolom')
                            ->schema([
                                Select::make('size')->label('Lebar')->options([
                                    'full' => 'Penuh', 'half' => '1/2', 'oneThird' => '1/3', 'twoThirds' => '2/3',
                                ])->default('full')->native(false),
                                CmsFields::richText('richText', 'Isi'),
                                Toggle::make('enableLink')->label('Tambah tombol')->live(),
                                Grid::make(4)->statePath('link')->schema(CmsFields::linkFields())
                                    ->visible(fn (Get $get) => (bool) $get('enableLink')),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->collapsible(),
                    ]),
                Block::make('mediaBlock')
                    ->label('Gambar')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        CmsFields::image('media', 'Gambar', 'blocks')->required(),
                        TextInput::make('caption')->label('Keterangan'),
                    ]),
                Block::make('cta')
                    ->label('Call to Action')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->schema([
                        CmsFields::richText('richText', 'Teks'),
                        CmsFields::links('links', 2),
                    ]),
                Block::make('stats')
                    ->label('Angka / Statistik')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->schema([
                        CmsFields::richText('introContent', 'Intro'),
                        Repeater::make('items')->label('Angka')->schema([
                            TextInput::make('value')->label('Angka')->placeholder('120+')->required(),
                            TextInput::make('label')->label('Keterangan')->placeholder('Anggota aktif')->required(),
                        ])->columns(2)->maxItems(6)->defaultItems(3),
                    ]),
                Block::make('archive')
                    ->label('Daftar konten (Berita/Agenda/Program)')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->schema([
                        CmsFields::richText('introContent', 'Intro'),
                        Grid::make(3)->schema([
                            Select::make('relationTo')->label('Tampilkan')->options([
                                'posts' => 'Berita terbaru',
                                'events' => 'Agenda mendatang',
                                'programs' => 'Program',
                            ])->default('posts')->required()->native(false)->live(),
                            Select::make('category')->label('Filter kategori')
                                ->options(fn (Get $get) => match ($get('relationTo')) {
                                    'programs' => Program::CATEGORIES,
                                    'posts' => Post::CATEGORIES,
                                    default => [],
                                })
                                ->visible(fn (Get $get) => $get('relationTo') !== 'events')
                                ->native(false),
                            TextInput::make('limit')->label('Jumlah')->numeric()->minValue(1)->maxValue(12)->default(3),
                        ]),
                    ]),
                Block::make('team')
                    ->label('Pengurus')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        CmsFields::richText('introContent', 'Intro'),
                        Select::make('division')->label('Bidang (kosong = semua)')->options(BoardMember::DIVISIONS)->native(false),
                    ]),
                Block::make('partners')
                    ->label('Partner & Sponsor')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        CmsFields::richText('introContent', 'Intro'),
                        Select::make('tier')->label('Tier (kosong = semua)')->options(Partner::TIERS)->native(false),
                    ]),
                Block::make('form')
                    ->label('Formulir pendaftaran')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Select::make('formType')->label('Jenis formulir')->options([
                            'membership' => 'Pendaftaran anggota baru',
                            'event' => 'Pendaftaran agenda/event',
                            'program' => 'Pendaftaran program',
                        ])->default('membership')->required()->native(false),
                        CmsFields::richText('introContent', 'Intro'),
                        Textarea::make('successMessage')->label('Pesan setelah submit')->rows(2),
                    ]),
                Block::make('faq')
                    ->label('FAQ')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->schema([
                        CmsFields::richText('introContent', 'Intro'),
                        Repeater::make('items')->label('Pertanyaan')->schema([
                            TextInput::make('question')->label('Pertanyaan')->required(),
                            Textarea::make('answer')->label('Jawaban')->rows(3)->required(),
                        ])->collapsible()->itemLabel(fn (array $state) => $state['question'] ?? null),
                    ]),
            ])
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->reorderableWithButtons()
            ->addActionLabel('Tambah block');
    }
}
