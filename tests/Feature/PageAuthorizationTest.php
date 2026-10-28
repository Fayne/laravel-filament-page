<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageResource\Pages\CreatePage;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageWidget;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates pages through Filament only with active administrator permissions', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Livewire::test(CreatePage::class)->assertForbidden();
    foreach (['createPage', 'viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    Livewire::test(CreatePage::class)->fillForm(['name' => '首页', 'path' => '/', 'type' => 'basic', 'locale' => 'en', 'components' => [], 'fixed_components' => []])
        ->call('create')->assertHasNoFormErrors();
    expect(Page::where('path', '/')->count())->toBe(1);
    $admin->is_active = false;
    Livewire::test(EditPage::class, ['record' => Page::first()->getKey()])->assertForbidden();
});

it('performs stash and restore through authorized Filament actions', function () {
    $page = Page::create(['name' => 'Published', 'path' => '/', 'components' => [], 'fixed_components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'is_stash' => false, 'released_at' => now()]);
    PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $draft = [['layout' => 'hero', 'key' => 'draft-key', 'attributes' => []]];
    $page->update(['name' => 'Draft', 'components' => $draft]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'actions@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    Livewire::test(EditPage::class, ['record' => $page->id])->callAction('stash')->assertHasNoErrors();
    expect($page->fresh()->name)->toBe('Published');
    $editor = Livewire::test(EditPage::class, ['record' => $page->id])->callAction('restoreStash')->assertHasNoErrors();
    expect(collect($editor->get('data.components'))->first()['type'] ?? null)->toBe('hero');
    $editor->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->components)->toBe($draft);
    expect($page->fresh()->name)->toBe('Draft')->and($page->fresh()->has_stash)->toBeFalse();
});

it('adds a component through the editor and preserves its persistent key on reopen', function () {
    $page = Page::create(['name' => 'Home', 'path' => '/', 'components' => []]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'import@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $editor = Livewire::test(EditPage::class, ['record' => $page->id])
        ->call('quickAddComponent', 'hero')
        ->assertHasNoErrors();
    expect(collect($editor->get('data.components'))->first()['type'] ?? null)->toBe('hero');
    $editor->call('save')->assertHasNoFormErrors();
    $saved = $page->fresh()->components;
    expect($saved[0]['layout'])->toBe('hero')->and($saved[0]['key'])->toBeString()->not->toBe('');
    Livewire::test(EditPage::class, ['record' => $page->id])->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->components)->toBe($saved);
});

it('does not mark unchanged raw content as changed after saving the edit form', function () {
    $page = Page::create(['name' => 'Published', 'path' => '/', 'components' => [], 'fixed_components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'unchanged@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(EditPage::class, ['record' => $page->id])->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->changed)->toBeFalse();
});

it('shows duplicate path errors on the page form field', function () {
    Page::create(['name' => 'Existing', 'path' => '/same', 'locale' => 'en']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'duplicate@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'createPage'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreatePage::class)->fillForm(['name' => 'Duplicate', 'path' => '/same'])->call('create')->assertHasFormErrors(['path']);
    expect(Page::count())->toBe(1);
});

it('edits database-defined entity and cascade fields without changing legacy references', function () {
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $widget = PageWidget::create(['name' => 'Referenced entity', 'slug' => 'entity']);
    config(['filament-page-builder.entities' => ['widget' => ['model' => PageWidget::class, 'label' => 'name', 'fields' => ['id', 'name']]]]);
    $component->fields()->create(['title' => 'Entity', 'slug' => 'entity', 'resolver' => 'entity', 'options' => ['reference' => json_encode([PageWidget::class])]]);
    config(['filament-page-builder.entities' => [PageWidget::class => ['model' => PageWidget::class, 'label' => 'name', 'fields' => ['id', 'name']]]]);
    $component->fields()->create(['title' => 'Location', 'slug' => 'location', 'resolver' => 'cascade', 'options' => ['options' => [['value' => 'province', 'label' => 'Province', 'children' => [['value' => 'city', 'label' => 'City']]]]]]);
    $raw = [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['entity' => json_encode(['model' => PageWidget::class, 'id' => $widget->id]), 'location' => '["province","city"]']]];
    $page = Page::create(['name' => 'Legacy', 'path' => '/legacy', 'components' => $raw]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'complex@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage', 'viewAnyPageWidget', 'viewPageWidget'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(EditPage::class, ['record' => $page->id])->assertSee('Province')->assertSee('City')->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->components)->toBe($raw);
});

it('requires separate permissions for sensitive reads and HTTP execution', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'special-fields@example.test', 'password' => 'password', 'is_active' => true]);
    $page = Page::create(['name' => 'Private', 'path' => '/private']);
    foreach (['viewPage', 'updatePage', 'viewSensitiveContentPage', 'executeContentHttpPage'] as $permission) {
        test()->permission($permission);
    }
    test()->grantPermissions($admin, ['viewPage', 'updatePage']);
    expect(Gate::forUser($admin)->allows('viewSensitiveContent', $page))->toBeFalse();
    test()->grantPermissions($admin, ['viewSensitiveContentPage', 'executeContentHttpPage']);
    expect(Gate::forUser($admin)->allows('viewSensitiveContent', $page))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('executeContentHttp', $page))->toBeTrue();
});

