<?php

declare(strict_types=1);

namespace Atrium\Admin\Support;

use GdImage;

/**
 * Облегчённая копия картинки: рядом с `photo.jpg` появляется `photo.jpg.webp`.
 *
 * Отдаёт её веб-сервер сам, по заголовку Accept (правило в `public/.htaccess`),
 * поэтому ни в базе, ни в шаблонах пути менять не нужно — а это важно: герой
 * главной и плитка галереи рисуют картинку через `background-image`, куда
 * `<picture>` не подставишь.
 *
 * Имя строится добавлением расширения, а не заменой: так по имени файла видно,
 * из чего копия сделана, и правило переписывания получается в одну строку.
 *
 * Раньше копии делала только консольная команда, и картинка, загруженная в
 * админке, ехала посетителям тяжёлой до ближайшего запуска по расписанию.
 * Теперь тем же кодом пользуется и загрузка.
 */
final class WebpCopy
{
    /** Больше 40 Мпикс не берём: такие исходники кладут PHP по памяти. */
    private const MAX_PIXELS = 40_000_000;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** Умеет ли эта сборка PHP писать webp. */
    public static function supported(): bool
    {
        return function_exists('imagewebp');
    }

    /** Стоит ли вообще пытаться: расширение из тех, что мы жмём. */
    public static function convertible(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Сделать копию.
     *
     * Возвращает не просто «получилось/нет»: команда `images:webp` из этих
     * состояний собирает отчёт, и «копия уже была» ей нужно отличать от
     * «картинка не открылась».
     *
     * - made     — копия сделана, в before/after её размеры
     * - skipped  — копия уже есть и не старше оригинала
     * - rejected — копия вышла не легче оригинала, удалена
     * - failed   — не открылась, слишком большая или webp не пишется
     *
     * @return array{status: string, before?: int, after?: int}
     */
    public static function make(string $source, int $quality = 82, int $maxWidth = 1920, bool $force = false): array
    {
        if (! self::supported() || ! is_file($source) || ! self::convertible($source)) {
            return ['status' => 'failed'];
        }

        $target = $source.'.webp';

        if (! $force && is_file($target) && filemtime($target) >= filemtime($source)) {
            return ['status' => 'skipped'];
        }

        $image = self::load($source);

        if ($image === null) {
            return ['status' => 'failed'];
        }

        $image = self::resize($image, $maxWidth);
        $ok = @imagewebp($image, $target, $quality);
        imagedestroy($image);

        if (! $ok) {
            return ['status' => 'failed'];
        }

        $before = (int) filesize($source);
        $after = (int) filesize($target);

        // Копия тяжелее исходника смысла не имеет — сервер отдаст оригинал.
        // Так бывает у палитровых PNG: плашки и логотипы webp проигрывает.
        if ($after >= $before) {
            @unlink($target);

            return ['status' => 'rejected'];
        }

        return ['status' => 'made', 'before' => $before, 'after' => $after];
    }

    private static function load(string $path): ?GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_PIXELS) {
            return null;
        }

        // Часть файлов с именем .jpg на деле уже webp — так их оставил модуль
        // старого сайта. Второй раз пережимать нечего.
        if ($info[2] === IMAGETYPE_WEBP) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        // Палитровые PNG иначе теряют цвета при масштабировании, а прозрачность
        // без этих двух строк заливается чёрным.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private static function resize(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);

        if ($maxWidth <= 0 || $width <= $maxWidth) {
            return $image;
        }

        $height = (int) round(imagesy($image) * $maxWidth / $width);
        $resized = imagecreatetruecolor($maxWidth, $height);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $height, $width, imagesy($image));
        imagedestroy($image);

        return $resized;
    }
}
