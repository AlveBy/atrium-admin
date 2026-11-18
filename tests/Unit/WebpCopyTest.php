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

    /**
     * Garbage after the PNG's last chunk: decoders stop at IEND, so the
     * picture stays the same and the file weighs exactly $size bytes.
     */
    private function weighing(string $path, int $size): string
    {
        $current = (int) filesize($path);
        $this->assertLessThanOrEqual($size, $current);
        file_put_contents($path, str_repeat("\0", $size - $current), FILE_APPEND);

        return $path;
    }

    /**
     * The gradient webp cannot beat: a few hundred bytes in PNG.
     */
    private function badge(string $name): string
    {
        $image = imagecreatetruecolor(16, 16);
        for ($x = 0; $x < 16; $x++) {
            for ($y = 0; $y < 16; $y++) {
                imagesetpixel($image, $x, $y, ($x * 16 << 16) | ($y * 16 << 8));
            }
        }
        $path = "{$this->dir}/{$name}";
        imagepng($image, $path, 9);

        return $path;
    }

    /**
     * A $width x $height PNG, solid, padded to weigh far more than its copy.
     */
    private function heavy(string $name, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $path = "{$this->dir}/{$name}";
        imagepng($image, $path);

        return $this->weighing($path, 100_000);
    }

    /**
     * @return array{int,int} width and height of the copy
     */
    private function sizeOfCopy(string $source): array
    {
        $info = (array) getimagesize($source.'.webp');

        return [$info[0], $info[1]];
    }

    public function test_the_default_quality_is_82(): void
    {
        $default = $this->photo('default.png', 96, 96);
        $lower = $this->photo('lower.png', 96, 96);
        $same = $this->photo('same.png', 96, 96);
        $higher = $this->photo('higher.png', 96, 96);

        WebpCopy::make($default);
        WebpCopy::make($lower, 81);
        WebpCopy::make($same, 82);
        WebpCopy::make($higher, 83);

        $this->assertFileEquals($default.'.webp', $same.'.webp');
        $this->assertFileNotEquals($default.'.webp', $lower.'.webp');
        $this->assertFileNotEquals($default.'.webp', $higher.'.webp');
    }

    public function test_the_default_width_limit_is_1920_pixels(): void
    {
        $exact = $this->heavy('exact.png', 1920, 2);
        $over = $this->heavy('over.png', 1921, 2);
        $under = $this->heavy('under.png', 1919, 2);

        foreach ([$exact, $over, $under] as $source) {
            $this->assertSame('made', WebpCopy::make($source)['status']);
        }

        $this->assertSame([1920, 2], $this->sizeOfCopy($exact));
        $this->assertSame([1920, 2], $this->sizeOfCopy($over));
        $this->assertSame([1919, 2], $this->sizeOfCopy($under));
    }

    public function test_a_copy_exactly_as_heavy_as_the_source_is_thrown_away_and_one_byte_lighter_kept(): void
    {
        $probe = WebpCopy::make($this->weighing($this->badge('probe.png'), 5000), 100);
        $this->assertSame('made', $probe['status']);
        $copy = $probe['after'];

        $this->assertSame(['status' => 'rejected'], WebpCopy::make($this->weighing($this->badge('lighter.png'), $copy - 1), 100));
        $this->assertSame(['status' => 'rejected'], WebpCopy::make($this->weighing($this->badge('equal.png'), $copy), 100));
        $this->assertFileDoesNotExist("{$this->dir}/equal.png.webp");
        $this->assertSame(
            ['status' => 'made', 'before' => $copy + 1, 'after' => $copy],
            WebpCopy::make($this->weighing($this->badge('heavier.png'), $copy + 1), 100),
        );
    }

    public function test_a_copy_whose_source_is_gone_is_not_a_copy_to_skip(): void
    {
        file_put_contents("{$this->dir}/orphan.png.webp", 'left behind');

        $this->assertSame(['status' => 'failed'], WebpCopy::make("{$this->dir}/orphan.png"));
    }

    public function test_the_pixel_limit_counts_width_times_height_and_stops_above_it(): void
    {
        $source = $this->heavy('ten.png', 10, 10);

        $this->assertSame('made', WebpCopy::make($source, maxPixels: 100)['status']);
        $this->assertSame(['status' => 'failed'], WebpCopy::make($source, force: true, maxPixels: 99));
        // 20 x 5 is 100 pixels too, though 20 / 5 is 4.
        $this->assertSame('made', WebpCopy::make($this->heavy('wide.png', 20, 5), maxPixels: 100)['status']);
    }

    public function test_the_height_of_a_scaled_picture_is_rounded_to_the_nearest_pixel(): void
    {
        $down = $this->heavy('down.png', 1000, 3);
        $up = $this->heavy('up.png', 1000, 3);

        WebpCopy::make($down, 82, 410);
        WebpCopy::make($up, 82, 590);

        // 3 * 410 / 1000 = 1.23 and 3 * 590 / 1000 = 1.77.
        $this->assertSame([410, 1], $this->sizeOfCopy($down));
        $this->assertSame([590, 2], $this->sizeOfCopy($up));
    }

    public function test_scaling_keeps_every_part_of_the_picture_in_its_place(): void
    {
        $image = imagecreatetruecolor(16, 16);
        imagefill($image, 0, 0, 0x0000FF);
        imagefilledrectangle($image, 0, 0, 7, 7, 0xFF0000);
        $source = "{$this->dir}/quarters.png";
        imagepng($image, $source);
        $this->weighing($source, 100_000);

        $this->assertSame('made', WebpCopy::make($source, 101, 8)['status']);

        $copy = imagecreatefromwebp($source.'.webp');
        $this->assertNotFalse($copy);
        foreach ([[0, 0], [3, 0], [0, 3], [3, 3]] as [$x, $y]) {
            $this->assertSame(0xFF0000, imagecolorat($copy, $x, $y) & 0xFFFFFF, "red at {$x},{$y}");
        }
        foreach ([[4, 0], [0, 4], [4, 4], [7, 7], [7, 0], [0, 7]] as [$x, $y]) {
            $this->assertSame(0x0000FF, imagecolorat($copy, $x, $y) & 0xFFFFFF, "blue at {$x},{$y}");
        }
    }

    public function test_transparency_survives_scaling_too(): void
    {
        $image = imagecreatetruecolor(64, 8);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 200, 10, 10, 127));
        imagefilledrectangle($image, 32, 0, 63, 7, (int) imagecolorallocatealpha($image, 10, 10, 200, 0));
        $source = "{$this->dir}/scaled-transparent.png";
        imagepng($image, $source);
        $this->weighing($source, 100_000);

        $this->assertSame('made', WebpCopy::make($source, 101, 32)['status']);

        $copy = imagecreatefromwebp($source.'.webp');
        $this->assertNotFalse($copy);
        $this->assertSame(127, (imagecolorat($copy, 5, 2) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($copy, 25, 2) >> 24) & 0x7F);
    }

    public function test_a_palette_png_with_a_transparent_colour_keeps_it(): void
    {
        $image = imagecreate(32, 8);
        $clear = (int) imagecolorallocate($image, 0, 0, 0);
        imagecolortransparent($image, $clear);
        $red = (int) imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 16, 0, 31, 7, $red);
        $source = "{$this->dir}/palette.png";
        imagepng($image, $source);
        $this->weighing($source, 100_000);

        $this->assertSame('made', WebpCopy::make($source, 101)['status']);

        $copy = imagecreatefromwebp($source.'.webp');
        $this->assertNotFalse($copy);
        $this->assertSame(127, (imagecolorat($copy, 5, 2) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($copy, 25, 2) >> 24) & 0x7F);
        $this->assertSame(0xFF0000, imagecolorat($copy, 25, 2) & 0xFFFFFF);
    }
}
