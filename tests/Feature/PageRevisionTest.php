<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Filament\Resources\PageRevisionResource\Pages\ListPageRevisions;
use Fayne\Page\Models\Page;
use Fayne\Page\Services\PageRevisionService;
use Illuminate\Validation\ValidationException;

it('stashes and restores raw content and fixed components atomically', function () {
    $page = Page::create(['path' => '/', 'name' => 'Published', 'components' => [['layout' => 'hero', 'key' => 'old', 'attributes' => []]], 'fixed_components' => []]);
    $revision = $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $draft = [['layout' => 'hero', 'key' => 'draft', 'attributes' => ['image' => ['disk' => 'public', 'path' => 'x.jpg']]]];
    $page->update(['name' => 'Draft', 'components' => $draft, 'fixed_components' => $draft]);
    $service = new PageRevisionService;
    $service->stash($page);
    expect($page->fresh()->name)->toBe('Published')->and($page->fresh()->has_stash)->toBeTrue();
    $service->restoreStash($page);
    expect($page->fresh()->components)->toBe($draft)->and($page->fresh()->fixed_components)->toBe($draft)
        ->and($page->fresh()->has_stash)->toBeFalse()->and($page->revisions()->where('is_stash', true)->exists())->toBeFalse();
});

it('refuses restoring a revision belonging to another page', function () {
    $first = Page::create(['name' => 'First', 'path' => '/first']);
    $second = Page::create(['name' => 'Second', 'path' => '/second']);
    $revision = $second->revisions()->create([...$second->contentSnapshot(), 'released_at' => now()]);
    try {
        (new PageRevisionService)->restore($first, $revision);
        $this->fail('Cross-page revision was accepted.');
    } catch (ValidationException) {
        expect($first->fresh()->name)->toBe('First');
    }
});

it('restores a revision through a separately authorized table action', function () {
    $page = Page::create(['path' => '/', 'name' => 'Published', 'components' => []]);
    $revision = $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $page->update(['name' => 'Draft']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'revision@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageRevision', 'viewPageRevision', 'viewAnyPage', 'viewPage', 'updatePage'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire\Livewire::test(ListPageRevisions::class)
        ->callTableAction('restore', $revision)->assertHasNoErrors();
    expect($page->fresh()->name)->toBe('Published');
});

function stashPageEditor(): Admin
{
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyPage', 'viewPage', 'updatePage'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);

    return $admin;
}

it('does not stash or discard unsaved editor input', function () {
    stashPageEditor();
    $page = Page::create(['name' => 'Published', 'path' => '/dirty-stash', 'components' => [], 'fixed_components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $page->update(['name' => 'Saved draft']);
    Livewire\Livewire::test(EditPage::class, ['record' => $page->id])
        ->fillForm(['name' => 'Unsaved input'])->callAction('stash')
        ->assertHasErrors()->assertFormSet(['name' => 'Unsaved input'])
        ->assertNotified(__('filament-page::translations.save_first'));
    expect($page->fresh()->name)->toBe('Saved draft')->and($page->fresh()->has_stash)->toBeFalse()
        ->and($page->stashRevision()->exists())->toBeFalse();
});

it('does not restore a stash over unsaved editor input', function () {
    stashPageEditor();
    $page = Page::create(['name' => 'Published', 'path' => '/dirty-restore', 'components' => [], 'fixed_components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $page->update(['name' => 'Saved draft']);
    app(PageRevisionService::class)->stash($page);
    Livewire\Livewire::test(EditPage::class, ['record' => $page->id])
        ->fillForm(['name' => 'Unsaved input'])->callAction('restoreStash')
        ->assertHasErrors()->assertFormSet(['name' => 'Unsaved input'])
        ->assertNotified(__('filament-page::translations.save_first'));
    expect($page->fresh()->name)->toBe('Published')->and($page->fresh()->has_stash)->toBeTrue()
        ->and($page->stashRevision()->firstOrFail()->name)->toBe('Saved draft');
});

it('can save then stash and restore through the editor', function () {
    stashPageEditor();
    $page = Page::create(['name' => 'Published', 'path' => '/saved-stash', 'components' => [], 'fixed_components' => []]);
    $page->revisions()->create([...$page->contentSnapshot(), 'released_at' => now(), 'is_stash' => false]);
    $view = Livewire\Livewire::test(EditPage::class, ['record' => $page->id]);
    $view->fillForm(['name' => 'Saved draft'])->call('save')->assertHasNoFormErrors()
        ->callAction('stash')->assertHasNoErrors()->assertFormSet(['name' => 'Published']);
    expect($page->stashRevision()->firstOrFail()->name)->toBe('Saved draft');
    $view->callAction('restoreStash')->assertHasNoErrors()->assertFormSet(['name' => 'Saved draft']);
    expect($page->fresh()->name)->toBe('Saved draft')->and($page->stashRevision()->exists())->toBeFalse();
});
