<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Filament\Resources\PageResource\Pages\CreatePage;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Services\PageContentValidator;
use Illuminate\Validation\ValidationException;

it('rejects duplicate paths and page types exceeding their configured limit', function () {
    Page::create(['name' => 'Home', 'path' => '/', 'type' => 'homepage', 'locale' => 'en']);
    $validator = new PageContentValidator;
    foreach ([['path' => '/', 'type' => 'basic'], ['path' => '/other', 'type' => 'homepage']] as $data) {
        try {
            $validator->validate([...$data, 'locale' => 'en', 'components' => [], 'fixed_components' => []]);
            $this->fail('Invalid page accepted.');
        } catch (ValidationException) {
            expect(Page::count())->toBe(1);
        }
    }
});

it('checks fixed component quantity and order on the server', function () {
    config(['filament-page.fixed_components.basic' => ['hero' => 1, 'footer' => 1]]);
    $validator = new PageContentValidator;
    $invalid = [['layout' => 'footer', 'key' => 'b', 'attributes' => []], ['layout' => 'hero', 'key' => 'a', 'attributes' => []]];
    $validator->validate(['path' => '/', 'type' => 'basic', 'locale' => 'en', 'components' => [], 'fixed_components' => $invalid]);
})->throws(ValidationException::class);

it('preserves fixed component keys in a valid save', function () {
    config(['filament-page.fixed_components.basic' => ['hero' => 1]]);
    $data = ['path' => '/', 'type' => 'basic', 'locale' => 'en', 'components' => [], 'fixed_components' => [['layout' => 'hero', 'key' => 'fixed', 'attributes' => []]]];
    (new PageContentValidator)->validate($data);
    expect($data['fixed_components'][0]['key'])->toBe('fixed');
});

it('seeds configured fixed components once when creating a page', function () {
    config(['filament-page.fixed_components.basic' => ['hero' => 1]]);
    PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'fixed@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyPage', 'createPage'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire\Livewire::test(CreatePage::class)
        ->fillForm(['name' => 'Home', 'path' => '/'])->call('create')->assertHasNoFormErrors();
    $page = Page::first();
    expect($page->fixed_components)->toHaveCount(1)->and($page->fixed_components[0]['layout'])->toBe('hero')
        ->and($page->fixed_components[0]['key'])->toBeString()->not->toBeEmpty();
});

it('rejects ordinary components for page types excluded by configuration', function () {
    config(['filament-page.excluded_components' => ['homepage']]);
    (new PageContentValidator)->validate(['path' => '/', 'type' => 'homepage', 'locale' => 'en', 'components' => [['layout' => 'hero', 'key' => 'a', 'attributes' => []]], 'fixed_components' => []]);
})->throws(ValidationException::class);
