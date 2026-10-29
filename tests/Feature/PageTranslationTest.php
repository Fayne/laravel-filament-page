<?php

use Fayne\CMS\Models\Admin;
use Fayne\EntityReference\Forms\Components\EntityReferenceSelect;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageWidget;
use Fayne\Page\Services\PageContentValidator;
use Fayne\Page\Services\PageRevisionService;
use Fayne\Page\Services\PageTranslationService;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh-CN' => '中文', 'fr' => 'French']]);
});

function translationPageAdmin(array $abilities = ['viewAnyPage', 'viewPage', 'createPage', 'updatePage']): Admin
{
    $admin = Admin::create(['name' => 'Translator', 'email' => 'translator@example.test', 'password' => 'password', 'is_active' => true]);
    foreach ($abilities as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);

    return $admin;
}

it('creates an independent inactive translation with fresh nested keys and unchanged references', function () {
    translationPageAdmin();
    $widget = PageWidget::create(['name' => 'Card', 'slug' => 'card']);
    $widget->fields()->create(['title' => 'Text', 'slug' => 'text', 'resolver' => 'text']);
    $hero = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $hero->fields()->create(['title' => 'Cards', 'slug' => 'cards', 'resolver' => 'widget', 'options' => ['widgets' => [$widget->id]]]);
    $raw = [['layout' => 'hero', 'key' => 'hero-old', 'attributes' => ['reference' => ['model' => 'event', 'id' => 19], 'cards' => [['layout' => 'card', 'key' => 'card-old', 'attributes' => ['text' => 'Original']]]]]];
    $source = Page::create(['name' => 'Source', 'path' => '/', 'locale' => 'en', 'components' => $raw, 'fixed_components' => [], 'is_active' => true]);
    $source->revisions()->create([...$source->contentSnapshot(), 'released_at' => now()]);
    $target = app(PageTranslationService::class)->create($source, 'zh-cn', '/');
    expect($target->locale)->toBe('zh-CN')->and($target->translation_group_id)->toBe($source->translation_group_id)
        ->and($target->is_active)->toBeFalse()->and($target->has_stash)->toBeFalse()
        ->and($target->revisions()->count())->toBe(0)
        ->and($target->components[0]['key'])->not->toBe('hero-old')
        ->and($target->components[0]['attributes']['cards'][0]['key'])->not->toBe('card-old')
        ->and($target->components[0]['attributes']['reference'])->toBe(['model' => 'event', 'id' => 19])
        ->and($source->fresh()->components)->toBe($raw);
    $target->update(['name' => 'Translation']);
    expect($source->fresh()->name)->toBe('Source');
});

it('refuses duplicate languages and conflicting paths without leaving records', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'Source', 'path' => '/', 'components' => []]);
    $service = app(PageTranslationService::class);
    $service->create($source, 'zh-CN', '/');
    expect(fn () => $service->create($source, 'zh-CN', '/other'))->toThrow(ValidationException::class);
    $other = Page::create(['name' => 'Other', 'path' => '/taken', 'locale' => 'fr']);
    expect(fn () => $service->create($source, 'fr', '/taken'))->toThrow(ValidationException::class);
    expect(Page::count())->toBe(3);
});

it('checks source read target create and target update authorization', function () {
    translationPageAdmin(['viewPage', 'createPage']);
    $source = Page::create(['name' => 'Source', 'path' => '/', 'components' => []]);
    expect(fn () => app(PageTranslationService::class)->create($source, 'fr', '/'))->toThrow(AuthorizationException::class);
    expect(Page::count())->toBe(1);
});

