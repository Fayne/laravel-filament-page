<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageComponentFieldResource\Pages\CreatePageComponentField;
use Fayne\Page\Filament\Resources\PageComponentResource\Pages\EditPageComponent;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageComponentField;
use Fayne\Page\Support\ComponentPreview;
use Fayne\PageBuilder\Filament\Forms\Components\FieldOptionsInput;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores JSON field options and validation rules through the definition editor', function () {
    $owner = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'definition@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponentField', 'createPageComponentField'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreatePageComponentField::class)->fillForm(['page_component_id' => $owner->id, 'title' => 'Title', 'slug' => 'headline', 'resolver' => 'text', 'options' => '{"max_length": 30}', 'rules' => '{"rules": ["required"]}', 'weight' => 1])->call('create')->assertHasNoFormErrors();
    expect(PageComponentField::first()->options)->toBe(['max_length' => 30])->and(PageComponentField::first()->rules)->toBe(['rules' => ['required']]);
});

it('rejects scalar JSON options before they can break dynamic schema loading', function () {
    $owner = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'scalar@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponentField', 'createPageComponentField'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreatePageComponentField::class)->fillForm(['page_component_id' => $owner->id, 'title' => 'Title', 'resolver' => 'text', 'options' => 'false', 'rules' => '[]'])->call('create')->assertHasFormErrors(['options']);
    expect(PageComponentField::count())->toBe(0);
});

it('uploads component previews while preserving legacy asset metadata on unchanged saves', function () {
    Storage::fake('public');
    $admin = Admin::create(['name' => 'Preview editor', 'email' => 'preview@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponent', 'viewPageComponent', 'updatePageComponent', 'uploadMedia'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $asset = ['disk' => 'public', 'path' => 'previews/old.png', 'name' => 'old.png'];
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero', 'mockup' => $asset]);
    $editor = Livewire::test(EditPageComponent::class, ['record' => $component->id]);
    $editor->assertFormFieldExists('mockup')->call('save')->assertHasNoFormErrors();
    expect($component->fresh()->mockup)->toBe($asset);
    $editor->fillForm(['mockup' => [UploadedFile::fake()->image('preview.png')]])->call('save')->assertHasNoFormErrors();
    $updated = $component->fresh()->mockup;
    expect($updated['disk'])->toBe('public')->and($updated['path'])->toStartWith('component-previews/');
    Storage::disk('public')->assertExists($updated['path']);
    expect(ComponentPreview::url($updated))->toBe(Storage::disk('public')->url($updated['path']));
});

it('rejects preview uploads without media permission and rejects unrelated stored paths', function () {
    Storage::fake('public');
    $admin = Admin::create(['name' => 'Limited editor', 'email' => 'limited-preview@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageComponent', 'viewPageComponent', 'updatePageComponent'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $page = EditPageComponent::class;
    Livewire::test($page, ['record' => $component->id])->fillForm(['mockup' => [UploadedFile::fake()->image('denied.png')]])->call('save')->assertForbidden();
    Storage::disk('public')->put('unrelated.png', 'private');
    Livewire::test($page, ['record' => $component->id])->fillForm(['mockup' => ['unrelated.png']])->call('save')->assertHasFormErrors(['mockup']);
    expect($component->fresh()->mockup)->toBeNull();
    test()->grantPermissions($admin, test()->permission('uploadMedia'));
    Livewire::test($page, ['record' => $component->id])->fillForm(['mockup' => [UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')]])->call('save')->assertHasFormErrors(['mockup']);
});

it('uses friendly types and structured rules in both page field resources', function (string $kind, string $foreignKey) {
    $model = 'Fayne\\Page\\Models\\Page'.$kind;
    $resource = 'Fayne\\Page\\Filament\\Resources\\Page'.$kind.'FieldResource\\Pages\\CreatePage'.$kind.'Field';
    $owner = $model::create(['name' => 'Friendly', 'slug' => 'friendly']);
    $admin = Admin::create(['name' => 'Field editor', 'email' => 'friendly@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPageWidget', 'viewAnyPage'.$kind.'Field', 'createPage'.$kind.'Field'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $widgetModel = 'Fayne\\Page\\Models\\PageWidget';
    $widget = $widgetModel::firstOrCreate(['slug' => 'allowed'], ['name' => 'Allowed widget']);
    Livewire::test($resource)
        ->assertFormFieldExists('options', fn ($field) => ($field->getOptionDefinitions()['widget']['widgets'][2][$widget->id] ?? null) === 'Allowed widget')
        ->assertSee('基本设置')->assertSee('字段选项')->assertSee('验证规则')
        ->assertFormFieldExists('options', fn ($field) => $field instanceof FieldOptionsInput)
        ->assertFormFieldExists('resolver', fn ($field) => $field instanceof Select)
        ->fillForm([$foreignKey => $owner->id, 'title' => 'Photo', 'slug' => 'photo', 'resolver' => 'image', 'options' => '{}', 'rules' => ['rules' => ['required', 'max:2048']]])->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->sole()->resolver)->toBe('file')->and($owner->fields()->sole()->options)->toBe(['is_image' => true])->and($owner->fields()->sole()->rules)->toBe(['rules' => ['required', 'max:2048']]);
})->with([['Component', 'page_component_id'], ['Widget', 'nova_page_widget_id']]);
