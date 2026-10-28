<?php

namespace Fayne\Page\Tests;

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Models\TenantDomain;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\Authorization\PermissionCatalog;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Filament\PagePlugin;
use Fayne\Page\PageServiceProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\Models\Permission;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return [PageServiceProvider::class, TestPanelProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:5qJq8O4L7PJkq4+9eX6xqz5VxA8w3iH+0mZkR4j7r7U=');
        $app['config']->set('auth.defaults.guard', 'admin');
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
        $app['config']->set('filament-cms.tenancy.enabled', true);
        $app['config']->set('app.locale', 'zh_CN');
        $app['config']->set('filament-cms.default_locale', 'en');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        PermissionCatalog::seed();
        $tenant = Tenant::create(['name' => 'Test tenant', 'slug' => 'test-tenant', 'is_active' => true]);
        TenantDomain::create(['tenant_id' => $tenant->id, 'host' => 'localhost', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        app(AuthorizationContext::class)->activate($tenant->id);
    }

    public function permission(string $ability): Permission
    {
        return Permission::query()->where('guard_name', 'admin')->where('name', str_starts_with($ability, 'tenant.') ? $ability : 'tenant.'.$ability)->firstOrFail();
    }

    public function grantPermissions(Admin $admin, mixed $permissions): void
    {
        app(AuthorizationContext::class)->activate((int) app(TenantContext::class)->requireId());
        $admin->tenants()->syncWithoutDetaching([app(TenantContext::class)->requireId() => ['is_active' => true]]);
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $admin->givePermissionTo(array_map(fn ($permission) => is_string($permission) ? $this->permission($permission) : $permission, $permissions));
    }
}

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->authGuard('admin')->login()->plugins([PagePlugin::make()]);
    }
}
