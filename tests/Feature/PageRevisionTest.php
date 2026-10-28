<?php

use Fayne\CMS\Models\Admin;
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
