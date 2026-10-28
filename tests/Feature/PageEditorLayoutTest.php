<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageResource\Pages\CreatePage;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageWidget;
use Filament\Schemas\Components\Tabs;
use Livewire\Livewire;

function groupedPageEditor(): mixed
{
    $admin = Admin::create(['name' => 'Layout editor', 'email' => 'layout@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $widget = PageWidget::create(['name' => 'Card', 'slug' => 'card']);
    $widget->fields()->create(['title' => 'Text', 'slug' => 'text', 'resolver' => 'text']);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $component->fields()->create(['title' => 'Title', 'slug' => 'title', 'resolver' => 'text', 'rules' => ['rules' => ['required']]]);
    $component->fields()->create(['title' => 'Cards', 'slug' => 'cards', 'resolver' => 'widget', 'options' => ['widgets' => [$widget->id]]]);
    $page = Page::create(['name' => 'Grouped', 'path' => '/grouped', 'type' => 'basic', 'locale' => 'en', 'title' => 'SEO title', 'description' => 'Description', 'keywords' => ['cms'], 'body_classes' => 'page', 'main_classes' => 'main', 'components' => [['layout' => 'hero', 'key' => 'root', 'internalTitle' => 'Welcome', 'attributes' => ['title' => 'Hello', 'cards' => [['layout' => 'card', 'key' => 'nested', 'internalTitle' => 'First card', 'attributes' => ['text' => 'Card text']]]]]], 'fixed_components' => []]);

    return Livewire::test(EditPage::class, ['record' => $page->id]);
}

it('groups the localized editor into four tabs without changing field state paths', function (string $locale, array $labels, string $components, string $fixed) {
    app()->setLocale($locale);
    $editor = groupedPageEditor();
    $tabs = collect($editor->instance()->form->getComponents())->first(fn ($component) => $component instanceof Tabs);
    expect($tabs)->not->toBeNull();
    expect(collect($tabs->getChildComponents())->map(fn ($tab) => $tab->getLabel())->all())->toBe($labels);
    $editor->assertSee('role="tablist"', false)->assertSee($components)->assertSee($fixed)->assertSee('Welcome [Hero]')->assertSee('First card [Card]');
})->with([
    ['zh_CN', ['基础信息', '页面内容', 'SEO 设置', '高级设置'], '普通组件', '固定组件'],
    ['en', ['Basic information', 'Page content', 'SEO settings', 'Advanced settings'], 'Components', 'Fixed components'],
]);

it('preserves SEO and nested component data when saving the grouped editor', function () {
    $editor = groupedPageEditor();
    $page = $editor->instance()->getRecord();
    $original = $page->only(['components', 'fixed_components', 'title', 'description', 'keywords', 'meta', 'body_classes', 'main_classes']);
    $editor->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->only(array_keys($original)))->toBe($original);
});

it('validates fields inside inactive tabs and blocks invalid saves', function () {
    $editor = groupedPageEditor();
    $key = array_key_first($editor->get('data.components'));
    $editor->set('data.components.'.$key.'.data.attributes.title', '')->fillForm(['title' => str_repeat('x', 256)])->call('save')
        ->assertHasFormErrors(['title' => 'max', 'components.'.$key.'.data.attributes.title' => 'required']);
    expect($editor->instance()->getRecord()->fresh()->components[0]['attributes']['title'])->toBe('Hello');
});

it('edits custom meta tags and keeps them through reopen snapshot and public output', function () {
    $editor = groupedPageEditor();
    $meta = ['og:title' => 'Shared title', 'og:image' => 'https://example.test/share.jpg', 'twitter:card' => 'summary_large_image', 'custom-tag' => 'Custom value'];
    $editor->assertFormFieldExists('meta')->fillForm(['meta' => $meta])->call('save')->assertHasNoFormErrors();
    $page = $editor->instance()->getRecord()->fresh();
    expect($page->meta)->toBe($meta)->and($page->contentSnapshot()['meta'])->toBe($meta)->and($page->publicProjection()['meta'])->toBe($meta);
    Livewire::test(EditPage::class, ['record' => $page->id])->assertFormSet(['meta' => $meta])->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->meta)->toBe($meta);
});

it('builds a page outline using shared unsaved form state and preserves existing nested content', function () {
    $editor = groupedPageEditor()->assertSee('快捷搭建');
    expect($editor->html())->not->toContain('@js(');
    $editor->assertSee('x-show="open"', false)->assertDontSee('<template x-if="open">', false);
    $key = array_key_first($editor->get('data.components'));
    $before = $editor->get('data.components');
    $editor->call('quickAddComponent', 'hero', $key)->assertHasNoErrors();
    $items = $editor->get('data.components');
    $editor->assertSee('页面结构 · 2 个组件');
    expect($items)->toHaveCount(2)->and(array_key_last($items))->toBe($key)->and($items[$key])->toBe($before[$key]);
    $newKey = array_key_first($items);
    $editor->set('data.components.'.$newKey.'.data.attributes.title', 'New hero');
    $editor->call('quickMoveComponent', $key, $newKey)->assertHasNoErrors();
    expect(array_keys($editor->get('data.components')))->toBe([$key, $newKey]);
    $editor->call('save')->assertHasNoFormErrors();
    expect($editor->instance()->getRecord()->fresh()->components[0]['key'])->toBe('root');
    $editor->call('quickRemoveComponent', $newKey)->assertHasNoErrors();
    expect($editor->get('data.components'))->toBe($before);
});

it('rejects unknown layouts stale drop targets and excluded page types without changing content', function () {
    $editor = groupedPageEditor();
    $before = $editor->get('data.components');
    $editor->call('quickAddComponent', 'missing')->assertHasErrors();
    expect($editor->get('data.components'))->toBe($before);
    $editor->call('quickAddComponent', 'hero', 'missing')->assertHasErrors();
    expect($editor->get('data.components'))->toBe($before);
    config(['filament-page.excluded_components' => ['basic']]);
    $editor->call('quickAddComponent', 'hero')->assertForbidden();
});

it('reauthorizes quick building after page update permission is revoked', function () {
    $editor = groupedPageEditor();
    auth('admin')->user()->revokePermissionTo('tenant.updatePage');
    $editor->call('quickAddComponent', 'hero')->assertForbidden();
});

it('also supports quick building before a new page is first saved', function () {
    groupedPageEditor();
    test()->grantPermissions(auth('admin')->user(), test()->permission('createPage'));
    $editor = Livewire::test(CreatePage::class)
        ->fillForm(['name' => 'New outline', 'path' => '/new-outline'])->call('quickAddComponent', 'hero')->assertHasNoErrors();
    $key = array_key_first($editor->get('data.components'));
    $editor->set('data.components.'.$key.'.data.attributes.title', 'New content')->call('create')->assertHasNoFormErrors();
    expect(Page::where('path', '/new-outline')->sole()->components[0]['attributes']['title'])->toBe('New content');
});

it('moves the first outline card between its immediate neighbour and the third without altering content', function () {
    $editor = groupedPageEditor()->call('quickAddComponent', 'hero')->call('quickAddComponent', 'hero');
    $before = $editor->get('data.components');
    [$first, $second, $third] = array_keys($before);
    $editor->call('quickMoveComponent', $first, $third)->assertHasNoErrors();
    expect($editor->get('data.components'))->toBe([$second => $before[$second], $first => $before[$first], $third => $before[$third]]);
});
