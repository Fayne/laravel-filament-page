<?php

use Fayne\Page\Models\Page;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('initializes permissions twice without deleting content or custom assignments', function () {
    $permission = Permission::findOrCreate('custom-permission', 'admin');
    $role = Role::findOrCreate('custom-role', 'admin');
    $role->givePermissionTo($permission);
    $record = Page::create(['name' => 'Keep', 'path' => '/']);
    $this->artisan('cms:page:init')->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'no')->assertSuccessful();
    $count = Permission::count();
    $this->artisan('cms:page:init')->expectsConfirmation(__('filament-page::commands.confirm_demo'), 'no')->assertSuccessful();
    expect(Permission::count())->toBe($count)->and($role->fresh()->hasPermissionTo('custom-permission', 'admin'))->toBeTrue()
        ->and($record->fresh()->name)->toBe('Keep')->and(Role::findByName('tenant-manager', 'admin')->hasPermissionTo('tenant.updatePage', 'admin'))->toBeTrue();
});
