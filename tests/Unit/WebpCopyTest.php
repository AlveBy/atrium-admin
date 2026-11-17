<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Support\WebpCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

#[RequiresFunction('imagewebp')]
final class WebpCopyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/atrium-webp-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);

        parent::tearDown();
    }

    /**
     * A photo-like picture: noise compresses badly in PNG, well in webp.
     */
    private function photo(string $name, int $width = 64, int $height = 32, string $type = 'png'): string
    {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(7);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($image, $x, $y, (int) imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }

        $path = "{$this->dir}/{$name}";
        $type === 'png' ? imagepng($image, $path) : imagejpeg($image, $path, 100);
        imagedestroy($image);

        return $path;
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function paths(): array
    {
        return [
            'jpg' => ['photo.jpg', true],
            'jpeg' => ['photo.jpeg', true],
            'png' => ['photo.png', true],
            'capital letters' => ['PHOTO.JPG', true],
            'gif' => ['photo.gif', false],
            'webp already' => ['photo.webp', false],
            'no extension' => ['photo', false],
            'jpg in the folder name only' => ['jpg/photo', false],
            'Cyrillic name' => ['фото.png', true],
        ];
    }

    #[DataProvider('paths')]
    public function test_only_jpeg_and_png_are_converted(string $path, bool $convertible): void
    {
        $this->assertSame($convertible, WebpCopy::convertible($path));
    }

    public function test_gd_with_webp_is_supported_here(): void
    {
        $this->assertTrue(WebpCopy::supported());
    }

    public function test_a_png_photo_gets_a_lighter_webp_copy_beside_it(): void
    {
        $source = $this->photo('photo.png');

        $result = WebpCopy::make($source);

        $this->assertSame('made', $result['status']);
        $this->assertSame(filesize($source), $result['before']);
        $this->assertSame(filesize($source.'.webp'), $result['after']);
        $this->assertLessThan($result['before'], $result['after']);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize($source.'.webp')[2]);
        $this->assertSame([64, 32], array_slice((array) getimagesize($source.'.webp'), 0, 2));
    }

    public function test_a_jpeg_photo_gets_a_copy_too(): void
    {
        $source = $this->photo('photo.jpg', 64, 64, 'jpeg');

        $this->assertSame('made', WebpCopy::make($source, 50)['status']);
        $this->assertFileExists($source.'.webp');
    }

    public function test_a_wide_picture_is_scaled_down_to_the_width_keeping_its_shape(): void
    {
        $source = $this->photo('wide.png', 200, 101);

        WebpCopy::make($source, 82, 100);

        // 101 * 100 / 200 = 50.5, rounded up.
        $this->assertSame([100, 51], array_slice((array) getimagesize($source.'.webp'), 0, 2));
    }

    public function test_a_strip_so_wide_that_it_scales_to_under_half_a_pixel_keeps_one_pixel_of_height(): void
    {
        // 1 * 100 / 300 = 0.33: no picture is zero pixels high.
        $source = $this->photo('strip.png', 300, 1);

        $this->assertSame('made', WebpCopy::make($source, 82, 100)['status']);
        $this->assertSame([100, 1], array_slice((array) getimagesize($source.'.webp'), 0, 2));
    }

    public function test_a_picture_at_the_width_or_with_no_limit_keeps_its_size(): void
    {
        $exact = $this->photo('exact.png', 100, 40);
        $unlimited = $this->photo('unlimited.png', 120, 40);

        WebpCopy::make($exact, 82, 100);
        WebpCopy::make($unlimited, 82, 0);

        $this->assertSame([100, 40], array_slice((array) getimagesize($exact.'.webp'), 0, 2));
        $this->assertSame([120, 40], array_slice((array) getimagesize($unlimited.'.webp'), 0, 2));
    }

    public function test_a_fresh_copy_is_skipped_unless_forced(): void
    {
        $source = $this->photo('photo.png');
        WebpCopy::make($source);
        touch($source, time() - 60);
        touch($source.'.webp', time() - 60);

        $this->assertSame(['status' => 'skipped'], WebpCopy::make($source));
        $this->assertSame('made', WebpCopy::make($source, force: true)['status']);
    }

    public function test_a_copy_older_than_its_source_is_made_again(): void
    {
        $source = $this->photo('photo.png');
        WebpCopy::make($source);
        touch($source.'.webp', time() - 60);
        touch($source, time());

        $this->assertSame('made', WebpCopy::make($source)['status']);
    }

    public function test_a_copy_heavier_than_the_source_is_thrown_away(): void
    {
        // A smooth gradient: PNG stores it in a few bytes, webp cannot.
        $image = imagecreatetruecolor(16, 16);
        for ($x = 0; $x < 16; $x++) {
            for ($y = 0; $y < 16; $y++) {
                imagesetpixel($image, $x, $y, ($x * 16 << 16) | ($y * 16 << 8));
            }
        }
        $source = "{$this->dir}/badge.png";
        imagepng($image, $source, 9);
        imagedestroy($image);

        $this->assertSame(['status' => 'rejected'], WebpCopy::make($source, 100));
        $this->assertFileDoesNotExist($source.'.webp');
    }

    public function test_what_cannot_be_read_as_a_picture_fails(): void
    {
        file_put_contents("{$this->dir}/fake.png", '<?php echo "not a picture";');
        $gif = imagecreatetruecolor(4, 4);
        imagegif($gif, "{$this->dir}/really-a-gif.png");
        imagewebp($gif, "{$this->dir}/really-a-webp.jpg");
        imagedestroy($gif);
        $broken = $this->photo('broken.jpg', 16, 16, 'jpeg');
        file_put_contents($broken, substr((string) file_get_contents($broken), 0, 200));

        foreach (['fake.png', 'really-a-gif.png', 'really-a-webp.jpg', 'missing.png', 'broken.jpg'] as $name) {
            $this->assertSame(['status' => 'failed'], WebpCopy::make("{$this->dir}/{$name}"), $name);
            $this->assertFileDoesNotExist("{$this->dir}/{$name}.webp", $name);
        }
    }

    public function test_a_copy_that_cannot_be_written_fails(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root writes into a read-only folder anyway.');
        }

        $source = $this->photo('photo.png');
        chmod($this->dir, 0555);

        try {
            $this->assertSame(['status' => 'failed'], WebpCopy::make($source));
        } finally {
            chmod($this->dir, 0755);
        }

        $this->assertFileDoesNotExist($source.'.webp');
    }

    public function test_a_file_that_is_not_a_jpeg_or_png_by_name_fails(): void
    {
        $source = $this->photo('photo.png');
        rename($source, "{$this->dir}/photo.gif");

        $this->assertSame(['status' => 'failed'], WebpCopy::make("{$this->dir}/photo.gif"));
    }

    public function test_a_picture_of_too_many_pixels_fails_without_being_opened(): void
    {
        // A PNG header claiming 8000 x 5001 pixels: just past 40 million.
        $source = "{$this->dir}/huge.png";
        file_put_contents($source, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 8000, 5001)."\x08\x02\x00\x00\x00".pack('N', 0));

        $this->assertSame(['status' => 'failed'], WebpCopy::make($source));
    }

    public function test_transparency_survives_the_copy(): void
    {
        $image = imagecreatetruecolor(64, 32);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        mt_srand(3);
        for ($x = 0; $x < 64; $x++) {
            for ($y = 0; $y < 32; $y++) {
                imagesetpixel($image, $x, $y, (int) imagecolorallocatealpha($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), $x < 32 ? 127 : 0));
            }
        }
        $source = "{$this->dir}/transparent.png";
        imagepng($image, $source);
        imagedestroy($image);

        $this->assertSame('made', WebpCopy::make($source, 100)['status']);

        $copy = imagecreatefromwebp($source.'.webp');
        $this->assertNotFalse($copy);
        $this->assertSame(127, (imagecolorat($copy, 5, 5) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($copy, 50, 5) >> 24) & 0x7F);
    }
}
