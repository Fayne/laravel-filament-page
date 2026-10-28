<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Database\Seeders\DemoHomepageSeeder;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageComponentField;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('creates linked Chinese and English draft homepages with editable content definitions', function () {
    $this->seed(DemoHomepageSeeder::class);
    $english = Page::where('locale', 'en')->firstOrFail();
    $chinese = Page::where('locale', 'zh_CN')->firstOrFail();

    expect(Page::count())->toBe(2)->and($english->translation_group_id)->toBe($chinese->translation_group_id)
        ->and($english->path)->toBe('/')->and($chinese->path)->toBe('/')
        ->and($english->type)->toBe('homepage')->and($chinese->type)->toBe('homepage')
        ->and($english->is_active)->toBeFalse()->and($chinese->is_active)->toBeFalse()
        ->and($english->revisions()->count())->toBe(0)->and($chinese->revisions()->count())->toBe(0)
        ->and($english->components)->toHaveCount(3)->and($chinese->components)->toHaveCount(3)
        ->and(PageComponent::count())->toBe(2)->and(PageComponentField::count())->toBe(5);
    expect(array_intersect(array_column($english->components, 'key'), array_column($chinese->components, 'key')))->toBe([]);
    expect($english->components[0]['attributes']['title'])->not->toBe($chinese->components[0]['attributes']['title']);

    $admin = Admin::create(['name' => 'Editor', 'email' => 'homepage@example.test', 'password' => 'password', 'is_active' => true]);
    $this->grantPermissions($admin, ['viewAnyPage', 'viewPage', 'updatePage']);
    Livewire::actingAs($admin, 'admin')->test(EditPage::class, ['record' => $english->id])->call('save')->assertHasNoFormErrors();
    expect($english->fresh()->components)->toBe($english->components);
});

it('preserves existing homepage data and definition edits on repeated seeding', function () {
    $this->seed(DemoHomepageSeeder::class);
    Page::where('locale', 'en')->firstOrFail()->update(['name' => 'My homepage']);
    PageComponent::firstOrFail()->update(['name' => 'My component']);
    $before = Page::orderBy('id')->get()->toArray();
    $definitions = PageComponent::orderBy('id')->get()->toArray();

    $this->seed(DemoHomepageSeeder::class);
    expect(Page::orderBy('id')->get()->toArray())->toBe($before)
        ->and(PageComponent::orderBy('id')->get()->toArray())->toBe($definitions);
});

it('does not replace an existing homepage or root page or add unused definitions', function (string $path, string $type) {
    $page = Page::create(['name' => 'Real page', 'path' => $path, 'type' => $type]);
    $this->seed(DemoHomepageSeeder::class);
    expect(Page::count())->toBe(1)->and($page->fresh()->name)->toBe('Real page')->and(PageComponent::count())->toBe(0);
})->with([['/', 'basic'], ['/home', 'homepage']]);

it('supports configured Chinese locale spelling and isolates tenants', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
    app(TenantContext::class)->run($other, function () {
        config(['filament-cms.supported_locales' => ['en' => 'English', 'zh-CN' => '中文']]);
        $this->seed(DemoHomepageSeeder::class);
        expect(Page::where('locale', 'zh-CN')->count())->toBe(1)->and(PageComponent::count())->toBe(2);
    });
    expect(Page::count())->toBe(0)->and(PageComponent::count())->toBe(0);
});

it('rejects unsupported language or homepage configurations without partial writes', function (string $setting, mixed $value) {
    config([$setting => $value]);
    expect(fn () => $this->seed(DemoHomepageSeeder::class))->toThrow(ValidationException::class);
    expect(Page::count())->toBe(0)->and(PageComponent::count())->toBe(0);
})->with([
    ['filament-cms.supported_locales', ['en' => 'English']],
    ['filament-page.fixed_components', ['homepage' => ['custom-header' => 1]]],
    ['filament-page.excluded_components', ['homepage']],
]);

it('asks before creating test data and skips it when declined or noninteractive', function () {
    $this->artisan('cms:page:init')->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'no')->assertSuccessful();
    $this->artisan('cms:page:init', ['--no-interaction' => true])->assertSuccessful();
    expect(Page::count())->toBe(0)->and(PageComponent::count())->toBe(0);
});

it('creates demo homepages only in the confirmed target tenant', function () {
    $other = Tenant::create(['name' => 'Shop', 'slug' => 'shop']);
    $this->artisan('cms:page:init', ['--tenant' => 'shop'])->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'yes')->assertSuccessful();
    expect(Page::count())->toBe(0);
    app(TenantContext::class)->run($other, fn () => expect(Page::count())->toBe(2));
});

it('prompts for a tenant when no context exists and restores the original context', function () {
    $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop']);
    $second = Tenant::create(['name' => 'Second', 'slug' => 'second']);
    $unselected = Tenant::create(['name' => 'Unselected', 'slug' => 'unselected']);
    $labels = Tenant::where('is_active', true)->orderBy('id')->get()->map(fn ($tenant) => $tenant->name.' ('.$tenant->slug.')')->all();
    Context::forget('filament-cms.tenant_id');
    $this->artisan('cms:page:init')->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'yes')
        ->expectsChoice(__('filament-page::commands.select_tenant'), ['Shop (shop)', 'Second (second)'], $labels)->assertSuccessful();
    expect(app(TenantContext::class)->id())->toBeNull();
    app(TenantContext::class)->run($tenant, fn () => expect(Page::count())->toBe(2));
    app(TenantContext::class)->run($second, fn () => expect(Page::count())->toBe(2));
    app(TenantContext::class)->run($unselected, fn () => expect(Page::count())->toBe(0));
});

it('supports single-site creation and rejects an unknown tenant', function () {
    $this->artisan('cms:page:init', ['--tenant' => 'missing'])->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'yes')->assertFailed();
    expect(Page::count())->toBe(0);
    config(['filament-cms.tenancy.enabled' => false]);
    $this->artisan('cms:page:init')->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'yes')->assertSuccessful();
    expect(Page::where('tenant_id', 0)->count())->toBe(2);
});

it('preserves conflicting existing definitions and rolls back newly created demo definitions', function () {
    $component = PageComponent::create(['name' => 'Custom component', 'slug' => 'demo-home-content']);
    $component->fields()->create(['title' => 'Custom', 'slug' => 'custom', 'resolver' => 'text']);

    expect(fn () => $this->seed(DemoHomepageSeeder::class))->toThrow(ValidationException::class);
    expect(Page::count())->toBe(0)->and(PageComponent::count())->toBe(1)
        ->and($component->fresh()->name)->toBe('Custom component')->and($component->fields()->count())->toBe(1);
});

it('keeps draft homepages private and exposes language-specific content only when enabled', function () {
    config(['filament-cms.multilingual_enabled' => true]);
    $this->seed(DemoHomepageSeeder::class);
    $this->getJson('/modules/pages/detail?locale=en')->assertNotFound();
    Page::all()->each->update(['is_active' => true]);

    $this->getJson('/modules/pages/detail?locale=en')->assertOk()->assertJsonPath('title', 'Welcome to our demo store')
        ->assertJsonPath('components.0.layout', 'demo-home-hero');
    $this->getJson('/modules/pages/detail?locale=zh_CN')->assertOk()->assertJsonPath('title', '欢迎来到演示商城');
});