it('does not offer raw component downloads or reveal legacy sensitive values', function () {
    $component = PageComponent::create(['name' => 'Private', 'slug' => 'private']);
    $component->fields()->create(['title' => 'Secret', 'slug' => 'secret', 'resolver' => 'sensitive']);
    $page = Page::create(['name' => 'Private', 'path' => '/download', 'components' => [['layout' => 'private', 'key' => 'k', 'attributes' => ['secret' => 'private-fixture']]]]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'download-secret@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $editor = Livewire::test(EditPage::class, ['record' => $page->id])->assertActionDoesNotExist('exportComponents')->assertDontSee('private-fixture');
    expect(json_encode($editor->get('data')))->not->toContain('private-fixture');
});

it('saves a page with the supported complex fields', function () {
    $component = PageComponent::create(['name' => 'Complex', 'slug' => 'complex']);
    foreach (['body' => 'rich_text', 'table' => 'table', 'hours' => 'opening_hours', 'request' => 'http'] as $slug => $resolver) {
        $component->fields()->create(['title' => $slug, 'slug' => $slug, 'resolver' => $resolver]);
    }
    $raw = [['layout' => 'complex', 'key' => 'complex', 'attributes' => ['body' => '<p><strong>Old</strong></p>', 'table' => [['A', 'B']], 'hours' => ['monday' => ['09:00-12:00']], 'request' => ['url' => 'https://unconfigured.example.test/data', 'method' => 'GET']]]];
    $page = Page::create(['name' => 'Complex', 'path' => '/complex', 'components' => $raw]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'all-fields@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $form = Livewire::test(EditPage::class, ['record' => $page->id]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.body', '<p>Changed</p>')->call('save')->assertHasNoFormErrors();
    $raw[0]['attributes']['body'] = '<p>Changed</p>';
    expect($page->fresh()->components)->toEqual($raw);
});

it('rejects adding a missing component definition without changing stored content', function () {
    $page = Page::create(['name' => 'Home', 'path' => '/malformed', 'components' => []]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'malformed@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    Livewire::test(EditPage::class, ['record' => $page->id])->call('quickAddComponent', 'missing-component')->assertHasErrors(['data.components']);
    expect($page->fresh()->components)->toBe([]);
});

it('uploads reopens and removes a rich image without deleting shared storage', function () {
    Storage::fake('public');
    $component = PageComponent::create(['name' => 'Article', 'slug' => 'article']);
    $component->fields()->create(['title' => 'Body', 'slug' => 'body', 'resolver' => 'rich_text']);
    $page = Page::create(['name' => 'Article', 'path' => '/article-image', 'components' => [['layout' => 'article', 'key' => 'article-key', 'attributes' => ['body' => '<p>Article</p>']]]]);
    $admin = Admin::create(['name' => 'Uploader', 'email' => 'inline-image@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage', 'uploadMedia'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $form = Livewire::test(EditPage::class, ['record' => $page->id]);
    $key = array_key_first($form->get('data.components'));
    $path = 'data.components.'.$key.'.data.attributes.body';
    $form->set('componentFileAttachments.'.$path.'.upload', UploadedFile::fake()->image('inline.png'))
        ->set($path, ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['id' => 'upload', 'src' => '/temporary', 'alt' => 'Inline image']]]])->call('save')->assertHasNoFormErrors();
    $html = $page->fresh()->components[0]['attributes']['body'];
    expect($html)->toContain('rich-content/', 'Inline image');
    $files = Storage::disk('public')->allFiles('rich-content');
    expect($files)->toHaveCount(1);
    Livewire::test(EditPage::class, ['record' => $page->id])->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->components[0]['attributes']['body'])->toBe($html);
    $form = Livewire::test(EditPage::class, ['record' => $page->id]);
    $key = array_key_first($form->get('data.components'));
    $form->callAction(TestAction::make('clone')->schemaComponent('components')->arguments(['item' => $key]))->call('save')->assertHasNoFormErrors();
    expect($page->fresh()->components)->toHaveCount(2);
    $form->set('data.components.'.$key.'.data.attributes.body', '<p>Image removed</p>')->call('save')->assertHasNoFormErrors();
    expect(Storage::disk('public')->exists($files[0]))->toBeTrue();
});
