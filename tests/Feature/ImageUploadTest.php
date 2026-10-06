<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\User;
use App\Support\ImageOptimizer;
use App\Support\RemoteImage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        RemoteImage::$resolveUsing = fn (string $host) => $host === 'internal.example' ? ['10.0.0.5'] : ['93.184.216.34'];
    }

    protected function tearDown(): void
    {
        RemoteImage::$resolveUsing = null;
        parent::tearDown();
    }

    /** A realistic, noisy photo so compression numbers are meaningful. */
    private function photo(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 4000; $i++) {
            imagefilledellipse($img, random_int(0, $w), random_int(0, $h), random_int(10, 300), random_int(10, 300), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($img, null, 95);

        return ob_get_clean();
    }

    private function dims(string $bytes): array
    {
        $i = (new ImageManager(new Driver))->read($bytes);

        return [$i->width(), $i->height()];
    }

    public function test_presets_resize_and_compress_to_webp(): void
    {
        $src = $this->photo(4000, 3000);

        $cover = ImageOptimizer::optimize($src, 'cover');
        $this->assertSame('webp', $cover['extension']);
        $this->assertSame([1600, 1200], $this->dims($cover['body']));
        $this->assertLessThan(strlen($src) / 4, strlen($cover['body']), 'cover should be far smaller than the original');

        $this->assertSame([2133, 1600], $this->dims(ImageOptimizer::optimize($src, 'hero')['body']));
        $this->assertSame([600, 600], $this->dims(ImageOptimizer::optimize($src, 'avatar')['body']));

        $og = ImageOptimizer::optimize($src, 'og');
        $this->assertSame('jpg', $og['extension']);
        $this->assertSame([1200, 630], $this->dims($og['body']));

        // small images are never upscaled
        $this->assertSame([400, 300], $this->dims(ImageOptimizer::optimize($this->photo(400, 300), 'cover')['body']));
    }

    public function test_logo_keeps_transparency_and_svg_passes_through(): void
    {
        $img = imagecreatetruecolor(1200, 400);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledrectangle($img, 100, 100, 300, 300, imagecolorallocate($img, 255, 201, 77));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $out = ImageOptimizer::optimize($png, 'logo');
        $decoded = (new ImageManager(new Driver))->read($out['body']);
        $this->assertSame([600, 200], [$decoded->width(), $decoded->height()]);
        $this->assertSame(0.0, $decoded->pickColor(5, 5)->alpha()->normalize(), 'corner must stay transparent');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
        $this->assertSame('svg', ImageOptimizer::optimize($svg, 'logo')['extension']);
    }

    public function test_rejects_non_images(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageOptimizer::optimize('%PDF-1.4 not an image', 'cover');
    }

    public function test_admin_upload_is_compressed_before_storage(): void
    {
        $this->actingAs(User::factory()->create());
        $original = $this->photo(3000, 2000);
        $file = UploadedFile::fake()->createWithContent('foto-hp.jpg', $original);

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Uji Kompres', 'category' => 'berita', 'content' => '<p>x</p>', 'status' => 'draft', 'cover_image' => [$file]])
            ->call('create')
            ->assertHasNoFormErrors();

        $path = Post::where('title', 'Uji Kompres')->value('cover_image');
        $this->assertMatchesRegularExpression('~^posts/[0-9a-z]{26}\.webp$~', $path);
        $stored = Storage::disk('public')->get($path);
        $this->assertSame([1600, 1067], $this->dims($stored));
        $this->assertLessThan(strlen($original) / 4, strlen($stored));
        fwrite(STDERR, sprintf("\n  upload: %d KB → %d KB\n", strlen($original) / 1024, strlen($stored) / 1024));
    }

    public function test_admin_can_fetch_image_from_url(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(['https://cdn.example.org/*' => Http::response($this->photo(2500, 2500), 200, ['Content-Type' => 'image/jpeg'])]);

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Dari URL', 'category' => 'berita', 'content' => '<p>x</p>', 'status' => 'draft'])
            ->callAction(TestAction::make('fromUrl')->schemaComponent('cover_image'), data: ['url' => 'https://cdn.example.org/foto.jpg'])
            ->assertHasNoActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $path = Post::where('title', 'Dari URL')->value('cover_image');
        $this->assertStringStartsWith('posts/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([1600, 1600], $this->dims(Storage::disk('public')->get($path)));
    }

    public function test_url_fetch_blocks_internal_addresses_and_redirects(): void
    {
        foreach (['http://127.0.0.1/a.jpg', 'http://169.254.169.254/latest/meta-data', 'https://internal.example/a.jpg', 'ftp://cdn.example.org/a.jpg'] as $bad) {
            try {
                RemoteImage::fetch($bad);
                $this->fail("Should reject {$bad}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::fake(['https://cdn.example.org/redirect' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.png'])]);
        $this->expectException(InvalidArgumentException::class);
        RemoteImage::fetch('https://cdn.example.org/redirect');
    }

    public function test_url_fetch_rejects_html_pages(): void
    {
        Http::fake(['https://cdn.example.org/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html'])]);
        $this->expectExceptionMessage('bukan file gambar');
        RemoteImage::fetch('https://cdn.example.org/page');
    }

    public function test_share_links_are_normalized(): void
    {
        $this->assertSame('https://drive.google.com/uc?export=download&id=1AbC_d-9', RemoteImage::normalize('https://drive.google.com/file/d/1AbC_d-9/view?usp=sharing'));
        $this->assertSame('https://www.dropbox.com/s/x/a.jpg?raw=1', RemoteImage::normalize('https://www.dropbox.com/s/x/a.jpg?dl=0'));
    }

    public function test_images_inserted_in_the_article_editor_are_compressed(): void
    {
        $tmpDisk = FileUploadConfiguration::disk();
        Storage::fake($tmpDisk);
        Storage::disk($tmpDisk)->put(FileUploadConfiguration::path('editor-foto.jpg'), $this->photo(3000, 2000));
        $file = TemporaryUploadedFile::createFromLivewire('editor-foto.jpg');

        $this->actingAs(User::factory()->create());
        $page = Livewire::test(CreatePost::class)->instance();
        $editor = collect($page->getSchema('form')->getFlatFields())->first(fn ($f) => $f->getName() === 'content');
        $path = $editor->saveUploadedFileAttachment($file);

        $this->assertMatchesRegularExpression('~^editor/[0-9a-z]{26}\.webp$~', $path);
        $this->assertSame([1600, 1067], $this->dims(Storage::disk('public')->get($path)));
    }

    public function test_optimize_command_compresses_existing_files_in_place(): void
    {
        Storage::disk('public')->put('posts/lama.jpg', $big = $this->photo(3600, 2400));

        $this->artisan('media:optimize', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(strlen($big), strlen(Storage::disk('public')->get('posts/lama.jpg')), 'dry-run must not modify files');

        $this->artisan('media:optimize')->assertSuccessful();
        $after = Storage::disk('public')->get('posts/lama.jpg');
        $this->assertSame([1600, 1067], $this->dims($after));
        $this->assertLessThan(strlen($big) / 4, strlen($after));
    }
}
