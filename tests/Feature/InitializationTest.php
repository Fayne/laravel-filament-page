<?php

use Fayne\Page\Models\Page;

it('installs without asking for or creating demo data', function () {
    $this->artisan('cms:page:install')->assertSuccessful();
    expect(Page::withoutGlobalScopes()->count())->toBe(0);
});

it('requires a target for unattended multi tenant demos', function () {
    \Illuminate\Support\Facades\Context::forget('filament-cms.tenant_id');
    $this->artisan('cms:page:install', ['--demo' => true, '--no-interaction' => true])->assertFailed();
    expect(Page::withoutGlobalScopes()->count())->toBe(0);
});

it('registers its own permissions without unrelated modules and preserves restricted defaults', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'tenant.updatePage')->exists())->toBeTrue()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createMenu')->exists())->toBeFalse()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createEvent')->exists())->toBeFalse()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createStore')->exists())->toBeFalse();
    $editor = \Spatie\Permission\Models\Role::where('name', 'content-editor')->where('authorization_scope_id', 0)->firstOrFail();
    $viewer = \Spatie\Permission\Models\Role::where('name', 'tenant-viewer')->where('authorization_scope_id', 0)->firstOrFail();
    $manager = \Spatie\Permission\Models\Role::where('name', 'tenant-manager')->where('authorization_scope_id', 0)->firstOrFail();
    expect($editor->hasPermissionTo('tenant.updatePage'))->toBeTrue()
        ->and($editor->hasPermissionTo('tenant.executeContentHttpPage'))->toBeFalse()
        ->and($editor->hasPermissionTo('tenant.viewSensitiveContentPage'))->toBeTrue()
        ->and($viewer->hasPermissionTo('tenant.viewAnyPage'))->toBeTrue()
        ->and($viewer->hasPermissionTo('tenant.viewSensitiveContentPage'))->toBeFalse()
        ->and($manager->hasPermissionTo('tenant.executeContentHttpPage'))->toBeTrue();
});

it('publishes its own config without replacing host customizations', function () {
    $directory = sys_get_temp_dir() . '/page-config-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $original = app()->configPath();
    app()->useConfigPath($directory);
    try {
        (new ReflectionMethod(\Illuminate\Support\ServiceProvider::class, 'publishes'))->invoke(app()->getProvider(\Fayne\Page\PageServiceProvider::class), [
            dirname(__DIR__, 2) . '/config/filament-page.php' => $directory . '/filament-page.php',
        ], 'filament-page-config');
        $this->artisan('cms:page:install', ['--no-interaction' => true])->assertSuccessful();
        expect(is_file($directory . '/filament-page.php'))->toBeTrue();
        file_put_contents($directory . '/filament-page.php', '<?php return ["host_setting" => true];');
        $this->artisan('cms:page:install', ['--no-interaction' => true])->assertSuccessful();
        expect(require $directory . '/filament-page.php')->toBe(['host_setting' => true]);
    } finally {
        app()->useConfigPath($original);
        \Illuminate\Support\Facades\File::deleteDirectory($directory);
    }
});
