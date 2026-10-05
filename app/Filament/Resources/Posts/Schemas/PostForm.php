<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Support\CmsFields;
use App\Models\Post;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make([
                Section::make()->schema([
                    CmsFields::title(),
                    CmsFields::slug('/berita/'),
                    Textarea::make('excerpt')->label('Ringkasan')->rows(3)->maxLength(300),
                    CmsFields::richText('content', 'Isi berita')->required(),
                ]),
                Section::make('SEO')->collapsed()->schema([
                    TextInput::make('meta_title')->maxLength(70),
                    Textarea::make('meta_description')->maxLength(160)->rows(2),
                ]),
            ])->columnSpan(2),
            Group::make([
                CmsFields::publishSection(),
                Section::make('Detail')->schema([
                    Select::make('category')->label('Kategori')->options(Post::CATEGORIES)->default('berita')->required()->native(false),
                    TextInput::make('author_name')->label('Penulis')->default(fn () => auth()->user()?->name),
                    CmsFields::image('cover_image', 'Gambar sampul', 'posts'),
                ]),
            ])->columnSpan(1),
        ]);
    }
}
