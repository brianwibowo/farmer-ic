<?php

namespace Tests\Feature\Farms;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class OperationalStorageTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_local_and_s3_disks_use_the_same_category_prefixes_and_relative_paths(): void
    {
        $repository = Env::getRepository();
        $variables = ['OPERATIONAL_FILESYSTEM_DRIVER', 'ACTIVITY_FILESYSTEM_DRIVER', 'LEGALITY_FILESYSTEM_DRIVER', 'FARM_FILESYSTEM_DRIVER'];
        $previous = [];
        foreach ($variables as $variable) {
            $previous[$variable] = $repository->get($variable);
        }
        try {
            foreach (['local', 's3'] as $driver) {
                foreach ($variables as $variable) {
                    $repository->set($variable, $driver);
                }
                $settings = require config_path('filesystems.php');
                $farm = (string) Str::uuid();
                $activity = (string) Str::uuid();
                $paths = ['activity' => 'farms/'.$farm.'/'.$activity.'/evidence.jpg',
                    'legality' => 'farms/'.$farm.'/contract.pdf', 'farm' => 'farms/'.$farm.'/image.jpg'];
                foreach (['activity' => 'task-activities', 'legality' => 'legality', 'farm' => 'farm-images'] as $disk => $prefix) {
                    $definition = $settings['disks'][$disk];
                    $this->assertSame($driver, $definition['driver']);
                    $this->assertSame('private', $definition['visibility']);
                    $this->assertTrue($definition['throw']);
                    if ($driver === 'local') {
                        $this->assertSame(storage_path('app/private/'.$prefix), $definition['root']);
                        $definition['root'] = storage_path('framework/testing/disks/uniform/'.$prefix);
                        $storage = Storage::build($definition);
                        $storage->put($paths[$disk], 'operational-file');
                        $this->assertFileExists($definition['root'].'/'.$paths[$disk]);
                        $this->assertSame('operational-file', $storage->get($paths[$disk]));
                        $storage->delete($paths[$disk]);
                    } else {
                        $this->assertSame($prefix, $definition['root']);
                        $objects = [];
                        $definition = [...$definition,
                            'region' => 'us-east-1', 'endpoint' => 'http://storage.test:9000', 'bucket' => 'farmer-ic-private',
                            'use_path_style_endpoint' => true, 'key' => 'test', 'secret' => 'test-secret',
                            'http_handler' => function (RequestInterface $request) use (&$objects) {
                                $key = $request->getUri()->getPath();
                                if ($request->getMethod() === 'PUT') {
                                    $this->assertSame('private', $request->getHeaderLine('x-amz-acl'));
                                    $objects[$key] = (string) $request->getBody();

                                    return Create::promiseFor(new Response(200, ['ETag' => '"test"']));
                                }
                                if ($request->getMethod() === 'DELETE') {
                                    unset($objects[$key]);

                                    return Create::promiseFor(new Response(204));
                                }

                                return Create::promiseFor(new Response(200, [], $objects[$key]));
                            },
                        ];
                        // Exercise Laravel's real S3 driver; only HTTP transport is simulated.
                        $storage = Storage::build($definition);
                        $storage->put($paths[$disk], 'operational-file');
                        $this->assertArrayHasKey('/farmer-ic-private/'.$prefix.'/'.$paths[$disk], $objects);
                        $this->assertSame('operational-file', $storage->get($paths[$disk]));
                        $storage->delete($paths[$disk]);
                        $this->assertSame([], $objects);
                    }
                }
                $this->assertSame('local', $settings['disks']['local']['driver']);
                $this->assertSame('local', $settings['disks']['public']['driver']);
            }
        } finally {
            foreach ($previous as $variable => $value) {
                $value === null ? $repository->clear($variable) : $repository->set($variable, $value);
            }
        }
    }

    public function test_demo_seeders_store_real_farm_and_activity_images_with_uniform_relative_paths(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        foreach (\App\Models\Farm::query()->whereNotNull('image_url')->get() as $farm) {
            $this->assertMatchesRegularExpression('#^farms/'.$farm->id.'/[^/]+\.jpg$#', $farm->image_url);
            Storage::disk('farm')->assertExists($farm->image_url);
        }
        $photos = \App\Models\ActivityPhoto::query()->with('activity')->get();
        $this->assertNotEmpty($photos);
        foreach ($photos as $photo) {
            $this->assertStringStartsWith('farms/'.$photo->activity->farm_id.'/'.$photo->activity_id.'/', $photo->image_url);
            Storage::disk('activity')->assertExists($photo->image_url);
        }
    }
}
