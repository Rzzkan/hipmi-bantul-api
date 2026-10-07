<?php

namespace Tests\Feature;

use App\Support\Media;
use App\Support\R2Filesystem;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class R2StorageTest extends TestCase
{
    private array $requests = [];

    private function r2(array $overrides = [])
    {
        return R2Filesystem::make(array_merge([
            'key' => 'test-key',
            'secret' => 'test-secret',
            'bucket' => 'hipmi',
            'endpoint' => 'https://acc123.r2.cloudflarestorage.com',
            'url' => 'https://media.hipmibantul.site',
            'root' => 'cms',
            'visibility' => 'public',
            'throw' => true,
            // capture raw HTTP requests instead of hitting the network
            'http_handler' => function (RequestInterface $request) {
                $this->requests[] = $request;

                return Create::promiseFor(new Response(200, ['ETag' => '"x"']));
            },
        ], $overrides));
    }

    public function test_uploads_never_send_acl_headers(): void
    {
        $disk = $this->r2();

        $disk->put('posts/cover.jpg', 'fake-image-bytes', ['visibility' => 'public']);
        $disk->putFileAs('board', UploadedFile::fake()->image('foto.jpg'), 'foto.jpg', 'public');

        $this->assertCount(2, $this->requests);
        foreach ($this->requests as $req) {
            $this->assertSame('PUT', $req->getMethod());
            $this->assertFalse($req->hasHeader('x-amz-acl'), 'R2 tidak mendukung ACL — header tidak boleh dikirim');
        }

        $this->assertSame('acc123.r2.cloudflarestorage.com', $this->requests[0]->getUri()->getHost());
        $this->assertSame('/hipmi/cms/posts/cover.jpg', $this->requests[0]->getUri()->getPath());
        $this->assertSame('/hipmi/cms/board/foto.jpg', $this->requests[1]->getUri()->getPath());
        $this->assertStringContainsString('image/jpeg', $this->requests[1]->getHeaderLine('Content-Type'));
    }

    public function test_public_url_uses_custom_domain_and_root(): void
    {
        $this->assertSame('https://media.hipmibantul.site/cms/posts/cover.jpg', $this->r2()->url('posts/cover.jpg'));
    }

    public function test_media_helper_follows_configured_disk(): void
    {
        config([
            'filesystems.media_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://pub-abc.r2.dev',
            'filesystems.disks.r2.root' => 'cms',
            'filesystems.disks.r2.endpoint' => 'https://acc123.r2.cloudflarestorage.com',
            'filesystems.disks.r2.bucket' => 'hipmi',
        ]);

        $this->assertSame('r2', Media::diskName());
        $this->assertSame('https://pub-abc.r2.dev/cms/events/poster.png', Media::url('events/poster.png'));
        $this->assertSame('https://elsewhere.test/x.png', Media::url('https://elsewhere.test/x.png'));
    }

    public function test_default_stays_on_server_disk(): void
    {
        $this->assertSame('public', Media::diskName());
        $this->assertStringEndsWith('/storage/posts/a.jpg', Media::url('posts/a.jpg'));
    }
}
