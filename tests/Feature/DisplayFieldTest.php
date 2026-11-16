<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Receipt;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

final class DisplayFieldTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Receipt::class]);
    }

    public function test_the_edit_form_gets_the_html_drawn_from_the_record(): void
    {
        $receipt = Receipt::query()->create(['name' => 'A & B']);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/receipts/{$receipt->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.summary', '<p>Receipt <b>A &amp; B</b></p>')
                ->where('fields.1.type', 'display'));
    }

    public function test_it_is_not_on_the_create_form(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/receipts/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('fields', 1));
    }

    public function test_a_submitted_value_is_ignored(): void
    {
        $receipt = Receipt::query()->create(['name' => 'Old']);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/receipts/{$receipt->id}", ['name' => 'New', 'summary' => '<script>x</script>'])
            ->assertRedirect(route('admin.receipts.index'));

        $this->assertSame('New', $receipt->fresh()?->getAttribute('name'));
    }
}
