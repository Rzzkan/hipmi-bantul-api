<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaPathsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filesystems.media_disk' => 'r2',
            'filesystems.disks.r2' => [
                'driver' => 'r2', 'key' => 'k', 'secret' => 's', 'bucket' => 'hipmi', 'region' => 'auto',
                'endpoint' => 'https://acc.r2.cloudflarestorage.com', 'url' => 'https://pub-old.r2.dev', 'root' => 'cms',
            ],
        ]);
        Storage::forgetDisk('r2');
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::create($attrs + ['title' => 'Uji', 'category' => 'berita', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    public function test_full_urls_are_stored_as_paths(): void
    {
        $post = $this->makePost([
            'cover_image' => 'https://pub-old.r2.dev/cms/posts/abc.webp',
            'content' => '<p>Halo</p><img src="https://pub-old.r2.dev/cms/editor/foto.webp" data-id="editor/foto.webp" alt="a"><img src="https://pub-old.r2.dev/cms/editor/lama.webp"><img src="https://cdn.lain.com/x.jpg">',
        ]);

        $raw = DB::table('posts')->where('id', $post->id)->first();
        $this->assertSame('posts/abc.webp', $raw->cover_image);
        $this->assertStringNotContainsString('pub-old.r2.dev', $raw->content, 'no own base URL may be stored');
        $this->assertStringContainsString('data-id="editor/foto.webp"', $raw->content);
        $this->assertStringContainsString('data-id="editor/lama.webp"', $raw->content, 'an own src without data-id becomes a path too');
        $this->assertStringContainsString('src="https://cdn.lain.com/x.jpg"', $raw->content, 'external images keep their src');
        $this->assertStringContainsString('<p>Halo</p>', $raw->content);

        $data = $this->getJson("/api/v1/posts/{$post->slug}")->assertOk()->json('data');
        $this->assertSame('https://pub-old.r2.dev/cms/posts/abc.webp', $data['coverImage']);
        $this->assertStringContainsString('src="https://pub-old.r2.dev/cms/editor/foto.webp"', $data['content']);
    }

    public function test_page_blocks_and_settings_store_paths_but_leave_links_alone(): void
    {
        $page = Page::create([
            'title' => 'T', 'slug' => 't', 'status' => 'published', 'published_at' => now()->subDay(),
            'hero' => ['type' => 'lowImpact', 'media' => 'https://pub-old.r2.dev/cms/hero/a.webp', 'richText' => '<h2>x</h2>', 'links' => []],
            'layout' => [
                ['type' => 'mediaBlock', 'data' => ['media' => 'https://pub-old.r2.dev/cms/blocks/b.webp']],
                ['type' => 'cta', 'data' => ['richText' => '<p>x</p>', 'links' => [['label' => 'Brosur', 'url' => 'https://pub-old.r2.dev/cms/brosur.pdf']]]],
            ],
        ]);
        Setting::put('site', ['name' => 'HIPMI', 'logo' => 'https://pub-old.r2.dev/cms/site/logo.webp']);

        $page->refresh();
        $this->assertSame('hero/a.webp', $page->hero['media']);
        $this->assertSame('blocks/b.webp', $page->layout[0]['data']['media']);
        $this->assertSame('https://pub-old.r2.dev/cms/brosur.pdf', $page->layout[1]['data']['links'][0]['url'], 'link URLs are not images');
        $this->assertSame('site/logo.webp', Setting::query()->where('key', 'site')->first()->value['logo']);
    }

    public function test_admin_can_change_r2_base_url(): void
    {
        $post = $this->makePost(['cover_image' => 'posts/abc.webp', 'content' => '<img data-id="editor/f.webp">']);
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageSettings::class)
            ->assertSee('Base URL publik bucket R2')
            ->set('data.site.name', 'BPC HIPMI Bantul')
            ->set('data.header.cta', ['label' => 'Daftar', 'url' => '/daftar', 'appearance' => 'default', 'newTab' => false])
            ->set('data.media.public_url', 'https://media.hipmibantul.com/')
            ->call('save')
            ->assertHasNoFormErrors();

        $media = Setting::get(MediaPaths::SETTING_KEY);
        $this->assertSame('https://media.hipmibantul.com', $media['public_url']);
        $this->assertContains('https://pub-old.r2.dev', $media['previous_urls'], 'old base kept so stray old URLs still convert');

        $data = $this->getJson("/api/v1/posts/{$post->slug}")->json('data');
        $this->assertSame('https://media.hipmibantul.com/cms/posts/abc.webp', $data['coverImage']);
        $this->assertStringContainsString('src="https://media.hipmibantul.com/cms/editor/f.webp"', $data['content']);

        // the setting is applied on every boot (next request)
        Storage::forgetDisk('r2');
        config(['filesystems.disks.r2.url' => 'https://pub-old.r2.dev']);
        MediaPaths::applyConfiguredBaseUrl();
        $this->assertSame('https://media.hipmibantul.com/cms/x.webp', Storage::disk('r2')->url('x.webp'));

        // a URL pasted with the OLD domain is still stored as a path
        $old = $this->makePost(['title' => 'Lama', 'cover_image' => 'https://pub-old.r2.dev/cms/posts/old.webp']);
        $this->assertSame('posts/old.webp', DB::table('posts')->where('id', $old->id)->value('cover_image'));

        $this->assertSame('https://media.hipmibantul.com/cms/posts/old.webp', $this->getJson("/api/v1/posts/{$old->slug}")->json('data.coverImage'));
    }

    public function test_invalid_base_url_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ManageSettings::class)
            ->set('data.site.name', 'X')
            ->set('data.media.public_url', 'media hipmi')
            ->call('save')
            ->assertHasFormErrors(['media.public_url']);
    }

    public function test_test_button_checks_bucket_and_public_url(): void
    {
        Storage::fake('r2');
        $this->actingAs(User::factory()->create());
        Http::fake(function ($request) {
            $file = basename(parse_url($request->url(), PHP_URL_PATH));

            return str_starts_with($request->url(), 'https://media.hipmibantul.com/cms/') && Storage::disk('r2')->exists($file)
                ? Http::response(Storage::disk('r2')->get($file))
                : Http::response('nope', 404);
        });

        Livewire::test(ManageSettings::class)->call('testMediaUrl', 'https://media.hipmibantul.com')->assertNotified('Base URL berfungsi');
        $this->assertSame([], Storage::disk('r2')->allFiles(), 'probe file is cleaned up');

        Livewire::test(ManageSettings::class)->call('testMediaUrl', 'https://salah.example.com')->assertNotified('File uji tidak bisa dibuka lewat Base URL');
    }

    public function test_command_converts_legacy_rows(): void
    {
        // rows written before this feature (bypassing the model)
        DB::table('partners')->insert([
            ['name' => 'A', 'logo' => 'https://pub-old.r2.dev/cms/partners/a.webp', 'tier' => 'partner', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'logo' => 'http://localhost/storage/partners/b.png', 'tier' => 'partner', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'C', 'logo' => 'https://cdn.lain.com/c.png', 'tier' => 'partner', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        config(['app.url' => 'http://localhost']);

        $this->artisan('media:normalize-urls', ['--dry-run' => true])->expectsOutputToContain('2 data akan diubah')->assertSuccessful();
        $this->assertSame('https://pub-old.r2.dev/cms/partners/a.webp', Partner::where('name', 'A')->value('logo'), 'dry-run changes nothing');

        $this->artisan('media:normalize-urls')->expectsOutputToContain('https://cdn.lain.com/c.png')->assertSuccessful();
        $this->assertSame(['partners/a.webp', 'partners/b.png', 'https://cdn.lain.com/c.png'], Partner::orderBy('sort_order')->pluck('logo')->all());
    }

    public function test_command_can_download_external_images(): void
    {
        Storage::fake('r2');
        \App\Support\RemoteImage::$resolveUsing = fn () => ['93.184.216.34'];
        $img = imagecreatetruecolor(800, 400);
        ob_start();
        imagepng($img);
        Http::fake(['https://cdn.lain.com/*' => Http::response(ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);
        DB::table('partners')->insert(['name' => 'C', 'logo' => 'https://cdn.lain.com/c.png', 'tier' => 'partner', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('media:normalize-urls', ['--download' => true])->assertSuccessful();

        $path = Partner::value('logo');
        $this->assertMatchesRegularExpression('~^imported/[0-9a-z]{26}\.webp$~', $path);
        Storage::disk('r2')->assertExists($path);
        \App\Support\RemoteImage::$resolveUsing = null;
    }
}
