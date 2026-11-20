<?php

namespace Tests\Feature\Farms;

use App\Services\MediaImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaImageServiceTest extends TestCase
{
    public function test_large_photo_is_resized_without_changing_private_category_or_farm_path(): void
    {
        $image = UploadedFile::fake()->image('phone.jpg', 4000, 2000);
        foreach (['farm', 'activity'] as $disk) {
            $path = app(MediaImageService::class)->store($image, 'farms/farm-a/activity-a', $disk);
            $contents = Storage::disk($disk)->get($path);
            $this->assertStringStartsWith('farms/farm-a/activity-a/', $path);
            // Windows does not expose POSIX chmod modes to Flysystem correctly.
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->assertSame('private', Storage::disk($disk)->getVisibility($path));
            }
            $this->assertSame([2048, 1024], array_slice(getimagesizefromstring($contents), 0, 2));
            $this->assertLessThan(strlen(file_get_contents($image->getPathname())), strlen($contents));
        }
    }

    public function test_small_image_is_kept_byte_for_byte_without_upscaling_or_reencoding(): void
    {
        $image = UploadedFile::fake()->image('small.png', 30, 20);
        $path = app(MediaImageService::class)->store($image, 'farms/farm-a', 'farm');
        $this->assertSame(file_get_contents($image->getPathname()), Storage::disk('farm')->get($path));
        $this->assertStringEndsWith('.png', $path);
    }

    #[DataProvider('s3Photos')]
    public function test_s3_upload_uses_the_category_prefix_and_private_acl(int $width, int $height, int $outputWidth, int $outputHeight): void
    {
        $requests = [];
        $disk = Storage::build([
            'driver' => 's3', 'root' => 'task-activities',
            'region' => 'us-east-1', 'endpoint' => 'http://storage.test:9000',
            'bucket' => 'farmer-ic-private', 'use_path_style_endpoint' => true,
            'key' => 'test', 'secret' => 'test-secret', 'throw' => true,
            'http_handler' => function (\Psr\Http\Message\RequestInterface $request) use (&$requests) {
                $requests[] = ['path' => $request->getUri()->getPath(),
                    'acl' => $request->getHeaderLine('x-amz-acl'),
                    'type' => $request->getHeaderLine('Content-Type'),
                    'contents' => (string) $request->getBody()];

                return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, ['ETag' => '"photo"']));
            },
        ]);
        Storage::set('activity', $disk);
        $path = app(MediaImageService::class)->store(UploadedFile::fake()->image('phone.jpg', $width, $height), 'farms/farm-a/activity-a', 'activity');
        $this->assertCount(1, $requests);
        $this->assertSame('/farmer-ic-private/task-activities/'.$path, $requests[0]['path']);
        $this->assertSame('private', $requests[0]['acl']);
        $this->assertSame('image/jpeg', $requests[0]['type']);
        $this->assertSame([$outputWidth, $outputHeight], array_slice(getimagesizefromstring($requests[0]['contents']), 0, 2));
    }

    public static function s3Photos(): array
    {
        return [[4000, 2000, 2048, 1024], [6048, 8064, 6048, 8064]];
    }

    public function test_transparent_png_is_resized_to_webp_with_transparency_preserved(): void
    {
        $source = imagecreatetruecolor(3000, 1500);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 10, 80, 20, 127));
        imagefilledrectangle($source, 1000, 500, 2000, 1000, imagecolorallocatealpha($source, 0, 100, 0, 0));
        ob_start();
        imagepng($source);
        $image = UploadedFile::fake()->createWithContent('alpha.png', ob_get_clean());
        $path = app(MediaImageService::class)->store($image, 'farms/farm-a', 'farm');
        $output = imagecreatefromstring(Storage::disk('farm')->get($path));
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame(2048, imagesx($output));
        $this->assertSame(127, imagecolorsforindex($output, imagecolorat($output, 10, 10))['alpha']);
    }

    public function test_processing_pixel_budget_keeps_original_instead_of_rejecting_user_upload(): void
    {
        config(['photo_uploads.max_processing_pixels' => 100, 'photo_uploads.max_dimension' => 10]);
        $image = UploadedFile::fake()->image('big.jpg', 20, 20);
        foreach (['farm', 'activity'] as $disk) {
            $path = app(MediaImageService::class)->store($image, 'farms/farm-a', $disk);
            $contents = Storage::disk($disk)->get($path);
            $this->assertSame(file_get_contents($image->getPathname()), $contents);
            $this->assertSame([20, 20], array_slice(getimagesizefromstring($contents), 0, 2));
        }
    }

    public function test_memory_budget_keeps_original_instead_of_rejecting_user_upload(): void
    {
        $image = UploadedFile::fake()->image('photo.jpg', 256, 256);
        config(['photo_uploads.max_dimension' => 128]);
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 16 * 1024 * 1024));
        try {
            $path = app(MediaImageService::class)->store($image, 'farms/farm-a', 'farm');
            $this->assertSame(file_get_contents($image->getPathname()), Storage::disk('farm')->get($path));
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    public function test_invalid_photo_is_rejected_without_an_object_being_created(): void
    {
        try {
            app(MediaImageService::class)->store(UploadedFile::fake()->createWithContent('broken.jpg', 'not an image'), 'farms/farm-a', 'farm');
            $this->fail('Expected a field-specific decoding error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        Storage::disk('farm')->assertDirectoryEmpty('');
    }

    #[DataProvider('orientations')]
    public function test_all_exif_orientations_including_mirrors_are_normalized(int $orientation, int $width, int $height, array $color): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Enable EXIF to verify phone orientation; production Docker and CI install it.');
        }
        $source = imagecreatetruecolor(80, 40);
        imagefilledrectangle($source, 0, 0, 39, 19, imagecolorallocate($source, 240, 0, 0));
        imagefilledrectangle($source, 40, 0, 79, 19, imagecolorallocate($source, 0, 240, 0));
        imagefilledrectangle($source, 0, 20, 39, 39, imagecolorallocate($source, 0, 0, 240));
        imagefilledrectangle($source, 40, 20, 79, 39, imagecolorallocate($source, 240, 240, 0));
        ob_start();
        imagejpeg($source, null, 95);
        $jpeg = ob_get_clean();
        $exif = "Exif\0\0II\x2A\0\x08\0\0\0".pack('v', 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0);
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $path = app(MediaImageService::class)->store(UploadedFile::fake()->createWithContent('rotated.jpg', $jpeg), 'farms/farm-a', 'farm');
        $output = imagecreatefromstring(Storage::disk('farm')->get($path));
        $this->assertSame([$width, $height], [imagesx($output), imagesy($output)]);
        $pixel = imagecolorsforindex($output, imagecolorat($output, 10, 10));
        foreach (['red', 'green', 'blue'] as $index => $channel) {
            $this->assertEqualsWithDelta($color[$index], $pixel[$channel], 15);
        }
    }

    public static function orientations(): array
    {
        return [
            [1, 80, 40, [240, 0, 0]], [2, 80, 40, [0, 240, 0]],
            [3, 80, 40, [240, 240, 0]], [4, 80, 40, [0, 0, 240]],
            [5, 40, 80, [240, 0, 0]], [6, 40, 80, [0, 0, 240]],
            [7, 40, 80, [240, 240, 0]], [8, 40, 80, [0, 240, 0]],
        ];
    }
}
