<?php

namespace App\Support;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Arr;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as S3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;

/**
 * Cloudflare R2 disk driver.
 *
 * R2 speaks the S3 API, but does NOT implement object ACLs (x-amz-acl).
 * Laravel's stock "s3" driver always sends an ACL header, so this driver
 * strips it from every request. Public access is configured on the bucket
 * (custom domain or r2.dev URL) instead of per object.
 */
class R2Filesystem
{
    public static function make(array $config): AwsS3V3Adapter
    {
        $client = new S3Client(array_filter([
            'version' => 'latest',
            'region' => $config['region'] ?? 'auto',
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => ['key' => $config['key'] ?? '', 'secret' => $config['secret'] ?? ''],
            'http_handler' => $config['http_handler'] ?? null, // for tests
            'handler' => $config['handler'] ?? null, // for tests
        ], fn ($v) => $v !== null));

        $client->getHandlerList()->appendInit(Middleware::mapCommand(function (CommandInterface $command) {
            unset($command['ACL']);

            return $command;
        }), 'r2-strip-acl');

        $adapter = new S3Adapter(
            $client,
            $config['bucket'] ?? '',
            trim($config['root'] ?? '', '/'),
            new PortableVisibilityConverter,
        );

        $flysystem = new Filesystem($adapter, Arr::only($config, ['directory_visibility', 'disable_asserts', 'retain_visibility', 'temporary_url', 'url', 'visibility']));

        return new AwsS3V3Adapter($flysystem, $adapter, $config, $client);
    }
}
