<?php

namespace Tests\Feature;

use App\Models\BoardMember;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Support\GoogleForm;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederSafetyAndEmbedTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = 'https://docs.google.com/forms/d/e/1FAIpQLSdqERlT-D7uhlWjWlvcO-KuzFbSDaG4djoAZwvpq6bvawqfeg/viewform';

    public function test_reseeding_never_overwrites_admin_edits(): void
    {
        $this->seed(ContentSeeder::class);

        $site = Setting::get('site');
        $site['name'] = 'BPC HIPMI Bantul (edit admin)';
        Setting::put('site', $site);
        Page::where('slug', 'home')->update(['title' => 'Beranda Baru']);
        BoardMember::where('position', 'Ketua Umum')->update(['name' => 'Nama Asli Ketum']);
        Post::query()->delete();

        $this->seed(ContentSeeder::class);

        $this->assertSame('BPC HIPMI Bantul (edit admin)', Setting::get('site')['name']);
        $this->assertSame('Beranda Baru', Page::where('slug', 'home')->value('title'));
        $this->assertSame('Nama Asli Ketum', BoardMember::where('position', 'Ketua Umum')->value('name'));
        $this->assertSame(1, BoardMember::where('position', 'Ketua Umum')->count());
        $this->assertSame(0, Post::count(), 'deleted sample posts must not come back');
    }

    public function test_daftar_page_embeds_google_form(): void
    {
        $this->seed(ContentSeeder::class);

        $layout = $this->getJson('/api/v1/pages/daftar')->assertOk()->json('data.layout');
        $this->assertSame(['embed', 'faq'], array_column($layout, 'blockType'));
        $this->assertSame(self::FORM.'?embedded=true', $layout[0]['embedUrl']);
        $this->assertSame(self::FORM, $layout[0]['openUrl']);
        $this->assertSame(1400, $layout[0]['height']);
    }

    public function test_migration_replaces_existing_membership_form_block(): void
    {
        // simulate a production page created before this change
        Page::create(['title' => 'Daftar', 'slug' => 'daftar', 'status' => 'published', 'layout' => [
            ['type' => 'form', 'data' => ['formType' => 'membership', 'introContent' => '<p>Intro lama</p>']],
            ['type' => 'faq', 'data' => ['items' => []]],
        ]]);

        $migration = require database_path('migrations/2026_10_06_000002_use_google_form_on_daftar_page.php');
        $migration->up();

        $layout = Page::where('slug', 'daftar')->first()->layout;
        $this->assertSame('embed', $layout[0]['type']);
        $this->assertSame(self::FORM, $layout[0]['data']['url']);
        $this->assertSame('<p>Intro lama</p>', $layout[0]['data']['introContent']);
        $this->assertSame('faq', $layout[1]['type']);

        $migration->down();
        $this->assertSame('form', Page::where('slug', 'daftar')->first()->layout[0]['type']);
    }

    public function test_google_form_url_handling(): void
    {
        $this->assertTrue(GoogleForm::isValid(self::FORM));
        $this->assertTrue(GoogleForm::isValid('https://forms.gle/AbC123xyz'));
        $this->assertFalse(GoogleForm::isValid('https://evil.example.com/forms/d/e/x/viewform'));
        $this->assertFalse(GoogleForm::isValid('javascript:alert(1)'));

        $this->assertSame(self::FORM.'?embedded=true', GoogleForm::embedUrl(self::FORM.'?usp=sharing'));
        $this->assertSame(self::FORM, GoogleForm::openUrl(self::FORM.'?embedded=true'));
        $this->assertSame('https://forms.gle/AbC123xyz', GoogleForm::embedUrl('https://forms.gle/AbC123xyz?x=1'));
    }

    public function test_invalid_embed_url_is_dropped_from_api(): void
    {
        Page::create(['title' => 'X', 'slug' => 'x', 'status' => 'published', 'layout' => [
            ['type' => 'embed', 'data' => ['url' => 'https://evil.example.com/a']],
        ]]);

        $this->assertSame([], $this->getJson('/api/v1/pages/x')->json('data.layout'));
    }
}
