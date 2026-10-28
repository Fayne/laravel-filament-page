<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Filament\Resources\PageComponentFieldResource\Pages\CreatePageComponentField;
use Fayne\Page\Models\PageComponent;
use Livewire\Livewire;

it('preserves the owner when navigating from a definition into standalone field creation', function (string $kind, string $foreignKey) {
    $parent = 'Fayne\\Page\\Models\\Page'.$kind;
    $parentResource = 'Fayne\\Page\\Filament\\Resources\\Page'.$kind.'Resource';
    $fieldResource = 'Fayne\\Page\\Filament\\Resources\\Page'.$kind.'FieldResource';
    $owner = $parent::create(['name' => 'Owner', 'slug' => 'owner']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'owner-context@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewPage'.$kind, 'updatePage'.$kind, 'viewAnyPage'.$kind.'Field', 'createPage'.$kind.'Field'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $manager = Livewire::test($parentResource.'\\RelationManagers\\FieldsRelationManager', ['ownerRecord' => $owner, 'pageClass' => $parentResource.'\\Pages\\EditPage'.$kind]);
    expect($manager->instance()->getTable()->getHeaderActions()[0]->getUrl())->toBe($fieldResource::getUrl('create', [$foreignKey => $owner->id]));
    Livewire::withQueryParams([$foreignKey => $owner->id])->test($fieldResource.'\\Pages\\CreatePage'.$kind.'Field')
        ->assertSet('data.'.$foreignKey, $owner->id)
        ->fillForm(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text', 'options' => '{}', 'rules' => '[]'])
        ->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->sole()->getAttribute($foreignKey))->toBe($owner->id);
})->with([['Widget', 'nova_page_widget_id']]);

it('does not preselect malformed missing or unauthorized owner context', function (mixed $query, bool $mayView) {
    $owner = PageComponent::create(['name' => 'Owner', 'slug' => 'owner']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'invalid-owner@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponentField', 'createPageComponentField', ...($mayView ? ['viewPageComponent'] : [])] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::withQueryParams(['page_component_id' => $query === 'current' ? $owner->id : $query])
        ->test(CreatePageComponentField::class)
        ->assertSet('data.page_component_id', null);
})->with([[['invalid'], true], [99999, true], ['current', false], [-1, true]]);

it('keeps the selected component when creating another field', function () {
    app(TenantContext::class)->set(Tenant::create(['name' => 'Test', 'slug' => 'test']));
    $owner = PageComponent::create(['name' => 'Owner', 'slug' => 'owner']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'another-field@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponentField', 'createPageComponentField', 'viewPageComponent'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $page = Livewire::test(CreatePageComponentField::class)
        ->fillForm(['page_component_id' => $owner->id, 'title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text', 'options' => '{}', 'rules' => '[]'])
        ->call('create', true)->assertHasNoFormErrors()
        ->assertSet('data.page_component_id', $owner->id)
        ->assertSet('data.title', null)
        ->assertSet('data.slug', null);
    $page->fillForm(['title' => 'Body', 'slug' => 'body', 'resolver' => 'text', 'options' => '{}', 'rules' => '[]'])
        ->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->count())->toBe(2);
});
