<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Files, pictures and galleries: a stored path is checked as a path, an
 * upload as a file.
 */
final class FileFieldsTest extends FieldTestCase
{
    /** @var list<string> */
    private static array $temporary = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter(self::$temporary, is_file(...)));
        self::$temporary = [];

        parent::tearDown();
    }

    /**
     * An upload whose type is judged by its content, as on a real request
     * (a fake judges by the name).
     */
    private static function real(string $name, string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'atrium-upload-');
        file_put_contents($path, $content);
        self::$temporary[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(FileField|ImageField $field, UploadedFile $file): bool
    {
        return $this->passes($field, $file, FieldContext::create($file, true));
    }

    public function test_a_file_field_takes_any_file_up_to_ten_megabytes(): void
    {
        $field = FileField::make('document');

        $this->assertSame('file', $field->getType());
        $this->assertSame(['disk' => 'public', 'directory' => 'uploads', 'maxSize' => 10240, 'acceptedTypes' => [], 'accept' => null], array_intersect_key($field->toArray(), array_flip(['disk', 'directory', 'maxSize', 'acceptedTypes', 'accept'])));
    }

    public function test_a_file_field_takes_its_storage_and_limits(): void
    {
        $array = FileField::make('document')->disk('s3')->directory('docs/2026')->maxSize(500)->acceptedTypes(['text/csv'])->accept('.csv')->toArray();

        $this->assertSame('s3', $array['disk']);
        $this->assertSame('docs/2026', $array['directory']);
        $this->assertSame(500, $array['maxSize']);
        $this->assertSame(['text/csv'], $array['acceptedTypes']);
        $this->assertSame('.csv', $array['accept']);
    }

    public function test_documents_and_pdf_set_what_the_picker_offers(): void
    {
        $documents = FileField::make('a')->documents()->toArray();
        $pdf = FileField::make('a')->pdf()->toArray();

        $this->assertSame(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $documents['acceptedTypes']);
        $this->assertSame('.pdf,.doc,.docx', $documents['accept']);
        $this->assertSame(['application/pdf'], $pdf['acceptedTypes']);
        $this->assertSame('.pdf', $pdf['accept']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function storedPaths(): array
    {
        return [
            'a path' => ['/upload/iblock/b5a/manual.pdf', true],
            'a Cyrillic name' => ['/upload/инструкция.pdf', true],
            'the longest path allowed' => ['/'.str_repeat('a', 2047), true],
            'one too long' => ['/'.str_repeat('a', 2048), false],
            'empty, the field is optional' => [null, true],
            'an array' => [['/a.pdf'], false],
        ];
    }

    #[DataProvider('storedPaths')]
    public function test_a_stored_path_is_checked_as_text_not_as_a_file(mixed $value, bool $passes): void
    {
        foreach ([FileField::make('document')->pdf(), ImageField::make('picture')] as $field) {
            $this->assertSame(['nullable', 'string', 'max:2048'], $field->getValidationRules(FieldContext::edit(null, $value, true)));
            $this->assertSame($passes, $this->passes($field, $value, FieldContext::edit(null, $value, true)), $field->getType());
        }
    }

    public function test_an_uploaded_file_is_checked_for_size_and_type(): void
    {
        $file = UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf');

        $this->assertSame(['nullable', 'file', 'max:10240'], FileField::make('document')->getValidationRules(FieldContext::create($file, true)));
        $this->assertSame(['nullable', 'file', 'max:10', 'mimes:pdf'], FileField::make('document')->pdf()->maxSize(10)->getValidationRules(FieldContext::create($file, true)));
        $this->assertSame(['nullable', 'file', 'max:10240', 'mimes:pdf,msword,vnd.openxmlformats-officedocument.wordprocessingml.document'], FileField::make('document')->documents()->getValidationRules(FieldContext::create($file, true)));
    }

    /**
     * @return array<string, array{\Closure(): UploadedFile, bool}>
     */
    public static function uploads(): array
    {
        return [
            'a PDF' => [fn (): UploadedFile => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'), true],
            'exactly the largest size' => [fn (): UploadedFile => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'), true],
            'one kilobyte too large' => [fn (): UploadedFile => UploadedFile::fake()->create('manual.pdf', 101, 'application/pdf'), false],
            'a Cyrillic name' => [fn (): UploadedFile => UploadedFile::fake()->create('инструкция.pdf', 10, 'application/pdf'), true],
            'a picture' => [fn (): UploadedFile => UploadedFile::fake()->create('photo.png', 10, 'image/png'), false],
            'a real PDF' => [fn (): UploadedFile => self::real('manual.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"), true],
            'PHP named as a PDF' => [fn (): UploadedFile => self::real('manual.pdf', '<?php system($_GET["c"]);'), false],
        ];
    }

    #[DataProvider('uploads')]
    public function test_a_pdf_field_takes_only_pdf_files_within_the_size(\Closure $file, bool $passes): void
    {
        $this->assertSame($passes, $this->upload(FileField::make('document')->pdf()->maxSize(100), $file()));
    }

    public function test_a_file_field_without_types_takes_any_upload_within_the_size(): void
    {
        $field = FileField::make('document')->maxSize(5);

        $this->assertTrue($this->upload($field, UploadedFile::fake()->create('notes.txt', 5, 'text/plain')));
        $this->assertFalse($this->upload($field, UploadedFile::fake()->create('notes.txt', 6, 'text/plain')));
    }

    public function test_an_image_field_takes_web_pictures_up_to_five_megabytes(): void
    {
        $field = ImageField::make('picture');
        $array = $field->toArray();

        $this->assertSame('image', $field->getType());
        $this->assertSame('public', $array['disk']);
        $this->assertSame('uploads', $array['directory']);
        $this->assertSame(5120, $array['maxSize']);
        $this->assertSame(['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $array['acceptedTypes']);
        $this->assertSame('image/jpeg,image/png,image/gif,image/webp', $array['accept']);
        $this->assertNull($array['maxWidth']);
        $this->assertNull($array['maxHeight']);
        $this->assertTrue($array['showPreview']);
    }

    public function test_an_image_field_takes_its_storage_and_limits(): void
    {
        $array = ImageField::make('picture')->disk('s3')->directory('photos')->maxSize(100)->acceptedTypes(['image/png'])->maxDimensions(800, 600)->showPreview(false)->toArray();

        $this->assertSame('s3', $array['disk']);
        $this->assertSame('photos', $array['directory']);
        $this->assertSame(100, $array['maxSize']);
        $this->assertSame(['image/png'], $array['acceptedTypes']);
        $this->assertSame('image/png', $array['accept']);
        $this->assertSame(800, $array['maxWidth']);
        $this->assertSame(600, $array['maxHeight']);
        $this->assertFalse($array['showPreview']);
    }

    public function test_an_uploaded_picture_is_checked_for_type_size_and_dimensions(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 10, 10);

        $this->assertSame(['nullable', 'image', 'max:5120', 'mimes:jpeg,png,gif,webp'], ImageField::make('picture')->getValidationRules(FieldContext::create($file, true)));
        $this->assertSame(['nullable', 'image', 'max:5120', 'mimes:jpeg,png,gif,webp', 'dimensions:max_width=800,max_height=600'], ImageField::make('picture')->maxDimensions(800, 600)->getValidationRules(FieldContext::create($file, true)));
        $this->assertSame(['nullable', 'image', 'max:5120', 'mimes:jpeg,png,gif,webp'], ImageField::make('picture')->maxDimensions(0, 600)->getValidationRules(FieldContext::create($file, true)), 'a zero width means no limit');
        $this->assertSame(['nullable', 'image', 'max:5120', 'mimes:jpeg,png,gif,webp'], ImageField::make('picture')->maxDimensions(800, 0)->getValidationRules(FieldContext::create($file, true)), 'a zero height means no limit');
    }

    /**
     * @return array<string, array{\Closure(): UploadedFile, bool}>
     */
    public static function pictures(): array
    {
        return [
            'a PNG' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.png', 100, 50), true],
            'a JPEG' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.jpg', 100, 50), true],
            'a GIF' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.gif', 100, 50), true],
            'the largest dimensions' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.png', 200, 100), true],
            'one pixel too wide' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.png', 201, 100), false],
            'one pixel too high' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.png', 200, 101), false],
            'too heavy' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.png', 10, 10)->size(51), false],
            'a Unicode name' => [fn (): UploadedFile => UploadedFile::fake()->image('фото 照片.png', 10, 10), true],
            'a BMP is not a web picture' => [fn (): UploadedFile => UploadedFile::fake()->image('photo.bmp', 10, 10), false],
            'a PDF' => [fn (): UploadedFile => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf'), false],
            'PHP named as a picture' => [fn (): UploadedFile => self::real('photo.jpg', '<?php system($_GET["c"]);'), false],
            'an SVG with a script' => [fn (): UploadedFile => self::real('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), false],
            'an SVG named as a PNG' => [fn (): UploadedFile => self::real('photo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), false],
        ];
    }

    #[DataProvider('pictures')]
    public function test_an_image_field_takes_only_web_pictures_within_the_limits(\Closure $file, bool $passes): void
    {
        $this->assertSame($passes, $this->upload(ImageField::make('picture')->maxSize(50)->maxDimensions(200, 100), $file()));
    }

    public function test_a_gallery_is_a_sortable_list_of_up_to_twenty_pictures(): void
    {
        $field = GalleryField::make('photos');
        $array = $field->toArray();

        $this->assertSame('gallery', $field->getType());
        $this->assertTrue($array['multiple']);
        $this->assertSame(20, $array['maxFiles']);
        $this->assertSame(0, $array['minFiles']);
        $this->assertTrue($array['sortable']);
        $this->assertSame(5120, $array['maxSize'], 'the picture settings are kept');
        $this->assertSame(['nullable', 'array'], $field->getValidationRules());
    }

    public function test_the_default_of_twenty_is_a_hint_not_a_limit(): void
    {
        $this->assertTrue($this->passes(GalleryField::make('photos'), array_fill(0, 45, '/upload/a.jpg')));
    }

    public function test_a_gallery_takes_its_counts_and_sorting(): void
    {
        $field = GalleryField::make('photos')->minFiles(2)->maxFiles(3)->sortable(false);
        $array = $field->toArray();

        $this->assertSame(2, $array['minFiles']);
        $this->assertSame(3, $array['maxFiles']);
        $this->assertFalse($array['sortable']);
        $this->assertSame(['nullable', 'array', 'min:2', 'max:3'], $field->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function galleries(): array
    {
        return [
            'the fewest allowed' => [['/a.jpg', '/b.jpg'], true],
            'the most allowed' => [['/a.jpg', '/b.jpg', '/c.jpg'], true],
            'one too few' => [['/a.jpg'], false],
            'one too many' => [['/a.jpg', '/b.jpg', '/c.jpg', '/d.jpg'], false],
            'empty, the field is optional' => [null, true],
            'one path, not a list' => ['/a.jpg', false],
        ];
    }

    #[DataProvider('galleries')]
    public function test_a_gallery_refuses_too_few_or_too_many_pictures(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(GalleryField::make('photos')->minFiles(2)->maxFiles(3), $value));
    }

    public function test_zero_counts_set_explicitly_are_no_limit(): void
    {
        $this->assertSame(['nullable', 'array'], GalleryField::make('photos')->minFiles(0)->maxFiles(0)->getValidationRules());
        $this->assertSame(['nullable', 'array', 'min:1'], GalleryField::make('photos')->minFiles(1)->getValidationRules());
        $this->assertSame(['nullable', 'array', 'max:1'], GalleryField::make('photos')->maxFiles(1)->getValidationRules());
    }

    public function test_a_gallery_keeps_explicit_rules_and_has_no_picture_rules_for_an_upload(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $this->assertSame(['required', 'distinct', 'array'], GalleryField::make('photos')->required()->rules('distinct')->getValidationRules(FieldContext::create($file, true)));
    }
}
