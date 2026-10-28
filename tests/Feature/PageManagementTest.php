<?php

use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageWidget;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;

it('persists raw content and configurable component definitions', function () {
    $raw = [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['title' => '欢迎']]];
    $page = Page::create(['name' => '首页', 'path' => '/', 'components' => $raw, 'fixed_components' => [], 'is_active' => true]);
    expect($page->fresh()->components)->toBe($raw)->and($page->fresh()->is_active)->toBeTrue();
    $component = PageComponent::create(['name' => '首屏', 'slug' => 'hero']);
    $component->fields()->create(['title' => 'Title', 'slug' => 'title', 'resolver' => 'text', 'options' => [], 'rules' => ['rules' => ['required']]]);
    $definitions = new EloquentContentDefinitions(PageComponent::class, PageWidget::class);
    expect($definitions->fields('hero')[0]['name'])->toBe('title');
});

it('filters published content by locale without changing stored references', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.default_locale' => 'en']);
    Page::create(['name' => 'English', 'path' => '/', 'locale' => 'en', 'is_active' => true]);
    Page::create(['name' => '中文', 'path' => '/', 'locale' => 'zh-CN', 'is_active' => true]);
    Page::create(['name' => 'Draft', 'path' => '/draft', 'locale' => 'en', 'is_active' => false]);
    expect(Page::published()->locale('en')->pluck('name')->all())->toBe(['English']);
});

it('does not expose loaded revision relations in public projections', function () {
    $page = Page::create(['name' => 'Public', 'path' => '/', 'components' => []]);
    $page->setRelation('revisions', collect([['components' => [['key' => 'private-editor-key']]]]));
    expect($page->publicProjection())->not->toHaveKey('revisions');
});
