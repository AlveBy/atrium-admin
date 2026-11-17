<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Console\Commands\SlugRegenerateCommand;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\Api\DocsController;
use Atrium\Admin\Http\Controllers\CaptchaController;
use Atrium\Admin\Http\Controllers\ModuleController;
use Atrium\Admin\Http\Controllers\SeoFilesController;
use Atrium\Admin\Http\Controllers\ThemeController;
use Atrium\Admin\Http\Requests\Auth\LoginRequest;
use Atrium\Admin\Mail\TwoFactorCodeMail;
use Atrium\Admin\Mcp\Tools\ApiTool;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\File;
use Atrium\Admin\Models\MediaCollection;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Services\SlugService;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Support\Themes;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\HasAdminFiles;
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\HasAdminSlug;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\HasSoftRelations;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Who may call what. A module extends the controllers, traits and models of
 * the panel and calls their public methods from outside; the framework calls
 * boot methods, relations and accessors from inside, where a narrower
 * visibility would go unnoticed. Listed by name on purpose: a method that
 * went private would otherwise vanish from a discovered list as well.
 */
final class ExtensionSurfaceTest extends TestCase
{
    /**
     * @return array<string,array{class-string,string,string}>
     */
    public static function methods(): array
    {
        return [
            'Auth\Permissions::canSetUpAnything' => [Permissions::class, 'canSetUpAnything', 'public'],
            'Console\Commands\SlugRegenerateCommand::cleanupHistory' => [SlugRegenerateCommand::class, 'cleanupHistory', 'protected'],
            'Console\Commands\SlugRegenerateCommand::findModelsWithSlug' => [SlugRegenerateCommand::class, 'findModelsWithSlug', 'protected'],
            'Console\Commands\SlugRegenerateCommand::regenerateAll' => [SlugRegenerateCommand::class, 'regenerateAll', 'protected'],
            'Console\Commands\SlugRegenerateCommand::regenerateModel' => [SlugRegenerateCommand::class, 'regenerateModel', 'protected'],
            'Fields\Field::getFieldProps' => [Field::class, 'getFieldProps', 'protected'],
            'Http\Controllers\AdminController::abilities' => [AdminController::class, 'abilities', 'protected'],
            'Http\Controllers\AdminController::allAdminFields' => [AdminController::class, 'allAdminFields', 'protected'],
            'Http\Controllers\AdminController::contextFields' => [AdminController::class, 'contextFields', 'protected'],
            'Http\Controllers\AdminController::create' => [AdminController::class, 'create', 'public'],
            'Http\Controllers\AdminController::createActions' => [AdminController::class, 'createActions', 'protected'],
            'Http\Controllers\AdminController::edit' => [AdminController::class, 'edit', 'public'],
            'Http\Controllers\AdminController::extractRelationValues' => [AdminController::class, 'extractRelationValues', 'protected'],
            'Http\Controllers\AdminController::extractSeoValues' => [AdminController::class, 'extractSeoValues', 'protected'],
            'Http\Controllers\AdminController::getPagePrefix' => [AdminController::class, 'getPagePrefix', 'protected'],
            'Http\Controllers\AdminController::ignoreCurrentRecord' => [AdminController::class, 'ignoreCurrentRecord', 'protected'],
            'Http\Controllers\AdminController::isPerSite' => [AdminController::class, 'isPerSite', 'protected'],
            'Http\Controllers\AdminController::listRow' => [AdminController::class, 'listRow', 'protected'],
            'Http\Controllers\AdminController::moduleAllows' => [AdminController::class, 'moduleAllows', 'protected'],
            'Http\Controllers\AdminController::normalizeTranslatableInput' => [AdminController::class, 'normalizeTranslatableInput', 'protected'],
            'Http\Controllers\AdminController::persistSeoValues' => [AdminController::class, 'persistSeoValues', 'protected'],
            'Http\Controllers\AdminController::prefillFromActions' => [AdminController::class, 'prefillFromActions', 'protected'],
            'Http\Controllers\AdminController::prepareFormData' => [AdminController::class, 'prepareFormData', 'protected'],
            'Http\Controllers\AdminController::preselectSection' => [AdminController::class, 'preselectSection', 'protected'],
            'Http\Controllers\AdminController::relatedColumns' => [AdminController::class, 'relatedColumns', 'protected'],
            'Http\Controllers\AdminController::resourceProps' => [AdminController::class, 'resourceProps', 'protected'],
            'Http\Controllers\AdminController::runValidation' => [AdminController::class, 'runValidation', 'protected'],
            'Http\Controllers\AdminController::sectionExists' => [AdminController::class, 'sectionExists', 'protected'],
            'Http\Controllers\AdminController::seoFieldMap' => [AdminController::class, 'seoFieldMap', 'protected'],
            'Http\Controllers\AdminController::serializeColumns' => [AdminController::class, 'serializeColumns', 'protected'],
            'Http\Controllers\AdminController::serializeFields' => [AdminController::class, 'serializeFields', 'protected'],
            'Http\Controllers\AdminController::serializeFieldsByTab' => [AdminController::class, 'serializeFieldsByTab', 'protected'],
            'Http\Controllers\AdminController::store' => [AdminController::class, 'store', 'public'],
            'Http\Controllers\AdminController::submittedData' => [AdminController::class, 'submittedData', 'protected'],
            'Http\Controllers\AdminController::syncRelations' => [AdminController::class, 'syncRelations', 'protected'],
            'Http\Controllers\AdminController::translatableAttributes' => [AdminController::class, 'translatableAttributes', 'protected'],
            'Http\Controllers\AdminController::translatableRules' => [AdminController::class, 'translatableRules', 'protected'],
            'Http\Controllers\AdminController::uniqueRule' => [AdminController::class, 'uniqueRule', 'protected'],
            'Http\Controllers\AdminController::update' => [AdminController::class, 'update', 'public'],
            'Http\Controllers\AdminController::validationAttributes' => [AdminController::class, 'validationAttributes', 'protected'],
            'Http\Controllers\Api\DocsController::markdown' => [DocsController::class, 'markdown', 'public'],
            'Http\Controllers\Api\DocsController::openApi' => [DocsController::class, 'openApi', 'public'],
            'Http\Controllers\CaptchaController::image' => [CaptchaController::class, 'image', 'public'],
            'Http\Controllers\ModuleController::disable' => [ModuleController::class, 'disable', 'public'],
            'Http\Controllers\ModuleController::enable' => [ModuleController::class, 'enable', 'public'],
            'Http\Controllers\ModuleController::settings' => [ModuleController::class, 'settings', 'public'],
            'Http\Controllers\ModuleController::update' => [ModuleController::class, 'update', 'public'],
            'Http\Controllers\SeoFilesController::robots' => [SeoFilesController::class, 'robots', 'public'],
            'Http\Controllers\SeoFilesController::sitemap' => [SeoFilesController::class, 'sitemap', 'public'],
            'Http\Controllers\SeoFilesController::sitemapPart' => [SeoFilesController::class, 'sitemapPart', 'public'],
            'Http\Controllers\ThemeController::update' => [ThemeController::class, 'update', 'public'],
            'Http\Requests\Auth\LoginRequest::ensureIsNotRateLimited' => [LoginRequest::class, 'ensureIsNotRateLimited', 'public'],
            'Http\Requests\Auth\LoginRequest::throttleKey' => [LoginRequest::class, 'throttleKey', 'public'],
            'Mail\TwoFactorCodeMail::content' => [TwoFactorCodeMail::class, 'content', 'public'],
            'Mail\TwoFactorCodeMail::envelope' => [TwoFactorCodeMail::class, 'envelope', 'public'],
            'Mcp\Tools\ApiTool::owner' => [ApiTool::class, 'owner', 'protected'],
            'Models\AdminUser::actingSuperadmin' => [AdminUser::class, 'actingSuperadmin', 'protected'],
            'Models\File::fileable' => [File::class, 'fileable', 'public'],
            'Models\File::getFullPathAttribute' => [File::class, 'getFullPathAttribute', 'public'],
            'Models\File::getHumanSizeAttribute' => [File::class, 'getHumanSizeAttribute', 'public'],
            'Models\File::getUrlAttribute' => [File::class, 'getUrlAttribute', 'public'],
            'Models\MediaCollection::children' => [MediaCollection::class, 'children', 'public'],
            'Models\MediaItem::collection' => [MediaItem::class, 'collection', 'public'],
            'Models\MediaItem::scopeDocuments' => [MediaItem::class, 'scopeDocuments', 'public'],
            'Models\MediaItem::scopeImages' => [MediaItem::class, 'scopeImages', 'public'],
            'Models\SeoMeta::getOgDescriptionAttribute' => [SeoMeta::class, 'getOgDescriptionAttribute', 'public'],
            'Models\SeoMeta::getOgTitleAttribute' => [SeoMeta::class, 'getOgTitleAttribute', 'public'],
            'Models\SeoMeta::seoable' => [SeoMeta::class, 'seoable', 'public'],
            'Models\Site::getAdminTabs' => [Site::class, 'getAdminTabs', 'public'],
            'Models\Site::normalizeFolder' => [Site::class, 'normalizeFolder', 'public'],
            'Models\Slug::findByPath' => [Slug::class, 'findByPath', 'public'],
            'Models\Slug::findBySlug' => [Slug::class, 'findBySlug', 'public'],
            'Models\Slug::resolve' => [Slug::class, 'resolve', 'public'],
            'Models\Slug::resolvePath' => [Slug::class, 'resolvePath', 'public'],
            'Models\Slug::sluggable' => [Slug::class, 'sluggable', 'public'],
            'Models\SlugHistory::cleanup' => [SlugHistory::class, 'cleanup', 'public'],
            'Models\SlugHistory::findRedirect' => [SlugHistory::class, 'findRedirect', 'public'],
            'Models\SlugHistory::findRedirectPath' => [SlugHistory::class, 'findRedirectPath', 'public'],
            'Models\SlugHistory::sluggable' => [SlugHistory::class, 'sluggable', 'public'],
            'Services\SlugService::cachePathSlug' => [SlugService::class, 'cachePathSlug', 'protected'],
            'Services\SlugService::cacheSlug' => [SlugService::class, 'cacheSlug', 'protected'],
            'Services\SlugService::getCacheKey' => [SlugService::class, 'getCacheKey', 'protected'],
            'Support\Locales::groups' => [Locales::class, 'groups', 'public'],
            'Support\Locales::isAvailable' => [Locales::class, 'isAvailable', 'public'],
            'Support\Sites::forHost' => [Sites::class, 'forHost', 'public'],
            'Support\Themes::isChoice' => [Themes::class, 'isChoice', 'public'],
            'Traits\BelongsToSite::bootBelongsToSite' => [BelongsToSite::class, 'bootBelongsToSite', 'public'],
            'Traits\BelongsToSite::site' => [BelongsToSite::class, 'site', 'public'],
            'Traits\HasAdminFiles::bootHasAdminFiles' => [HasAdminFiles::class, 'bootHasAdminFiles', 'public'],
            'Traits\HasAdminSeo::bootHasAdminSeo' => [HasAdminSeo::class, 'bootHasAdminSeo', 'public'],
            'Traits\HasAdminSeo::getDefaultSeoDescription' => [HasAdminSeo::class, 'getDefaultSeoDescription', 'protected'],
            'Traits\HasAdminSeo::getDefaultSeoTitle' => [HasAdminSeo::class, 'getDefaultSeoTitle', 'protected'],
            'Traits\HasAdminSeo::getSeoAdminFields' => [HasAdminSeo::class, 'getSeoAdminFields', 'public'],
            'Traits\HasAdminSeo::getSeoDescriptionTemplate' => [HasAdminSeo::class, 'getSeoDescriptionTemplate', 'protected'],
            'Traits\HasAdminSeo::getSeoTitleTemplate' => [HasAdminSeo::class, 'getSeoTitleTemplate', 'protected'],
            'Traits\HasAdminSeo::processSeoTemplate' => [HasAdminSeo::class, 'processSeoTemplate', 'protected'],
            'Traits\HasAdminSeo::seoFieldMap' => [HasAdminSeo::class, 'seoFieldMap', 'public'],
            'Traits\HasAdminSlug::bootHasAdminSlug' => [HasAdminSlug::class, 'bootHasAdminSlug', 'public'],
            'Traits\HasAdminSlug::cacheSlug' => [HasAdminSlug::class, 'cacheSlug', 'protected'],
            'Traits\HasAdminSlug::clearSlugCache' => [HasAdminSlug::class, 'clearSlugCache', 'protected'],
            'Traits\HasAdminSlug::createSlugRecord' => [HasAdminSlug::class, 'createSlugRecord', 'protected'],
            'Traits\HasAdminSlug::ensureUniqueSlug' => [HasAdminSlug::class, 'ensureUniqueSlug', 'protected'],
            'Traits\HasAdminSlug::findByFullPath' => [HasAdminSlug::class, 'findByFullPath', 'public'],
            'Traits\HasAdminSlug::findBySlug' => [HasAdminSlug::class, 'findBySlug', 'public'],
            'Traits\HasAdminSlug::findRedirect' => [HasAdminSlug::class, 'findRedirect', 'public'],
            'Traits\HasAdminSlug::getSlugCacheKey' => [HasAdminSlug::class, 'getSlugCacheKey', 'protected'],
            'Traits\HasAdminSlug::saveSlugToHistory' => [HasAdminSlug::class, 'saveSlugToHistory', 'protected'],
            'Traits\HasAdminSlug::transliterate' => [HasAdminSlug::class, 'transliterate', 'protected'],
            'Traits\HasAdminSlug::updateSlugRecord' => [HasAdminSlug::class, 'updateSlugRecord', 'protected'],
            'Traits\HasAdminTabs::getAdminFieldsWithTabs' => [HasAdminTabs::class, 'getAdminFieldsWithTabs', 'public'],
            'Traits\HasAdminTabs::getAdminTabs' => [HasAdminTabs::class, 'getAdminTabs', 'public'],
            'Traits\HasAdminTabs::getFieldsGroupedByTabs' => [HasAdminTabs::class, 'getFieldsGroupedByTabs', 'public'],
            'Traits\HasAdminTabs::usesTabs' => [HasAdminTabs::class, 'usesTabs', 'public'],
            'Traits\HasSoftRelations::softBelongsTo' => [HasSoftRelations::class, 'softBelongsTo', 'protected'],
            'Traits\HasSoftRelations::softBelongsToMany' => [HasSoftRelations::class, 'softBelongsToMany', 'protected'],
            'Traits\HasSoftRelations::softHasMany' => [HasSoftRelations::class, 'softHasMany', 'protected'],
            'Traits\HasSoftRelations::softMorphTo' => [HasSoftRelations::class, 'softMorphTo', 'protected'],
            'Traits\InteractsWithAdmin::getAdministrationActions' => [InteractsWithAdmin::class, 'getAdministrationActions', 'public'],
            'Traits\InteractsWithAdmin::getAdministrationFilters' => [InteractsWithAdmin::class, 'getAdministrationFilters', 'public'],
            'Traits\InteractsWithAdmin::isCreatableInAdmin' => [InteractsWithAdmin::class, 'isCreatableInAdmin', 'public'],
            'Traits\InteractsWithAdmin::isEditableInAdmin' => [InteractsWithAdmin::class, 'isEditableInAdmin', 'public'],
            'Traits\Translatable::getFallbackLocale' => [Translatable::class, 'getFallbackLocale', 'public'],
        ];
    }

    #[DataProvider('methods')]
    public function test_a_method_is_as_visible_as_the_extension_api_says(string $class, string $method, string $visibility): void
    {
        $reflection = new ReflectionMethod($class, $method);

        $this->assertSame($visibility === 'public', $reflection->isPublic());
        $this->assertSame($visibility === 'protected', $reflection->isProtected());
    }
}
