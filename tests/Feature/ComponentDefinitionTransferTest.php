<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Filament\Resources\PageComponentResource\Pages\ListPageComponents;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageWidget;
use Fayne\Page\Services\ComponentDefinitionTransferService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    app(TenantContext::class)->set(Tenant::create(['name' => 'Test', 'slug' => 'test']));
});

it('imports defaults remaps widget ids and round trips definitions', function () {
    $service = new ComponentDefinitionTransferService;
    $data = json_decode(file_get_contents(__DIR__.'/../../resources/defaults/components.json'), true);
    PageWidget::create(['slug' => 'existing', 'name' => 'Existing']);
    $service->import($data, 'skip');
    expect(PageComponent::count())->toBe(count($data));
    $field = PageComponent::where('slug', 'hero-banner')->firstOrFail()->fields()->where('resolver', 'widget')->firstOrFail();
    expect($field->options['widgets'])->toBe([PageWidget::where('slug', 'banner-widget')->value('id')]);
    $export = $service->export();
    $service->import($export, 'update');
    expect($service->export())->toBe($export);
});

it('preserves or updates existing definitions using the conflict decision', function () {
    $service = new ComponentDefinitionTransferService;
    $data = [['name' => 'New', 'slug' => 'hero', 'fields' => []]];
    $component = PageComponent::create(['name' => 'Old', 'slug' => 'hero']);
    expect($service->conflicts($data))->toBe(['component:hero']);
    $service->import($data, 'skip');
    expect($component->fresh()->name)->toBe('Old');
    $service->import($data, 'update');
    expect($component->fresh()->name)->toBe('New');
});

it('rejects malformed definitions before writing anything', function () {
    $service = new ComponentDefinitionTransferService;
    expect(fn () => $service->import([['slug' => 'good', 'name' => 'Good', 'fields' => []], ['slug' => 'bad']], 'skip'))->toThrow(ValidationException::class);
    expect(PageComponent::count())->toBe(0);
});

it('opens a conflict modal before changing records and applies the selected decision', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'transfer@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponent', 'createPageComponent', 'updatePageComponent', 'viewPageComponent', 'createPageWidget'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $record = PageComponent::create(['name' => 'Old', 'slug' => 'hero']);
    $page = Livewire::test(ListPageComponents::class)
        ->callAction('importDefinitions', data: ['json' => json_encode([['name' => 'New', 'slug' => 'hero', 'fields' => []]])])
        ->assertHasNoFormErrors()
        ->assertActionMounted('resolveDefinitionConflicts');
    expect($record->fresh()->name)->toBe('Old');
    $page->fillForm(['mode' => 'update'])->callMountedAction()->assertHasNoFormErrors();
    expect($record->fresh()->name)->toBe('New');
});

it('scopes definitions to the current tenant', function () {
    $service = new ComponentDefinitionTransferService;
    $data = [['name' => 'Hero', 'slug' => 'hero', 'fields' => []]];
    $service->import($data, 'skip');
    app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other']));
    expect($service->conflicts($data))->toBe([])->and($service->export())->toBe([]);
    $service->import($data, 'update');
    expect(PageComponent::count())->toBe(1)->and(PageComponent::withoutGlobalScopes()->count())->toBe(2);
});

it('skips existing empty widgets and retains omitted fields when updating', function () {
    $service = new ComponentDefinitionTransferService;
    $widget = PageWidget::create(['name' => 'Keep', 'slug' => 'banner-widget']);
    $service->import($service->defaults(), 'skip');
    expect($widget->fresh()->name)->toBe('Keep')->and($widget->fields()->count())->toBe(0);
    $hero = PageComponent::where('slug', 'hero-banner')->firstOrFail();
    $field = $hero->fields()->create(['title' => 'Custom', 'slug' => 'custom', 'resolver' => 'text']);
    $service->import($service->defaults(), 'update');
    expect($widget->fresh()->name)->toBe('Banner Widget')->and($field->fresh()->slug)->toBe('custom');
});