it('does not copy sensitive fixed or nested content without explicit permission', function () {
    translationPageAdmin();
    $component = PageComponent::create(['name' => 'Secret', 'slug' => 'secret']);
    $component->fields()->create(['title' => 'Secret', 'slug' => 'token', 'resolver' => 'sensitive']);
    config(['filament-page.fixed_components.basic' => ['secret' => 1]]);
    $source = Page::create(['name' => 'Source', 'path' => '/', 'fixed_components' => [['layout' => 'secret', 'key' => 'secret-key', 'attributes' => ['token' => 'private']]]]);
    expect(fn () => app(PageTranslationService::class)->create($source, 'fr', '/'))->toThrow(AuthorizationException::class);
    expect(Page::count())->toBe(1);
});

it('restores legacy revision and stash content without changing language identity', function () {
    $page = Page::create(['name' => 'Current', 'path' => '/', 'locale' => 'zh-CN']);
    $identity = $page->translation_group_id;
    $revision = $page->revisions()->create([...$page->contentSnapshot(), 'name' => 'Old content', 'locale' => 'en', 'fallback_id' => 123, 'released_at' => now()]);
    $service = app(PageRevisionService::class);
    $service->restore($page, $revision);
    expect($page->fresh()->name)->toBe('Old content')->and($page->fresh()->locale)->toBe('zh-CN')->and($page->fresh()->translation_group_id)->toBe($identity);
    $stash = $page->revisions()->create([...$page->contentSnapshot(), 'name' => 'Stashed', 'locale' => 'en', 'fallback_id' => 456, 'is_stash' => true]);
    $service->restoreStash($page);
    expect($page->fresh()->name)->toBe('Stashed')->and($page->fresh()->locale)->toBe('zh-CN')->and($page->fresh()->translation_group_id)->toBe($identity);
});

it('creates and switches translations through authorized Filament actions', function () {
    translationPageAdmin();
    $page = Page::create(['name' => 'Source', 'path' => '/', 'components' => [], 'fixed_components' => []]);
    Livewire::test(EditPage::class, ['record' => $page->id])
        ->callAction('createTranslation', data: ['locale' => 'fr', 'path' => '/fr'])
        ->assertHasNoErrors();
    $target = $page->getTranslation('fr');
    expect($target)->not->toBeNull()->and($target->is_active)->toBeFalse();
    Livewire::test(EditPage::class, ['record' => $page->id])
        ->callAction('switchLanguage', data: ['locale' => 'fr'])
        ->assertRedirect();
    expect($page->fresh()->locale)->toBe('en');
});

it('uses the configured language default and locks identity on regular edits', function () {
    config(['filament-cms.default_locale' => 'fr']);
    $page = Page::create(['name' => 'French', 'path' => '/']);
    expect($page->locale)->toBe('fr');
    expect(fn () => $page->update(['locale' => 'en']))->toThrow(ValidationException::class);
});

it('serves strict versioned locale APIs without changing legacy response shapes', function () {
    config(['app.locale' => 'fr']);
    app()->setLocale('fr');
    $en = Page::create(['name' => 'English', 'path' => '/', 'locale' => 'en', 'is_active' => true, 'components' => []]);
    Page::create(['name' => 'French draft', 'path' => '/', 'locale' => 'fr', 'translation_group_id' => $en->translation_group_id, 'components' => []]);
    $this->getJson('/modules/pages/v2/detail')->assertOk()->assertJsonPath('name', 'English')->assertJsonCount(1, 'translations')->assertJsonMissing(['name' => 'French draft']);
    $this->getJson('/modules/pages/v2/detail?locale=FR')->assertNotFound();
    $this->getJson('/modules/pages/v2/detail?locale=zh')->assertUnprocessable();
    $this->getJson('/modules/pages/v2/slugs?locale=en')->assertExactJson([['name' => 'English', 'path' => '/']]);
    $this->getJson('/modules/pages/slugs?locale=en')->assertExactJson([['name' => 'English', 'path' => '/']]);
    config(['filament-cms.multilingual_enabled' => false]);
    $this->getJson('/modules/pages/v2/detail?locale=fr')->assertUnprocessable();
    $this->getJson('/modules/pages/detail?locale=fr')->assertOk()->assertJsonPath('name', 'English');
});

