<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Http\UploadedFile;

class ImageField extends Field
{
    protected string $disk = 'public';

    protected string $directory = 'uploads';

    protected int $maxSize = 5120; // KB

    protected array $acceptedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    protected ?int $maxWidth = null;

    protected ?int $maxHeight = null;

    protected bool $showPreview = true;

    public function getType(): string
    {
        return 'image';
    }

    /**
     * Set storage disk
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set directory
     */
    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Set max file size in KB
     */
    public function maxSize(int $sizeKb): static
    {
        $this->maxSize = $sizeKb;

        return $this;
    }

    /**
     * Set accepted file types
     */
    public function acceptedTypes(array $types): static
    {
        $this->acceptedTypes = $types;

        return $this;
    }

    /**
     * Set max dimensions
     */
    public function maxDimensions(int $width, int $height): static
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Show/hide preview
     */
    public function showPreview(bool $show = true): static
    {
        $this->showPreview = $show;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'disk' => $this->disk,
            'directory' => $this->directory,
            'maxSize' => $this->maxSize,
            'acceptedTypes' => $this->acceptedTypes,
            'maxWidth' => $this->maxWidth,
            'maxHeight' => $this->maxHeight,
            'showPreview' => $this->showPreview,
            'accept' => implode(',', $this->acceptedTypes),
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        // The admin image control does not upload: AdminFieldRenderer.vue binds
        // a plain path string (e.g. "/upload/iblock/b5a/photo.png"), and the
        // @change handler is still a TODO stub. Applying image|mimes|max(KB)
        // unconditionally would therefore reject every save of every record
        // that already has a picture — 65 ImageFields across the models, 324
        // existing rows. File rules are only meaningful for a real upload, so
        // they are gated on one actually being submitted.
        if ($context?->value instanceof UploadedFile) {
            $rules[] = 'image';
            $rules[] = "max:{$this->maxSize}";
            $rules[] = 'mimes:jpeg,png,gif,webp';

            if ($this->maxWidth && $this->maxHeight) {
                $rules[] = "dimensions:max_width={$this->maxWidth},max_height={$this->maxHeight}";
            }

            return $rules;
        }

        // Stored path.
        $rules[] = 'string';
        $rules[] = 'max:2048';

        return $rules;
    }
}
