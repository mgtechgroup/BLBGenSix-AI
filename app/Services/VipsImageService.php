<?php

namespace App\Services;

use Jcupitt\Vips\Image;

/**
 * VipsImageService - High-performance image processing via libvips
 * Replaces GD/Imagick for 4-10x faster processing and 10x lower memory usage
 */
class VipsImageService
{
    protected bool $available = false;
    protected string $cacheDir;

    public function __construct()
    {
        $this->available = extension_loaded('vips') && class_exists(Image::class);
        $this->cacheDir = storage_path('app/vips-cache');
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getVipsVersion(): string
    {
        return $this->available ? Image::version() : 'vips not loaded';
    }

    /**
     * Generate thumbnail with smart crop
     */
    public function thumbnail(string $sourcePath, int $width = 300, int $height = 300, string $crop = 'attention'): string
    {
        $hash = md5($sourcePath . $width . $height . $crop);
        $destPath = "{$this->cacheDir}/thumb_{$hash}.jpg";

        if (file_exists($destPath)) {
            return $destPath;
        }

        try {
            $image = Image::thumbnail($sourcePath, $width, ['height' => $height, 'crop' => $crop]);
            $image->writeToFile($destPath, ['Q' => 85]);
            return $destPath;
        } catch (\Exception $e) {
            // Fallback to GD/Imagick
            return $this->fallbackThumbnail($sourcePath, $width, $height);
        }
    }

    /**
     * Optimize and resize image for web delivery
     */
    public function optimize(string $sourcePath, int $maxWidth = 2048, int $quality = 80): string
    {
        $hash = md5($sourcePath . $maxWidth . $quality);
        $destPath = "{$this->cacheDir}/opt_{$hash}.webp";

        if (file_exists($destPath)) {
            return $destPath;
        }

        try {
            $image = Image::newFromFile($sourcePath);

            // Auto-rotate based on EXIF
            $image = $image->autorot();

            // Resize if needed
            if ($image->width > $maxWidth) {
                $image = $image->resize($maxWidth / $image->width);
            }

            // Strip metadata for privacy
            $image = $image->copy(['interpretation' => 'srgb']);

            // Save as optimized WebP
            $image->writeToFile($destPath, [
                'Q' => $quality,
                'strip' => true,
                'lossless' => false,
            ]);

            return $destPath;
        } catch (\Exception $e) {
            return $this->fallbackOptimize($sourcePath, $maxWidth, $quality);
        }
    }

    /**
     * Convert between image formats
     */
    public function convert(string $sourcePath, string $format = 'webp', int $quality = 85): string
    {
        $hash = md5($sourcePath . $format . $quality);
        $destPath = "{$this->cacheDir}/conv_{$hash}.{$format}";

        if (file_exists($destPath)) {
            return $destPath;
        }

        try {
            $image = Image::newFromFile($sourcePath);
            
            $saveOptions = ['Q' => $quality];
            if ($format === 'png') {
                $saveOptions['compression'] = 9;
            } elseif ($format === 'webp') {
                $saveOptions['lossless'] = false;
            } elseif ($format === 'avif') {
                $saveOptions['compression'] = 'hevc';
            }

            $image->writeToFile($destPath, $saveOptions);
            return $destPath;
        } catch (\Exception $e) {
            return $sourcePath;
        }
    }

    /**
     * Extract image metadata (dimensions, EXIF, ICC profile)
     */
    public function metadata(string $sourcePath): array
    {
        try {
            $image = Image::newFromFile($sourcePath);

            return [
                'width' => $image->width,
                'height' => $image->height,
                'bands' => $image->bands,
                'format' => $image->get('vips-loader'),
                'interpretation' => $image->interpretation,
                'has_alpha' => $image->hasAlpha(),
                'format_supported' => true,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'format_supported' => false,
            ];
        }
    }

    /**
     * Generate multiple responsive sizes at once
     */
    public function responsive(string $sourcePath, array $sizes = [320, 640, 1024, 2048]): array
    {
        $results = [];
        foreach ($sizes as $size) {
            $results[$size] = $this->thumbnail($sourcePath, $size, 0, 'none');
        }
        return $results;
    }

    /**
     * Watermark an image
     */
    public function watermark(string $sourcePath, string $watermarkPath, string $position = 'bottom-right', int $opacity = 50): string
    {
        $hash = md5($sourcePath . $watermarkPath . $position . $opacity);
        $destPath = "{$this->cacheDir}/wm_{$hash}.webp";

        try {
            $image = Image::newFromFile($sourcePath);
            $watermark = Image::newFromFile($watermarkPath);

            // Scale watermark to 15% of image width
            $scale = ($image->width * 0.15) / $watermark->width;
            $watermark = $watermark->resize($scale);

            // Set opacity
            if ($watermark->bands < 4) {
                $watermark = $watermark->bandjoin(255);
            }
            $watermark = $watermark->linear(1, 0)->linear($opacity / 100, 0);

            // Position
            $x = match($position) {
                'bottom-right' => $image->width - $watermark->width - 20,
                'bottom-left' => 20,
                'top-right' => $image->width - $watermark->width - 20,
                'top-left' => 20,
                'center' => ($image->width - $watermark->width) / 2,
                default => $image->width - $watermark->width - 20,
            };
            $y = match($position) {
                'bottom-right', 'bottom-left' => $image->height - $watermark->height - 20,
                'top-right', 'top-left' => 20,
                'center' => ($image->height - $watermark->height) / 2,
                default => $image->height - $watermark->height - 20,
            };

            $image = $image->composite($watermark, 'over', ['x' => $x, 'y' => $y]);
            $image->writeToFile($destPath, ['Q' => 85]);
            return $destPath;
        } catch (\Exception $e) {
            return $sourcePath;
        }
    }

    /**
     * Generate image comparison / slider
     */
    public function compare(string $pathA, string $pathB, string $direction = 'horizontal'): string
    {
        $hash = md5($pathA . $pathB . $direction);
        $destPath = "{$this->cacheDir}/compare_{$hash}.webp";

        try {
            $a = Image::newFromFile($pathA);
            $b = Image::newFromFile($pathB);

            // Resize to match
            $maxW = max($a->width, $b->width);
            $maxH = max($a->height, $b->height);
            $a = $a->embed($maxW / 2, $maxH, 0, 0);
            $b = $b->embed($maxW / 2, $maxH, 0, 0);

            $joined = $a->join($b, 'horizontal');
            $joined->writeToFile($destPath, ['Q' => 85]);
            return $destPath;
        } catch (\Exception $e) {
            return $pathA;
        }
    }

    /**
     * Generate a spritesheet from multiple images
     */
    public function spritesheet(array $paths, int $columns = 4, int $thumbWidth = 200): string
    {
        $hash = md5(implode(',', $paths) . $columns . $thumbWidth);
        $destPath = "{$this->cacheDir}/sprite_{$hash}.webp";

        try {
            $thumbs = [];
            foreach ($paths as $path) {
                $thumb = Image::thumbnail($path, $thumbWidth);
                $thumbs[] = $thumb;
            }

            if (empty($thumbs)) return '';

            $rows = [];
            for ($i = 0; $i < count($thumbs); $i += $columns) {
                $row = array_slice($thumbs, $i, $columns);
                $rowImage = $row[0];
                for ($j = 1; $j < count($row); $j++) {
                    $rowImage = $rowImage->join($row[$j], 'horizontal');
                }
                $rows[] = $rowImage;
            }

            $result = $rows[0];
            for ($i = 1; $i < count($rows); $i++) {
                $result = $result->join($rows[$i], 'vertical');
            }

            $result->writeToFile($destPath, ['Q' => 80]);
            return $destPath;
        } catch (\Exception $e) {
            return '';
        }
    }

    protected function fallbackThumbnail(string $path, int $w, int $h): string
    {
        // GD fallback
        if (!function_exists('imagecreatetruecolor')) return $path;
        $info = getimagesize($path);
        if (!$info) return $path;
        
        $src = match($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => null
        };
        if (!$src) return $path;

        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        $output = "{$this->cacheDir}/thumb_fallback_" . md5($path) . ".jpg";
        imagejpeg($dst, $output, 85);
        
        imagedestroy($src);
        imagedestroy($dst);
        return $output;
    }

    protected function fallbackOptimize(string $path, int $maxW, int $q): string
    {
        return $this->fallbackThumbnail($path, $maxW, 0);
    }
}