it('blocks language actions with unsaved editor changes', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'Source', 'path' => '/', 'components' => [], 'fixed_components' => []]);
    Livewire::test(EditPage::class, ['record' => $source->id])
        ->fillForm(['name' => 'Unsaved draft'])
        ->callAction('createTranslation', data: ['locale' => 'fr', 'path' => '/'])
        ->assertHasErrors(['locale']);
    expect(Page::count())->toBe(1)->and($source->fresh()->name)->toBe('Source');
});

it('keeps nondefault records out of the editor when multilingual is disabled', function () {
    translationPageAdmin();
    $page = Page::create(['name' => 'French', 'path' => '/', 'locale' => 'fr']);
    config(['filament-cms.multilingual_enabled' => false]);
    Livewire::test(EditPage::class, ['record' => $page->id])->assertNotFound();
});

it('serves the released content and path instead of a later unpublished edit', function () {
    $page = Page::create(['name' => 'Published', 'path' => '/published', 'is_active' => true, 'components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now()]);
    $page->update(['name' => 'Private draft', 'path' => '/draft', 'changed' => true]);
    $this->getJson('/modules/pages/v2/detail/published')->assertOk()->assertJsonPath('name', 'Published')->assertJsonPath('path', '/published');
    $this->getJson('/modules/pages/detail/published')->assertOk()->assertJsonPath('name', 'Published');
    $this->getJson('/modules/pages/v2/detail/draft')->assertNotFound();
    $this->getJson('/modules/pages/v2/slugs')->assertExactJson([['name' => 'Published', 'path' => '/published']]);
});

it('reserves released paths while an unpublished path change is being edited', function () {
    $page = Page::create(['name' => 'Published', 'path' => '/published', 'is_active' => true, 'components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now()]);
    $page->update(['path' => '/draft', 'changed' => true]);
    expect(fn () => app(PageContentValidator::class)->validate([
        'path' => '/published', 'locale' => 'en', 'type' => 'basic', 'components' => [], 'fixed_components' => [],
    ]))->toThrow(ValidationException::class);
});

it('offers new entity references in the content language while keeping existing foreign references', function () {
    translationPageAdmin();
    config(['filament-page-builder.entities' => ['page' => ['model' => Page::class, 'label' => 'name', 'fields' => ['id', 'name']]]]);
    $english = Page::create(['name' => 'Target English', 'path' => '/target-en', 'locale' => 'en']);
    $french = Page::create(['name' => 'Target French', 'path' => '/target-fr', 'locale' => 'fr']);
    $definition = PageComponent::create(['name' => 'References', 'slug' => 'references']);
    $definition->fields()->create(['title' => 'Page', 'slug' => 'page', 'resolver' => 'entity', 'options' => ['reference' => ['page']]]);
    $raw = [['layout' => 'references', 'key' => 'reference-key', 'attributes' => ['page' => ['model' => 'page', 'id' => $english->id]]]];
    $source = Page::create(['name' => 'French editor', 'path' => '/source', 'locale' => 'fr', 'components' => $raw]);
    $editor = Livewire::test(EditPage::class, ['record' => $source->id]);
    $field = $editor->instance()->form->getComponent(fn ($field) => $field instanceof EntityReferenceSelect);
    expect(array_values($field->getSearchResults('Target')))->toBe(['page: Target French']);
    expect($field->getOptionLabel())->toBe('page: Target English');
    $editor->call('save')->assertHasNoFormErrors();
    expect($source->fresh()->components)->toBe($raw);
});

it('projects public referenced pages without leaking drafts or recursing through cyclic references', function () {
    config(['filament-page-builder.resolve_entities' => true, 'filament-page-builder.entities' => [
        'page' => ['model' => Page::class, 'label' => 'name', 'fields' => ['name', 'components']],
    ]]);
    $definition = PageComponent::create(['name' => 'References', 'slug' => 'references']);
    $definition->fields()->create(['title' => 'Page', 'slug' => 'page', 'resolver' => 'entity', 'options' => ['reference' => ['page']]]);
    $first = Page::create(['name' => 'First', 'path' => '/first', 'is_active' => true]);
    $second = Page::create(['name' => 'Released', 'path' => '/second', 'is_active' => true]);
    $block = fn ($id) => [['layout' => 'references', 'key' => 'reference', 'attributes' => ['page' => ['model' => 'page', 'id' => $id]]]];
    $first->update(['components' => $block($second->id)]);
    $second->update(['components' => $block($first->id)]);
    $second->revisions()->create([...$second->contentSnapshot(), 'released_at' => now()]);
    $second->update(['name' => 'Private unpublished wording']);
    $projection = $first->publicProjection();
    expect($projection['components'][0]['attributes']['page']['name'])->toBe('Released')
        ->and(json_encode($projection))->not->toContain('Private unpublished wording');
    $second->update(['is_active' => false]);
    expect($first->publicProjection()['components'][0]['attributes']['page'])->toBeNull();
});

it('hides translation creation once all configured languages exist', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'Source', 'path' => '/complete', 'locale' => 'en']);
    foreach (['zh-CN', 'fr'] as $locale) {
        Page::create(['name' => $locale, 'path' => '/complete', 'locale' => $locale, 'translation_group_id' => $source->translation_group_id]);
    }
    Livewire::test(EditPage::class, ['record' => $source->id])->assertActionHidden('createTranslation');
});

it('switches directly when only one other supported language exists', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'English', 'path' => '/direct', 'locale' => 'en']);
    $target = Page::create(['name' => 'French', 'path' => '/direct', 'locale' => 'fr', 'translation_group_id' => $source->translation_group_id]);
    Livewire::test(EditPage::class, ['record' => $source->id])
        ->callAction('switchLanguage')
        ->assertRedirect(PageResource::getUrl('edit', ['record' => $target]));
});

