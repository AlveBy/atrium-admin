<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class HasAdminSeoTest extends TestCase
{
    public function test_the_title_from_the_seo_tab_wins_and_fills_its_placeholders(): void
    {
        $article = Handbook::query()->create(['name' => '<b>Chair</b>', 'code' => 'chair']);
        $article->updateSeoMeta(['title' => 'Buy {name} — {code}']);

        $this->assertSame('Buy Chair — chair', $article->fresh()?->getSeoTitle());
    }

    public function test_without_a_title_the_template_of_the_model_is_used(): void
    {
        config()->set('atrium.seo.templates.'.Handbook::class.'.title', '{name} in our shop');
        $article = Handbook::query()->create(['name' => 'Chair']);
        $article->updateSeoMeta(['title' => '', 'keywords' => 'chair']);

        $this->assertSame('Chair in our shop', $article->fresh()?->getSeoTitle());
    }

    public function test_without_a_title_or_template_the_name_then_the_title_is_used(): void
    {
        $this->assertSame('Chair', (new Handbook(['name' => 'Chair', 'title' => 'Seat']))->getSeoTitle());
        $this->assertSame('Seat', (new Handbook(['title' => 'Seat']))->getSeoTitle());
        $this->assertSame('', (new Handbook)->getSeoTitle());
    }

    public function test_an_unknown_placeholder_or_an_empty_relation_stays_as_it_is(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair']);
        $article->updateSeoMeta(['title' => '{price} {parent} {name}']);

        $this->assertSame('{price} {parent} Chair', $article->fresh()?->getSeoTitle());
    }

    public function test_a_placeholder_naming_a_related_record_gives_its_name(): void
    {
        $parent = Handbook::query()->create(['name' => 'Furniture']);
        $article = Handbook::query()->create(['name' => 'Chair', 'parent_id' => $parent->id]);
        $article->updateSeoMeta(['title' => '{name} in {parent}']);

        $this->assertSame('Chair in Furniture', $article->fresh()?->getSeoTitle());
    }

    public function test_a_related_record_without_a_name_leaves_its_placeholder(): void
    {
        $parent = Handbook::query()->create(['name' => null]);
        $article = Handbook::query()->create(['name' => 'Chair', 'parent_id' => $parent->id]);
        $article->updateSeoMeta(['title' => '{name} in {parent}']);

        $this->assertSame('Chair in {parent}', $article->fresh()?->getSeoTitle());
    }

    public function test_the_name_of_a_related_record_is_stripped_of_tags(): void
    {
        $parent = Handbook::query()->create(['name' => '<b>Furniture</b>']);
        $article = Handbook::query()->create(['name' => 'Chair', 'parent_id' => $parent->id]);
        $article->updateSeoMeta(['title' => '{parent}']);

        $this->assertSame('Furniture', $article->fresh()?->getSeoTitle());
    }

    public function test_a_list_or_a_missing_value_leaves_its_placeholder_and_numbers_and_dates_are_written_out(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 4)->setTime(10, 20, 30));
        $article = Handbook::query()->create(['name' => 'Chair']);
        $article->updateSeoMeta(['title' => '{files}|{id}|{created_at}|{description}']);

        $this->assertSame("{files}|{$article->id}|2026-03-04 10:20:30|{description}", $article->fresh()?->getSeoTitle());
    }

    public function test_the_description_from_the_seo_tab_wins(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'preview_text' => 'Preview']);
        $article->updateSeoMeta(['description' => 'All about {name}']);

        $this->assertSame('All about Chair', $article->fresh()?->getSeoDescription());
    }

    public function test_without_a_description_the_template_then_the_texts_are_used(): void
    {
        $article = new Handbook(['name' => 'Chair', 'preview_text' => '<p>Soft &amp; warm</p>', 'description' => 'Section text']);
        $this->assertSame('Soft & warm', $article->getSeoDescription());

        $section = new Handbook(['name' => 'Chairs', 'description' => '<p>Every chair we have</p>']);
        $this->assertSame('Every chair we have', $section->getSeoDescription());

        $this->assertSame('', (new Handbook(['name' => 'Chair', 'preview_text' => '', 'description' => '']))->getSeoDescription());
        // An empty preview text gives way to the section text.
        $this->assertSame('Section text', (new Handbook(['name' => 'Chair', 'preview_text' => '', 'description' => 'Section text']))->getSeoDescription());

        config()->set('atrium.seo.templates.'.Handbook::class.'.description', 'Buy {name}');
        $this->assertSame('Buy Chair', $article->getSeoDescription());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function texts(): array
    {
        $sentence = str_repeat('word ', 33).'end.'; // 169 characters
        $long = str_repeat('word ', 80); // 400 characters, no sentence end

        return [
            'short text stays whole' => ['Soft chair', 'Soft chair'],
            'tags and entities go' => ['<p>Soft&nbsp;&amp;&nbsp;warm</p>', 'Soft & warm'],
            'whitespace and no-break spaces collapse' => ["Soft \n\t\u{00A0} chair", 'Soft chair'],
            'only tags is empty' => ['<p> </p>', ''],
            'exactly 300 characters stay' => [str_repeat('a', 300), str_repeat('a', 300)],
            'cut at the end of a long sentence' => [$sentence.' '.$long, $sentence],
            'Cyrillic sentence' => [str_repeat('слово ', 30).'конец! '.$long, str_repeat('слово ', 30).'конец!'],
            'a short sentence is not enough' => ['Short. '.$long, 'Short. '.rtrim(str_repeat('word ', 58)).'…'],
            'no sentence end: cut at a word' => [$long, rtrim(mb_substr($long, 0, 299)).'…'],
            'quotes and HTML5 entities are decoded' => ['<p>&quot;Soft&quot; &apos;warm&apos; It&#039;s</p>', '"Soft" \'warm\' It\'s'],
            'a sentence of exactly half the limit is enough' => [str_repeat('a', 149).'. '.$long, str_repeat('a', 149).'.'],
            'a sentence one short of half the limit is not' => [str_repeat('a', 148).'. '.$long, str_repeat('a', 148).'. '.rtrim(str_repeat('word ', 30)).'…'],
            'half the limit counts letters, not bytes' => [str_repeat('я', 99).'. '.$long, str_repeat('я', 99).'. '.rtrim(str_repeat('word ', 39)).'…'],
            'Cyrillic text under the limit stays whole, however many bytes' => [str_repeat('я', 160).'. '.str_repeat('я', 50), str_repeat('я', 160).'. '.str_repeat('я', 50)],
            'a tag cut in half does not eat the text' => [str_repeat('<b>word</b> ', 70), rtrim(mb_substr(str_repeat('word ', 70), 0, 299)).'…'],
        ];
    }

    #[DataProvider('texts')]
    public function test_a_long_preview_text_becomes_a_short_description(string $text, string $description): void
    {
        $this->assertSame($description, (new Handbook(['preview_text' => $text]))->getSeoDescription());
    }

    public function test_the_keywords_come_from_the_seo_tab(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair']);
        $this->assertNull($article->getSeoKeywords());

        $article->updateSeoMeta(['keywords' => 'chair, seat']);
        $this->assertSame('chair, seat', $article->fresh()?->getSeoKeywords());
    }

    public function test_the_share_picture_falls_back_to_the_preview_then_the_detail_picture(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair', 'preview_picture' => 'articles/preview.jpg', 'detail_picture' => '/upload/detail.jpg']);
        $this->assertSame(asset('storage/articles/preview.jpg'), $article->getOgImage());

        $article->update(['preview_picture' => null]);
        $this->assertSame(asset('/upload/detail.jpg'), $article->fresh()?->getOgImage());

        $article->updateSeoMeta(['og_image' => 'https://cdn.example.com/share.jpg']);
        $this->assertSame('https://cdn.example.com/share.jpg', $article->fresh()?->getOgImage());

        $this->assertNull((new Handbook)->getOgImage());
    }

    public function test_seo_meta_is_written_once_per_record_and_goes_with_it(): void
    {
        $article = Handbook::query()->create(['name' => 'Chair']);
        $other = Handbook::query()->create(['name' => 'Table']);
        $other->updateSeoMeta(['title' => 'Table']);

        $article->updateSeoMeta(['title' => 'First']);
        $saved = $article->updateSeoMeta(['title' => 'Second']);

        $this->assertSame('Second', $saved->title);
        $this->assertSame(2, SeoMeta::query()->count());
        $this->assertSame('Second', SeoMeta::query()->where('seoable_id', $article->id)->sole()->title);

        $article->delete();

        $this->assertSame(['Table'], SeoMeta::query()->pluck('title')->all());
    }

    public function test_the_field_map_takes_only_the_seo_fields(): void
    {
        $this->assertSame([
            'seo_title' => 'title',
            'seo_keywords' => 'keywords',
            'seo_description' => 'description',
            'seo_page_title' => 'page_title',
            'seo_preview_picture_alt' => 'preview_picture_alt',
            'seo_preview_picture_title' => 'preview_picture_title',
            'seo_preview_picture_file_name' => 'preview_picture_file_name',
            'seo_detail_picture_alt' => 'detail_picture_alt',
            'seo_detail_picture_title' => 'detail_picture_title',
            'seo_detail_picture_file_name' => 'detail_picture_file_name',
            'seo_tags' => 'tags',
            'seo_og_image' => 'og_image',
        ], Handbook::seoFieldMap());

        $this->assertSame(['seo_title' => 'title'], ArticleWithExtraSeoField::seoFieldMap());
    }

    public function test_the_seo_fields_carry_their_limits(): void
    {
        $fields = [];

        foreach (Handbook::getSeoAdminFields() as $field) {
            $fields[$field->getName()] = $field->toArray();
        }

        $this->assertSame(255, $fields['seo_title']['maxLength']);
        $this->assertSame(255, $fields['seo_page_title']['maxLength']);
        $this->assertSame(500, $fields['seo_tags']['maxLength']);
        $this->assertSame(3, $fields['seo_keywords']['rows']);
        $this->assertSame(3, $fields['seo_description']['rows']);
        foreach (['preview', 'detail'] as $picture) {
            foreach (['alt', 'title', 'file_name'] as $part) {
                $this->assertSame(2, $fields["seo_{$picture}_picture_{$part}"]['rows'], "seo_{$picture}_picture_{$part}");
            }
        }
        $this->assertSame('image', $fields['seo_og_image']['type']);
    }
}

final class ArticleWithExtraSeoField extends Handbook
{
    public static function getSeoAdminFields(): array
    {
        return [
            TextField::make('canonical')->label('Canonical'),
            TextField::make('seo_title')->label('Title'),
        ];
    }
}
