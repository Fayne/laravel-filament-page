<?php

use Fayne\CMS\Http\Middleware\ResolvePublicTenant;
use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Models\TenantDomain;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Support\PagePreviewSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

it('serves a valid draft preview in a fresh request without inherited tenant context', function () {
    $page = Page::create(['name' => 'Audit draft', 'path' => '/audit-draft', 'is_active' => false, 'components' => []]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('/headless/preview/draft?page=/audit-draft', ['Authorization' => 'Bearer '.$token])->assertOk();
});

it('serves a valid preview without an inherited authorization scope', function () {
    $page = Page::create(['name' => 'Audit draft', 'path' => '/audit-draft', 'components' => []]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('/headless/preview/draft?page=/audit-draft', ['Authorization' => 'Bearer '.$token])->assertOk();
});

it('resolves a registered hostname for the public frontend route', function () {
    Page::create(['name' => 'Audit public', 'path' => '/audit-public', 'is_active' => true, 'components' => []]);
    TenantDomain::create(['tenant_id' => app(TenantContext::class)->requireId(), 'host' => 'audit-public.test', 'is_active' => true]);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('https://audit-public.test/modules/pages/v2/slugs')->assertOk();
});

it('keeps preview projection inside its tenant and restores the caller context', function () {
    $page = Page::create(['name' => 'Scoped draft', 'path' => '/scoped', 'components' => []]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    $other = Tenant::create(['name' => 'Other', 'slug' => 'preview-other', 'is_active' => true]);
    app(TenantContext::class)->set($other);
    app(AuthorizationContext::class)->activate($other->id);
    $this->getJson('/headless/preview/draft?page=/scoped', ['Authorization' => 'Bearer '.$token])
        ->assertOk()->assertJsonPath('data.projection.name', 'Scoped draft')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(app(TenantContext::class)->id())->toBe($other->id)
        ->and(app(AuthorizationContext::class)->id())->toBe($other->id);
    $admin->tenants()->detach($page->tenant_id);
    $this->getJson('/headless/preview/draft?page=/scoped', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
});

it('rejects inactive tenants when resolving a preview token', function () {
    $page = Page::create(['name' => 'Draft', 'path' => '/inactive']);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    Tenant::findOrFail($page->tenant_id)->update(['is_active' => false]);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('/headless/preview/draft?page=/inactive', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
});

it('rejects unmapped domains even with inherited tenant context', function () {
    Page::create(['name' => 'Private tenant', 'path' => '/hidden', 'is_active' => true]);
    $this->getJson('https://unknown.test/modules/pages/v2/slugs')->assertNotFound();
});

it('rejects unowned legacy releases even with a build credential', function () {
    config(['filament-cms.deployment.build_api_token' => 'build-secret']);
    $release = DeploymentRecord::create(['title' => 'Legacy', 'deployment_type' => 'full', 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $url = '/headless/releases/'.$manifest->release_id.'/pages';
    $this->getJson($url)->assertForbidden();
    $this->getJson($url, ['Authorization' => 'Bearer build-secret'])->assertNotFound();
});

it('restores preview scopes even if content rendering throws', function () {
    $page = Page::create(['name' => 'Draft', 'path' => '/render-failure']);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $sessions = app(PagePreviewSession::class);
    $token = $sessions->issue($page, $admin);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    expect(fn () => $sessions->run($token, '/render-failure', fn () => throw new RuntimeException('render failure')))
        ->toThrow(RuntimeException::class, 'render failure');
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
});

it('previews single site content without inherited authorization scope', function () {
    config(['filament-cms.tenancy.enabled' => false]);
    app(AuthorizationContext::class)->activate(0);
    $page = Page::create(['name' => 'Single site', 'path' => '/single']);
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo(test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('/headless/preview/draft?page=/single', ['Authorization' => 'Bearer '.$token])
        ->assertOk()->assertJsonPath('data.projection.name', 'Single site');
    expect(app(AuthorizationContext::class)->id())->toBeNull();
});

it('rejects disabled public domains and restores context on downstream errors', function () {
    $tenantId = app(TenantContext::class)->requireId();
    TenantDomain::create(['tenant_id' => $tenantId, 'host' => 'disabled.test', 'is_active' => false]);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('https://disabled.test/modules/pages/slugs')->assertNotFound();
    $middleware = app(ResolvePublicTenant::class);
    expect(fn () => $middleware->handle(Request::create('https://localhost/modules/pages/slugs'), fn () => throw new RuntimeException('downstream failure')))
        ->toThrow(RuntimeException::class, 'downstream failure');
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
});

it('previews the token regional connection rather than another database with the same page id', function () {
    config(['database.connections.preview_region' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'filament-cms.regions' => [['id' => 'preview-region', 'database' => 'preview_region']]]);
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_20_100000_create_page_content_tables.php')->up();
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_22_100000_add_page_translation_groups.php')->up();
    $default = Page::create(['name' => 'Default database', 'path' => '/regional-preview']);
    $regional = Page::on('preview_region')->create(['name' => 'Regional draft', 'path' => '/regional-preview']);
    $component = PageComponent::on('preview_region')->create(['name' => 'Regional hero', 'slug' => 'regional-hero']);
    $component->fields()->create(['title' => 'Heading', 'slug' => 'heading', 'resolver' => 'text']);
    $regional->update(['components' => [['layout' => 'regional-hero', 'key' => 'regional-block', 'attributes' => ['heading' => 'Regional content']]]]);
    expect($regional->id)->toBe($default->id);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($regional, $admin);
    session(['filament-cms.region_id' => 'unknown']);
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('/headless/preview/draft?page=/regional-preview', ['Authorization' => 'Bearer '.$token])
        ->assertOk()->assertJsonPath('data.projection.name', 'Regional draft')
        ->assertJsonPath('data.projection.components.0.attributes.heading', 'Regional content');
    expect(session('filament-cms.region_id'))->toBe('unknown');
});
