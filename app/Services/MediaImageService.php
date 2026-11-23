<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaImageService
{
    public function store(UploadedFile $image, string $directory, string $disk, string $errorKey = 'image'): string
    {
        $temporary = null;
        $source = null;
        $canvas = null;
        $path = null;
        try {
            $filename = $image->getPathname();
            $info = @getimagesize($filename);
            if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                throw new RuntimeException('Unsupported or unreadable image.');
            }
            [$width, $height] = $info;
            $orientation = 1;
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($filename);
                $orientation = (int) ($exif['Orientation'] ?? 1);
            }
            $limit = max(1, (int) config('photo_uploads.max_dimension'));
            $target = max(1, (int) config('photo_uploads.target_bytes'));
            $file = $image;
            $extension = image_type_to_extension($info[2], false);
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            $originalExtension = $extension;
            $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
            $estimatedMemory = $width * $height * ($orientation === 1 ? 6 : 10) + 32 * 1024 * 1024;
            // These are processing budgets, not user upload resolution limits.
            $canCompress = $width * $height <= config('photo_uploads.max_processing_pixels')
                && ($memoryLimit <= 0 || memory_get_usage(true) + $estimatedMemory <= $memoryLimit);
            // Browser-optimized and already-small images need no second lossy encode.
            if ($canCompress && (max($width, $height) > $limit || $image->getSize() > $target || $orientation !== 1)) {
                try {
                    $source = match ($info[2]) {
                        IMAGETYPE_JPEG => @imagecreatefromjpeg($filename),
                        IMAGETYPE_PNG => @imagecreatefrompng($filename),
                        IMAGETYPE_WEBP => @imagecreatefromwebp($filename),
                    };
                    if (! $source instanceof GdImage) {
                        throw new RuntimeException('Image decoding failed.');
                    }
                    if (in_array($orientation, [2, 4, 5, 7], true)) {
                        imageflip($source, IMG_FLIP_HORIZONTAL);
                    }
                    $angle = match ($orientation) {
                        3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0
                    };
                    if ($angle !== 0) {
                        $rotated = imagerotate($source, $angle, 0);
                        if (! $rotated instanceof GdImage) {
                            throw new RuntimeException('Image orientation failed.');
                        }
                        $source = $rotated;
                    }
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $scale = min(1, $limit / max($width, $height));
                    $newWidth = max(1, (int) round($width * $scale));
                    $newHeight = max(1, (int) round($height * $scale));
                    $canvas = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    if ($transparent === false) {
                        throw new RuntimeException('Image canvas allocation failed.');
                    }
                    imagefill($canvas, 0, 0, $transparent);
                    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    // WebP preserves transparency without scanning every source pixel.
                    $extension = $info[2] === IMAGETYPE_JPEG ? 'jpg' : 'webp';
                    $temporary = tmpfile();
                    if ($temporary === false) {
                        throw new RuntimeException('Temporary image file unavailable.');
                    }
                    $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;
                    if (! is_string($temporaryPath)) {
                        throw new RuntimeException('Temporary image path unavailable.');
                    }
                    $quality = max(1, min(100, (int) config('photo_uploads.quality')));
                    $minimum = max(1, min($quality, (int) config('photo_uploads.min_quality')));
                    for (; $quality >= $minimum; $quality -= 10) {
                        $encoded = $extension === 'jpg'
                            ? imagejpeg($canvas, $temporaryPath, $quality)
                            : imagewebp($canvas, $temporaryPath, $quality);
                        clearstatcache(true, $temporaryPath);
                        if (! $encoded || filesize($temporaryPath) === 0) {
                            throw new RuntimeException('Image encoding failed.');
                        }
                        if (filesize($temporaryPath) <= $target) {
                            break;
                        }
                    }
                    // Keep a smaller original when no resize/orientation was needed.
                    if ($scale < 1 || $orientation !== 1 || filesize($temporaryPath) < $image->getSize()) {
                        $file = new UploadedFile($temporaryPath, 'photo.'.$extension, $extension === 'jpg' ? 'image/jpeg' : 'image/webp', null, true);
                    } else {
                        $extension = $info[2] === IMAGETYPE_JPEG ? 'jpg' : image_type_to_extension($info[2], false);
                    }
                } catch (Throwable $compressionException) {
                    // A recoverable compression failure must not reject a valid
                    // user upload. Storage failures below still fail normally.
                    report($compressionException);
                    $file = $image;
                    $extension = $originalExtension;
                } finally {
                    unset($source, $canvas, $rotated);
                }
            }
            $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;
            if (Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']) !== $path) {
                throw new RuntimeException('Image storage failed.');
            }

            return $path;
        } catch (Throwable $exception) {
            if ($path !== null) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages([$errorKey => 'Foto gagal diproses atau disimpan. Pilih ulang foto dan coba lagi.']);
        } finally {
            // PHP 8 GdImage objects are released when these references leave scope.
            unset($source, $canvas);
            if (is_resource($temporary)) {
                fclose($temporary);
            }
        }
    }
}
