<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageWidgetResource;
use Fayne\Page\Filament\Resources\PageWidgetResource\Pages\EditPageWidget;
use Fayne\Page\Filament\Resources\PageWidgetResource\RelationManagers\FieldsRelationManager;
use Fayne\Page\Models\PageWidget;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function widgetFieldEditor(array $permissions = []): Admin
{
    $admin = Admin::create(['name' => 'Schema editor', 'email' => 'fields@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageWidget', 'viewPageWidget', 'updatePageWidget', 'viewAnyPageWidgetField', ...$permissions] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);

    return $admin;
}
function widgetFieldsManager(PageWidget $widget): mixed
{
    return Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $widget, 'pageClass' => EditPageWidget::class]);
}
it('embeds the field manager on the widget edit page and scopes its records', function () {
    widgetFieldEditor();
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $own = $widget->fields()->create(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text']);
    $other = PageWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'slug' => 'foreign', 'resolver' => 'text']);
    expect(PageWidgetResource::getRelations())->toContain(FieldsRelationManager::class);
    Livewire::test(EditPageWidget::class, ['record' => $widget->id])->assertSeeLivewire(FieldsRelationManager::class);
    widgetFieldsManager($widget)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
});
it('creates edits and deletes a field without permitting a different widget association', function () {
    widgetFieldEditor(['createPageWidgetField', 'viewPageWidgetField', 'updatePageWidgetField', 'deletePageWidgetField']);
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $other = PageWidget::create(['name' => 'Other', 'slug' => 'other']);
    widgetFieldsManager($widget)->callAction(TestAction::make('create')->table(), data: ['title' => 'Body', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 10, 'options' => '{"toolbar":["bold","attachFiles"]}', 'rules' => '{"rules":["required"]}', 'nova_page_widget_id' => $other->id])->assertHasNoFormErrors();
    $field = $widget->fields()->sole();
    expect($field->nova_page_widget_id)->toBe($widget->id)->and($field->options)->toBe(['toolbar' => ['bold', 'attachFiles']]);
    widgetFieldsManager($widget)->callAction(TestAction::make('edit')->table($field), data: ['title' => 'Article', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 20, 'options' => '{}', 'rules' => '{}', 'nova_page_widget_id' => $other->id])->assertHasNoFormErrors();
    expect($field->fresh()->title)->toBe('Article')->and($field->fresh()->nova_page_widget_id)->toBe($widget->id);
    widgetFieldsManager($widget)->callAction(TestAction::make('delete')->table($field))->assertHasNoErrors();
    expect($field->fresh())->toBeNull();
});
it('reorders only the complete current widget field set', function () {
    widgetFieldEditor(['updatePageWidgetField']);
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $first = $widget->fields()->create(['title' => 'First', 'slug' => 'first', 'resolver' => 'text', 'weight' => 10]);
    $second = $widget->fields()->create(['title' => 'Second', 'slug' => 'second', 'resolver' => 'text', 'weight' => 20]);
    $foreign = PageWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text', 'weight' => 50]);
    widgetFieldsManager($widget)->call('reorderTable', [$second->id, $first->id])->assertHasNoErrors();
    expect($widget->fields()->pluck('id')->all())->toBe([$second->id, $first->id]);
    widgetFieldsManager($widget)->call('reorderTable', [$first->id, $foreign->id])->assertHasErrors();
    expect($widget->fields()->pluck('id')->all())->toBe([$second->id, $first->id])->and($foreign->fresh()->weight)->toBe(50);
});
it('does not grant field mutations through widget edit permission alone', function () {
    widgetFieldEditor();
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $field = $widget->fields()->create(['title' => 'Title', 'resolver' => 'text']);
    widgetFieldsManager($widget)->assertActionHidden(TestAction::make('create')->table())->assertActionHidden(TestAction::make('edit')->table($field))->assertActionHidden(TestAction::make('delete')->table($field));
    widgetFieldsManager($widget)->call('reorderTable', [$field->id])->assertForbidden();
});

it('reorders widgets with more than nine fields', function () {
    widgetFieldEditor(['updatePageWidgetField']);
    $widget = PageWidget::create(['name' => 'Large', 'slug' => 'large']);
    foreach (range(1, 12) as $index) {
        $widget->fields()->create(['title' => 'Field '.$index, 'slug' => 'field_'.$index, 'resolver' => 'text', 'weight' => $index]);
    }
    $order = $widget->fields()->pluck('id')->reverse()->values()->all();
    widgetFieldsManager($widget)->call('reorderTable', $order)->assertHasNoErrors();
    expect($widget->fields()->pluck('id')->all())->toBe($order);
});

it('cannot edit a field belonging to a different widget by supplying its record key', function () {
    widgetFieldEditor(['updatePageWidgetField']);
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $foreign = PageWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text']);
    widgetFieldsManager($widget)->mountAction(TestAction::make('edit')->table($foreign))->assertActionNotMounted();
    expect($foreign->fresh()->title)->toBe('Foreign');
});

it('requires permission to update the parent even when field creation is allowed', function () {
    $admin = widgetFieldEditor(['createPageWidgetField']);
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.updatePageWidget');
    widgetFieldsManager($widget)->assertActionHidden(TestAction::make('create')->table());
    expect($widget->fields()->count())->toBe(0);
});

it('rejects direct relation manager access without permission to view the parent', function () {
    $admin = widgetFieldEditor();
    $widget = PageWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.viewPageWidget');
    widgetFieldsManager($widget)->assertForbidden();
});