it('hides language switching without another accessible supported translation', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'English', 'path' => '/only', 'locale' => 'en']);
    Livewire::test(EditPage::class, ['record' => $source->id])->assertActionHidden('switchLanguage')->assertActionVisible('createTranslation');
    Page::create(['name' => 'French', 'path' => '/only', 'locale' => 'fr', 'translation_group_id' => $source->translation_group_id]);
    config(['filament-cms.supported_locales' => ['en' => 'English', 'zh-CN' => '中文']]);
    Livewire::test(EditPage::class, ['record' => $source->id])->assertActionHidden('switchLanguage');
});

it('retains a language picker for multiple translations and excludes the current language', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'English', 'path' => '/picker', 'locale' => 'en']);
    foreach (['zh-CN', 'fr'] as $locale) {
        Page::create(['name' => $locale, 'path' => '/picker', 'locale' => $locale, 'translation_group_id' => $source->translation_group_id]);
    }
    $view = Livewire::test(EditPage::class, ['record' => $source->id])->mountAction('switchLanguage')->assertActionMounted('switchLanguage');
    $view->assertFormFieldExists('locale', function (Select $field): bool {
        expect(array_keys($field->getOptions()))->toEqualCanonicalizing(['zh-CN', 'fr']);

        return true;
    });
});

it('blocks a direct language switch when editor changes are unsaved', function () {
    translationPageAdmin();
    $source = Page::create(['name' => 'English', 'path' => '/dirty', 'locale' => 'en']);
    Page::create(['name' => 'French', 'path' => '/dirty', 'locale' => 'fr', 'translation_group_id' => $source->translation_group_id]);
    Livewire::test(EditPage::class, ['record' => $source->id])->fillForm(['name' => 'Unsaved'])
        ->callAction('switchLanguage')->assertHasErrors(['locale'])->assertNoRedirect();
    expect($source->fresh()->name)->toBe('English');
});
