<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\ListPages;
use Fayne\Page\Models\Page;
use Livewire\Livewire;

it('shows one representative per group with language counts and group language filtering', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'fr' => 'French']]);
    $tenant = Tenant::create(['name' => 'List test', 'slug' => 'list-test']);
    app(TenantContext::class)->set($tenant);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'group-list@example.test', 'password' => 'password', 'is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewAnyPage'));
    // Tenant permissions are resolved through membership in a tenant context.
    $fr = Page::create(['name' => 'French first', 'path' => '/fr', 'locale' => 'fr']);
    $en = Page::create(['name' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_group_id' => $fr->translation_group_id]);
    $fallback = Page::create(['name' => 'French only', 'path' => '/only-fr', 'locale' => 'fr']);
    $englishOnly = Page::create(['name' => 'English only', 'path' => '/only-en', 'locale' => 'en']);

    expect(PageResource::getEloquentQuery()->find($fr->id)?->id)->toBe($fr->id);

    Livewire::actingAs($admin->fresh(), 'admin')->test(ListPages::class)
        ->assertCanSeeTableRecords([$en, $fallback, $englishOnly])
        ->assertCanNotSeeTableRecords([$fr])
        ->assertCountTableRecords(3)
        ->assertTableColumnStateSet('translation_languages', 'French / English', $en)
        ->assertTableColumnExists('path')
        ->filterTable('locale', 'fr')
        ->assertCanSeeTableRecords([$en, $fallback])
        ->assertCanNotSeeTableRecords([$englishOnly])
        ->assertCountTableRecords(2);
});

it('toggles every language version and deletes only the selected language scope', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'fr' => 'French']]);
    $tenant = Tenant::create(['name' => 'Actions', 'slug' => 'actions']);
    app(TenantContext::class)->set($tenant);
    $admin = Admin::create(['name' => 'Manager', 'email' => 'actions@example.test', 'password' => 'password', 'is_active' => true]);
    $permissions = collect(['viewAnyPage', 'updatePage', 'deletePage'])->map(fn ($name) => test()->permission($name)->id)->all();
    test()->grantPermissions($admin, ['viewAnyPage', 'updatePage', 'deletePage']);
    $en = Page::create(['name' => 'English', 'path' => '/actions', 'locale' => 'en']);
    $fr = Page::create(['name' => 'French', 'path' => '/actions', 'locale' => 'fr', 'translation_group_id' => $en->translation_group_id]);
    $other = Page::create(['name' => 'Other', 'path' => '/other', 'locale' => 'en']);
    $component = Livewire::actingAs($admin, 'admin')->test(ListPages::class);
    $component->call('updateTableColumnState', 'is_active', (string) $en->id, true);
    expect($en->fresh()->is_active)->toBeTrue()->and($fr->fresh()->is_active)->toBeTrue()
        ->and($other->fresh()->is_active)->toBeFalse();
    $component->call('updateTableColumnState', 'is_active', (string) $en->id, false);
    expect($en->fresh()->is_active)->toBeFalse()->and($fr->fresh()->is_active)->toBeFalse();
    $component->callTableAction('delete', $en, data: ['all_languages' => false]);
    expect(Page::find($en->id))->toBeNull()->and(Page::find($fr->id))->not->toBeNull();
    $en2 = Page::create(['name' => 'English again', 'path' => '/actions', 'locale' => 'en', 'translation_group_id' => $fr->translation_group_id]);
    Livewire::test(ListPages::class)->callTableAction('delete', $en2, data: ['all_languages' => true]);
    expect(Page::find($en2->id))->toBeNull()->and(Page::find($fr->id))->toBeNull()->and(Page::find($other->id))->not->toBeNull();
});
