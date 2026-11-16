<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class MapField extends Field
{
    protected string $mapType = 'yandex';

    protected float $defaultLat = 55.7558;

    protected float $defaultLng = 37.6173;

    protected int $defaultZoom = 10;

    protected bool $searchEnabled = true;

    public function getType(): string
    {
        return 'map';
    }

    /**
     * Set map type (yandex, google)
     */
    public function mapType(string $type): static
    {
        $this->mapType = $type;

        return $this;
    }

    /**
     * Use Yandex maps
     */
    public function yandex(): static
    {
        $this->mapType = 'yandex';

        return $this;
    }

    /**
     * Use Google maps
     */
    public function google(): static
    {
        $this->mapType = 'google';

        return $this;
    }

    /**
     * Set default center coordinates
     */
    public function defaultCenter(float $lat, float $lng): static
    {
        $this->defaultLat = $lat;
        $this->defaultLng = $lng;

        return $this;
    }

    /**
     * Set default zoom
     */
    public function defaultZoom(int $zoom): static
    {
        $this->defaultZoom = $zoom;

        return $this;
    }

    /**
     * Enable/disable address search
     */
    public function searchEnabled(bool $enabled = true): static
    {
        $this->searchEnabled = $enabled;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'mapType' => $this->mapType,
            'defaultLat' => $this->defaultLat,
            'defaultLng' => $this->defaultLng,
            'defaultZoom' => $this->defaultZoom,
            'searchEnabled' => $this->searchEnabled,
            'apiKey' => $this->mapType === 'yandex'
                ? config('atrium.maps.yandex_api_key')
                : config('atrium.maps.google_api_key'),
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        // No type rule on purpose. The docblock below says "JSON {lat,lng,address}",
        // but rows migrated from Bitrix are plain comma strings
        // ('61.79862,34.368981') even though the models cast the attribute to 'array'. An `array` rule
        // here would make every one of those records unsavable, and a `string`
        // rule would reject the JSON shape the field is documented to accept.
        return parent::getValidationRules($context);
    }

    public function transformValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
