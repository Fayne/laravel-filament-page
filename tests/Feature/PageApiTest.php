<?php

use Fayne\Page\Models\Page;

it('uses the configured content language instead of the interface language when no locale is requested', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.default_locale' => 'en']);
    app()->setLocale('zh_CN');
    Page::create(['name' => 'English page', 'path' => '/default-language', 'locale' => 'en', 'is_active' => true, 'components' => []]);

    $this->getJson('/modules/pages/detail/default-language')->assertOk()->assertJsonPath('name', 'English page');
    $this->getJson('/modules/pages/slugs')->assertOk()->assertExactJson([['name' => 'English page', 'path' => '/default-language']]);
});

it('exposes only active pages in the requested locale and keeps the public route shape', function () {
    config(['filament-cms.multilingual_enabled' => true]);
    Page::create(['name' => 'English', 'path' => '/', 'locale' => 'en', 'is_active' => true, 'components' => []]);
    Page::create(['name' => '中文', 'path' => '/', 'locale' => 'zh-CN', 'is_active' => true, 'components' => []]);
    Page::create(['name' => 'Draft', 'path' => '/draft', 'locale' => 'en', 'is_active' => false]);
    $this->getJson('/modules/pages/detail?locale=en')->assertOk()->assertJsonPath('name', 'English');
    $this->getJson('/modules/pages/detail?locale=zh-CN')->assertOk()->assertJsonPath('name', '中文');
    $this->getJson('/modules/pages/detail/draft?locale=en')->assertNotFound();
    $this->getJson('/modules/pages/slugs?locale=en')->assertOk()->assertExactJson([['name' => 'English', 'path' => '/']]);
});
