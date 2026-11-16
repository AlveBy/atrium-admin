<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Demo;

use Atrium\Admin\Demo\Models\Product;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

final class DemoOffTest extends TestCase
{
    public function test_without_the_flag_the_demo_is_nowhere(): void
    {
        $this->assertArrayNotHasKey('demo_products', $this->app->make(ResourceRegistry::class)->all());
        $this->assertNull(config('atrium.public_urls.'.Product::class));
        $this->assertFalse(Schema::hasTable('demo_products'));

        $this->actingAs($this->admin(), 'admin')->get('/admin/demo_products')->assertNotFound();
    }
}
