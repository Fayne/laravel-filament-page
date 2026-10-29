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
