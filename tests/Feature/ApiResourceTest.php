<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\ApiArticle;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\SiteAdmin;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * Admin modules through the API with a token: list, read, create, update,
 * delete and upload, and every way to be refused.
 */
final class ApiResourceTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Page::class, ApiArticle::class, Note::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @return array<string,array{0:\Closure(AdminUser):array<string,string>}>
     */
    public static function badTokens(): array
    {
        return [
            'no header' => [static fn (AdminUser $owner): array => []],
            'empty bearer' => [static fn (AdminUser $owner): array => ['Authorization' => 'Bearer ']],
            'not a bearer' => [static fn (AdminUser $owner): array => ['Authorization' => 'Basic '.base64_encode('admin@example.com:secret-password')]],
            'made up' => [static fn (AdminUser $owner): array => ['Authorization' => 'Bearer 1|made-up-token']],
            'right id, wrong secret' => [static function (AdminUser $owner): array {
                $token = $owner->createToken('agent', ['posts:read']);

                return ['Authorization' => 'Bearer '.$token->accessToken->id.'|wrong'];
            }],
            'revoked' => [static function (AdminUser $owner): array {
                $token = $owner->createToken('agent', ['posts:read']);
                $token->accessToken->delete();

                return ['Authorization' => 'Bearer '.$token->plainTextToken];
            }],
            'expired a second ago' => [static function (AdminUser $owner): array {
                $token = $owner->createToken('agent', ['posts:read'], now()->subSecond());

                return ['Authorization' => 'Bearer '.$token->plainTextToken];
            }],
            'owner switched off' => [static function (AdminUser $owner): array {
                $token = $owner->createToken('agent', ['posts:read']);
                $owner->update(['is_active' => false]);

                return ['Authorization' => 'Bearer '.$token->plainTextToken];
            }],
            'owner is not an administrator' => [static function (AdminUser $owner): array {
                $post = Post::query()->create(['name' => 'Not a person']);
                $token = PersonalAccessToken::query()->forceCreate([
                    'tokenable_type' => Post::class,
                    'tokenable_id' => $post->id,
                    'name' => 'odd',
                    'token' => hash('sha256', 'odd-secret'),
                    'abilities' => ['*'],
                ]);

                return ['Authorization' => 'Bearer '.$token->id.'|odd-secret'];
            }],
            'the configured model is not an administrator' => [static function (AdminUser $owner): array {
                config(['atrium.auth.model' => PanelAccount::class]);
                $token = PersonalAccessToken::query()->forceCreate([
                    'tokenable_type' => PanelAccount::class,
                    'tokenable_id' => $owner->id,
                    'name' => 'odd',
                    'token' => hash('sha256', 'odd-secret'),
                    'abilities' => ['*'],
                ]);

                return ['Authorization' => 'Bearer '.$token->id.'|odd-secret'];
            }],
            'an administrator of another model than the configured one' => [static function (AdminUser $owner): array {
                config(['atrium.auth.model' => SiteAdmin::class]);
                $token = $owner->createToken('agent', ['posts:read']);

                return ['Authorization' => 'Bearer '.$token->plainTextToken];
            }],
        ];
    }

    /**
     * @param  \Closure(AdminUser):array<string,string>  $headers
     */
    #[DataProvider('badTokens')]
    public function test_a_request_without_a_valid_token_is_refused(\Closure $headers): void
    {
        $owner = $this->admin();
        Post::query()->create(['name' => 'Hello']);

        $this->withHeaders($headers($owner))
            ->get('/api/v1/posts')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'A valid API token is required: Authorization: Bearer <token> header.']);

        $this->assertGuest('admin');
    }

    public function test_a_token_that_expires_later_works_and_marks_its_use(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $owner = $this->admin();
        $token = $owner->createToken('agent', ['posts:read'], now()->addSecond());

        $this->withToken($token->plainTextToken)->getJson('/api/v1/posts')->assertOk();

        $this->assertSame('2026-11-17 12:00:00', $token->accessToken->fresh()->last_used_at->format('Y-m-d H:i:s'));
        $this->assertAuthenticatedAs($owner, 'admin');
    }

    public function test_answers_are_json_whatever_the_client_asks_for(): void
    {
        $this->withToken($this->token())
            ->withHeader('Accept', 'text/html')
            ->post('/api/v1/posts', ['sort' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_list_comes_with_page_details_and_addresses(): void
    {
        $hello = Post::query()->create(['name' => 'Hello']);

        $this->api()->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1])
            ->assertJsonPath('data.0.id', $hello->id)
            ->assertJsonPath('data.0.name', 'Hello')
            ->assertJsonPath('data.0.admin_url', "http://localhost/admin/posts/{$hello->id}/edit")
            ->assertJsonPath('data.0.public_url', null);
    }

    /**
     * @return array<string,array{0:mixed,1:int,2:int}>
     */
    public static function perPage(): array
    {
        return [
            'default' => [null, 20, 2],
            'one' => [1, 1, 25],
            'zero becomes one' => [0, 1, 25],
            'negative becomes one' => [-5, 1, 25],
            'not a number becomes one' => ['many', 1, 25],
            'the most' => [100, 100, 1],
            'over the most' => [101, 100, 1],
            'the largest integer' => [PHP_INT_MAX, 100, 1],
        ];
    }

    #[DataProvider('perPage')]
    public function test_a_page_holds_one_to_a_hundred_records(mixed $perPage, int $expected, int $lastPage): void
    {
        foreach (range(1, 25) as $i) {
            Post::query()->create(['name' => "Post {$i}"]);
        }

        $query = $perPage === null ? '' : '?per_page='.$perPage;

        $this->api()->getJson('/api/v1/posts'.$query)
            ->assertOk()
            ->assertJsonPath('meta.per_page', $expected)
            ->assertJsonPath('meta.last_page', $lastPage)
            ->assertJsonCount(min($expected, 25), 'data');
    }

    public function test_the_list_is_filtered_by_module_fields_only(): void
    {
        Post::query()->create(['name' => 'Hello', 'active' => true]);
        Post::query()->create(['name' => 'Bye', 'active' => false]);

        $this->assertSame(['Hello'], $this->names('/api/v1/posts?filter[name]=Hello'));
        $this->assertSame(['Bye'], $this->names('/api/v1/posts?filter[active]=0'));
        $this->assertSame(['Hello'], $this->names('/api/v1/posts?filter[active]=1&filter[name]=Hello'));
        $this->assertSame([], $this->names('/api/v1/posts?filter[active]=1&filter[name]=Bye'));
        $this->assertSame(['Hello', 'Bye'], $this->names('/api/v1/posts?filter[password]=x'), 'Not a field of the module: ignored.');
        $this->assertSame(['Hello', 'Bye'], $this->names('/api/v1/posts?filter[name)=1 or 1=1--]=x'));
        $this->assertSame(['Hello', 'Bye'], $this->names('/api/v1/posts?filter=name'));
    }

    public function test_the_list_is_searched_in_the_searchable_columns(): void
    {
        Post::query()->create(['name' => 'Garden chair']);
        Post::query()->create(['name' => 'Kitchen table']);
        Post::query()->create(['name' => 'Садовый стул']);

        $this->assertSame(['Garden chair'], $this->names('/api/v1/posts?search=chair'));
        $this->assertSame(['Садовый стул'], $this->names('/api/v1/posts?search='.urlencode('стул')));
        $this->assertSame([], $this->names('/api/v1/posts?search=sofa'));
        $this->assertSame(['Garden chair', 'Kitchen table', 'Садовый стул'], $this->names('/api/v1/posts?search='));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function literalSearches(): array
    {
        return [
            'a percent sign is a percent sign' => ['50%', '50% off'],
            'an underscore is an underscore' => ['a_c', 'a_c'],
            'the escape character is a character' => ['Sale!', 'Big Sale!'],
            'the escape character alone' => ['!', 'Big Sale!'],
            'capitals find small letters' => ['APPLE', 'Apple'],
        ];
    }

    #[DataProvider('literalSearches')]
    public function test_the_search_takes_the_words_literally_and_ignores_case(string $search, string $found): void
    {
        foreach (['50% off', '500 rooms', 'a_c', 'abc', 'Big Sale!', 'Big Sales', 'Apple'] as $name) {
            Post::query()->create(['name' => $name]);
        }

        $this->assertSame([$found], $this->names('/api/v1/posts?search='.urlencode($search)));
    }

    #[DataProvider('literalSearches')]
    public function test_a_translatable_column_takes_the_words_literally_and_ignores_case(string $search, string $found): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        foreach (['50% off', '500 rooms', 'a_c', 'abc', 'Big Sale!', 'Big Sales', 'Apple'] as $name) {
            Page::query()->create(['name' => ['en' => 'Page', 'de' => $name]]);
        }

        $this->assertSame([$found], array_column($this->withToken($this->token(['pages:read']))->getJson('/api/v1/pages?search='.urlencode($search))->json('data.*.name'), 'de'));
    }

    public function test_a_translatable_column_is_searched_and_sorted_in_every_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Page::query()->create(['name' => ['en' => 'Beta', 'de' => 'Apfel']]);
        Page::query()->create(['name' => ['en' => 'Alpha', 'de' => 'Birne']]);

        $token = $this->token(['pages:read']);

        $this->assertEquals([['en' => 'Beta', 'de' => 'Apfel']], $this->withToken($token)->getJson('/api/v1/pages?search=apf')->json('data.*.name'));
        $this->assertSame(['Alpha', 'Beta'], array_column($this->withToken($token)->getJson('/api/v1/pages?sort=name')->json('data.*.name'), 'en'));
        $this->assertSame(['Beta', 'Alpha'], array_column($this->withToken($token)->getJson('/api/v1/pages?sort=name&direction=desc')->json('data.*.name'), 'en'));
    }

    public function test_the_list_is_sorted_by_module_fields_only(): void
    {
        Post::query()->create(['name' => 'B', 'sort' => 1]);
        Post::query()->create(['name' => 'C', 'sort' => 3]);
        Post::query()->create(['name' => 'A', 'sort' => 2]);

        $this->assertSame(['B', 'C', 'A'], $this->names('/api/v1/posts'));
        $this->assertSame(['A', 'B', 'C'], $this->names('/api/v1/posts?sort=name'));
        $this->assertSame(['C', 'B', 'A'], $this->names('/api/v1/posts?sort=name&direction=desc'));
        $this->assertSame(['B', 'A', 'C'], $this->names('/api/v1/posts?sort=sort&direction=asc'));
        $this->assertSame(['B', 'A', 'C'], $this->names('/api/v1/posts?sort=sort&direction=sideways'));
        $this->assertSame(['A', 'C', 'B'], $this->names('/api/v1/posts?sort=id&direction=desc'));
        $this->assertSame(['A', 'C', 'B'], $this->names('/api/v1/posts?sort=password&direction=desc'), 'Not a field: by id.');
        $this->assertSame(['B', 'C', 'A'], $this->names('/api/v1/posts?sort=name;drop table posts'));
    }

    public function test_one_record_is_read_by_id(): void
    {
        $hello = Post::query()->create(['name' => 'Hello', 'sort' => 7]);

        $this->api()->getJson("/api/v1/posts/{$hello->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $hello->id)
            ->assertJsonPath('data.name', 'Hello')
            ->assertJsonPath('data.sort', 7)
            ->assertJsonPath('data.admin_url', "http://localhost/admin/posts/{$hello->id}/edit");
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function missing(): array
    {
        return [
            'unknown id' => ['getJson', '/api/v1/posts/999'],
            'unknown id on update' => ['patchJson', '/api/v1/posts/999'],
            'unknown id on delete' => ['deleteJson', '/api/v1/posts/999'],
            'id that is not a number' => ['getJson', '/api/v1/posts/abc'],
            'negative id' => ['getJson', '/api/v1/posts/-1'],
            'module in capitals' => ['getJson', '/api/v1/Posts'],
            'module with a dash' => ['getJson', '/api/v1/api-articles'],
        ];
    }

    #[DataProvider('missing')]
    public function test_a_missing_record_or_address_is_a_404(string $method, string $url): void
    {
        Post::query()->create(['name' => 'Hello']);

        $this->api()->{$method}($url, ['name' => 'Changed'])->assertNotFound();

        $this->assertSame('Hello', Post::query()->sole()->name);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function unknownModules(): array
    {
        return [
            'unknown' => ['nope'],
            'administrators are left out of the API' => ['users'],
            'digits' => ['123'],
        ];
    }

    #[DataProvider('unknownModules')]
    public function test_an_unknown_module_says_where_the_list_is(string $module): void
    {
        $this->api()->getJson("/api/v1/{$module}")
            ->assertNotFound()
            ->assertJsonPath('message', 'No such module. See the list in the documentation: /api/v1');
        $this->api()->postJson("/api/v1/{$module}", ['name' => 'x'])->assertNotFound();
    }

    public function test_a_record_is_created_and_returned(): void
    {
        $response = $this->api()->postJson('/api/v1/posts', ['name' => 'Garden chair 🪑', 'active' => false, 'sort' => 5])
            ->assertCreated();

        $post = Post::query()->sole();
        $response->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.name', 'Garden chair 🪑')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.sort', 5);
        $this->assertSame('Garden chair 🪑', $post->name);
        $this->assertFalse($post->active);
    }

    public function test_fields_not_on_the_form_are_ignored_on_create_and_update(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        $this->api()->postJson('/api/v1/posts', [
            'name' => 'Hello', 'id' => 777, 'created_at' => '2000-01-01 00:00:00', 'is_superadmin' => true,
        ])->assertCreated();

        $post = Post::query()->sole();
        $this->assertNotSame(777, $post->id);
        $this->assertSame('2026-11-17', $post->created_at->format('Y-m-d'));

        $this->api()->patchJson("/api/v1/posts/{$post->id}", ['id' => 778, 'created_at' => '2000-01-01'])->assertOk();
        $this->assertSame(1, Post::query()->whereKey($post->id)->count());
        $this->assertSame('2026-11-17', $post->fresh()->created_at->format('Y-m-d'));
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalidRecords(): array
    {
        return [
            'no name' => [['sort' => 1], 'name'],
            'empty name' => [['name' => ''], 'name'],
            'name as an array' => [['name' => ['Hello']], 'name'],
            'name past the TEXT ceiling' => [['name' => str_repeat('a', 65536)], 'name'],
            'sort not a number' => [['name' => 'Hello', 'sort' => 'first'], 'sort'],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('invalidRecords')]
    public function test_an_invalid_record_is_refused_with_the_field_named(array $data, string $field): void
    {
        $this->api()->postJson('/api/v1/posts', $data)->assertStatus(422)->assertJsonValidationErrors($field);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_an_update_changes_only_the_fields_sent(): void
    {
        $post = Post::query()->create(['name' => 'Hello', 'active' => true, 'sort' => 1]);

        $this->api()->patchJson("/api/v1/posts/{$post->id}", ['sort' => 9])
            ->assertOk()
            ->assertJsonPath('data.sort', 9)
            ->assertJsonPath('data.name', 'Hello');

        $this->assertSame('Hello', $post->fresh()->name);
        $this->assertSame(9, $post->fresh()->sort);
        $this->assertTrue($post->fresh()->active);
    }

    public function test_a_field_sent_on_update_is_still_validated(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);

        $this->api()->putJson("/api/v1/posts/{$post->id}", ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame('Hello', $post->fresh()->name);
    }

    public function test_a_record_is_deleted_and_the_hook_hears_of_it(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $heard = [];
        Hook::on('atrium.record.deleted', function ($item) use (&$heard): void {
            $heard[] = $item->name;
        });

        $this->api()->deleteJson("/api/v1/posts/{$post->id}")->assertNoContent();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(['Hello'], $heard);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function undeletable(): array
    {
        return [
            'the record refuses' => ['Locked'],
            'its delete hook refuses' => ['Kept'],
        ];
    }

    #[DataProvider('undeletable')]
    public function test_a_record_that_may_not_be_deleted_stays(string $name): void
    {
        $article = ApiArticle::query()->create(['name' => $name]);
        $heard = false;
        Hook::on('atrium.record.deleted', function () use (&$heard): void {
            $heard = true;
        });

        $this->withToken($this->token(['api_articles:read', 'api_articles:write']))
            ->deleteJson("/api/v1/api_articles/{$article->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This record cannot be deleted.']);

        $this->assertTrue(ApiArticle::query()->whereKey($article->id)->exists());
        $this->assertFalse($heard);
    }

    public function test_seo_fields_are_saved_and_read_back_flat(): void
    {
        $token = $this->token(['api_articles:read', 'api_articles:write']);

        $created = $this->withToken($token)
            ->postJson('/api/v1/api_articles', ['name' => 'Hello', 'seo_title' => 'Hello | Shop'])
            ->assertCreated()
            ->assertJsonPath('data.seo_title', 'Hello | Shop')
            ->assertJsonMissingPath('data.seo_meta')
            ->assertJsonMissingPath('data.fullPath');

        $this->assertSame('Hello | Shop', SeoMeta::query()->sole()->title);

        $this->withToken($token)
            ->getJson('/api/v1/api_articles/'.$created->json('data.id'))
            ->assertJsonPath('data.seo_title', 'Hello | Shop')
            ->assertJsonMissingPath('data.seo_meta');
    }

    public function test_a_file_is_uploaded_with_a_token_that_may_write(): void
    {
        Storage::fake('public');

        $this->withToken($this->token(['posts:read', 'posts:write'], ['posts.view', 'posts.create']))
            ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('offer.pdf', "%PDF-1.4\n%%EOF\n")])
            ->assertOk()
            ->assertJsonPath('filename', 'offer.pdf')
            ->assertJsonPath('extension', 'pdf');

        $this->assertSame(1, MediaItem::query()->count());
    }

    public function test_a_superadmin_token_may_upload(): void
    {
        Storage::fake('public');
        $token = $this->admin()->createToken('agent', ['posts:write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('offer.pdf', "%PDF-1.4\n%%EOF\n")])->assertOk();

        $this->assertSame(1, MediaItem::query()->count());
    }

    public function test_a_read_only_token_cannot_upload(): void
    {
        Storage::fake('public');

        $this->withToken($this->token(['posts:read', 'pages:read'], ['posts.view', 'posts.create']))
            ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('offer.pdf', "%PDF-1.4\n%%EOF\n")])
            ->assertForbidden()
            ->assertJsonPath('message', 'The token is not allowed to change anything.');

        $this->assertSame(0, MediaItem::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_record_of_another_site_is_out_of_reach_on_this_one(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $mine = Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $main->id]);
        $theirs = Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        $token = $this->token(['notes:read', 'notes:write'], ['notes.view', 'notes.update', 'notes.delete']);

        $this->withToken($token)->getJson('/api/v1/notes?site=main')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
        $this->withToken($token)->getJson('/api/v1/notes?site=main&search=German')->assertJsonPath('meta.total', 0);
        $this->withToken($token)->getJson("/api/v1/notes/{$theirs->id}?site=main")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/notes/{$theirs->id}?site=main", ['name' => 'Hacked'])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/notes/{$theirs->id}?site=main")->assertNotFound();

        $this->assertSame('German note', Note::query()->withoutGlobalScopes()->findOrFail($theirs->id)->name);
        $this->withToken($token)->getJson("/api/v1/notes/{$theirs->id}?site=de")->assertOk()->assertJsonPath('data.name', 'German note');
    }

    private function api(): static
    {
        return $this->withToken($this->token());
    }

    /**
     * @return array<int,string>
     */
    private function names(string $url): array
    {
        return $this->api()->getJson($url)->assertOk()->json('data.*.name');
    }

    /**
     * A token of an administrator who may do everything in posts.
     *
     * @param  array<int,string>  $abilities
     * @param  array<int,string>|null  $permissions  null: a superadmin
     */
    private function token(array $abilities = ['posts:read', 'posts:write'], ?array $permissions = null): string
    {
        $this->app['auth']->forgetGuards();

        $owner = AdminUser::query()->where('email', 'owner@example.com')->first()
            ?? AdminUser::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password', 'is_active' => true]);

        if ($permissions === null) {
            $owner->forceFill(['is_superadmin' => true])->save();
        } else {
            $owner->roles()->attach(AdminRole::query()->create(['name' => 'Role '.AdminRole::query()->count(), 'permissions' => $permissions]));
        }

        return $owner->createToken('agent', $abilities)->plainTextToken;
    }
}
