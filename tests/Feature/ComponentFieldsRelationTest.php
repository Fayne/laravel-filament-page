<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageComponentFieldResource;
use Fayne\Page\Filament\Resources\PageComponentFieldResource\Pages\CreatePageComponentField;
use Fayne\Page\Filament\Resources\PageComponentResource;
use Fayne\Page\Filament\Resources\PageComponentResource\Pages\EditPageComponent;
use Fayne\Page\Filament\Resources\PageComponentResource\RelationManagers\FieldsRelationManager;
use Fayne\Page\Models\PageComponent;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function componentFieldEditor(array $permissions = []): Admin
{
    $admin = Admin::create(['name' => 'Schema editor', 'email' => 'fields@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponent', 'viewPageComponent', 'updatePageComponent', 'viewAnyPageComponentField', ...$permissions] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);

    return $admin;
}
function componentFieldsManager(PageComponent $component): mixed
{
    return Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $component, 'pageClass' => EditPageComponent::class]);
}
it('embeds the field manager on the component edit page and scopes its records', function () {
    componentFieldEditor();
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $own = $component->fields()->create(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text']);
    $other = PageComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'slug' => 'foreign', 'resolver' => 'text']);
    expect(PageComponentResource::getRelations())->toContain(FieldsRelationManager::class);
    Livewire::test(EditPageComponent::class, ['record' => $component->id])->assertSeeLivewire(FieldsRelationManager::class);
    componentFieldsManager($component)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
});
it('creates edits and deletes a field without permitting a different component association', function () {
    componentFieldEditor(['createPageComponentField', 'viewPageComponentField', 'updatePageComponentField', 'deletePageComponentField']);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $other = PageComponent::create(['name' => 'Other', 'slug' => 'other']);
    componentFieldsManager($component)->callAction(TestAction::make('create')->table(), data: ['title' => 'Body', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 10, 'options' => '{"toolbar":["bold","attachFiles"]}', 'rules' => '{"rules":["required"]}', 'page_component_id' => $other->id])->assertHasNoFormErrors();
    $field = $component->fields()->sole();
    expect($field->page_component_id)->toBe($component->id)->and($field->options)->toBe(['toolbar' => ['bold', 'attachFiles']]);
    componentFieldsManager($component)->callAction(TestAction::make('edit')->table($field), data: ['title' => 'Article', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 20, 'options' => '{}', 'rules' => '{}', 'page_component_id' => $other->id])->assertHasNoFormErrors();
    expect($field->fresh()->title)->toBe('Article')->and($field->fresh()->page_component_id)->toBe($component->id);
    componentFieldsManager($component)->callAction(TestAction::make('delete')->table($field))->assertHasNoErrors();
    expect($field->fresh())->toBeNull();
});
it('reorders only the complete current component field set', function () {
    componentFieldEditor(['updatePageComponentField']);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $first = $component->fields()->create(['title' => 'First', 'slug' => 'first', 'resolver' => 'text', 'weight' => 10]);
    $second = $component->fields()->create(['title' => 'Second', 'slug' => 'second', 'resolver' => 'text', 'weight' => 20]);
    $foreign = PageComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text', 'weight' => 50]);
    componentFieldsManager($component)->call('reorderTable', [$second->id, $first->id])->assertHasNoErrors();
    expect($component->fields()->pluck('id')->all())->toBe([$second->id, $first->id]);
    componentFieldsManager($component)->call('reorderTable', [$first->id, $foreign->id])->assertHasErrors();
    expect($component->fields()->pluck('id')->all())->toBe([$second->id, $first->id])->and($foreign->fresh()->weight)->toBe(50);
});
it('does not grant field mutations through component edit permission alone', function () {
    componentFieldEditor();
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $field = $component->fields()->create(['title' => 'Title', 'resolver' => 'text']);
    componentFieldsManager($component)->assertActionHidden(TestAction::make('create')->table())->assertActionHidden(TestAction::make('edit')->table($field))->assertActionHidden(TestAction::make('delete')->table($field));
    componentFieldsManager($component)->call('reorderTable', [$field->id])->assertForbidden();
});

it('reorders components with more than nine fields', function () {
    componentFieldEditor(['updatePageComponentField']);
    $component = PageComponent::create(['name' => 'Large', 'slug' => 'large']);
    foreach (range(1, 12) as $index) {
        $component->fields()->create(['title' => 'Field '.$index, 'slug' => 'field_'.$index, 'resolver' => 'text', 'weight' => $index]);
    }
    $order = $component->fields()->pluck('id')->reverse()->values()->all();
    componentFieldsManager($component)->call('reorderTable', $order)->assertHasNoErrors();
    expect($component->fields()->pluck('id')->all())->toBe($order);
});

it('cannot edit a field belonging to a different component by supplying its record key', function () {
    componentFieldEditor(['updatePageComponentField']);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $foreign = PageComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text']);
    componentFieldsManager($component)->mountAction(TestAction::make('edit')->table($foreign))->assertActionNotMounted();
    expect($foreign->fresh()->title)->toBe('Foreign');
});

it('requires permission to update the parent even when field creation is allowed', function () {
    $admin = componentFieldEditor(['createPageComponentField']);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.updatePageComponent');
    componentFieldsManager($component)->assertActionHidden(TestAction::make('create')->table());
    expect($component->fields()->count())->toBe(0);
});

it('rejects direct relation manager access without permission to view the parent', function () {
    $admin = componentFieldEditor();
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.viewPageComponent');
    componentFieldsManager($component)->assertForbidden();
});

it('carries its component into the standalone field creation link and form', function () {
    componentFieldEditor(['createPageComponentField']);
    $owner = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $resource = PageComponentFieldResource::class;
    $action = componentFieldsManager($owner)->instance()->getTable()->getHeaderActions()[0];
    expect($action->getUrl())->toBe($resource::getUrl('create', ['page_component_id' => $owner->id]));
    Livewire::withQueryParams(['page_component_id' => $owner->id])->test(CreatePageComponentField::class)
        ->assertSet('data.page_component_id', $owner->id)
        ->fillForm(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text', 'options' => '{}', 'rules' => '[]'])
        ->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->sole()->page_component_id)->toBe($owner->id);
});
