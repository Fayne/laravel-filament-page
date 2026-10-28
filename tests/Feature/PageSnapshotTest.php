<?php

use Fayne\CMS\Events\PublicationSucceeded;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Models\TenantDomain;
use Fayne\CMS\Publishing\PublicationLifecycle;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Illuminate\Support\Facades\Http;

it('freezes page projections independently of later edits', function () {
    $record = Page::create(['name' => 'Original',
        'path' => '/', 'components' => [], 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    expect($manifest->entries['page'])->toHaveCount(1);
    $before = $manifest->entries;
    $record->update(['name' => 'Changed']);
    expect(app(ReleaseManifestService::class)->freeze($release)->entries)->toBe($before);
});

it('limits release snapshots to the requested locale', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh-CN' => '中文']]);
    Page::create(['name' => 'English', 'path' => '/', 'locale' => 'en', 'is_active' => true]);
    Page::create(['name' => '中文', 'path' => '/', 'locale' => 'zh-CN', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'deployment_contents' => ['locale' => 'zh-CN'], 'status' => 0]);
    $entries = app(ReleaseManifestService::class)->freeze($release)->entries['page'];
    expect($entries)->toHaveCount(1)->and($entries[0]['locale'])->toBe('zh-CN');
});

it('serves frozen release pages and rejects missing manifests', function () {
    Page::create(['name' => 'Frozen', 'path' => '/', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    config(['filament-cms.deployment.build_api_token' => 'build-secret']);
    $release->forceFill(['tenant_id' => app(TenantContext::class)->requireId()])->save();
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $this->withToken('build-secret');
    $this->getJson('/headless/releases/'.$manifest->release_id.'/pages?path=/')->assertOk()->assertJsonPath('data.0.projection.name', 'Frozen');
    $this->getJson('/headless/releases/missing/pages')->assertNotFound();
});

it('freezes only the default language when release locale is omitted', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'fr' => 'French']]);
    Page::create(['name' => 'English', 'path' => '/', 'locale' => 'en', 'is_active' => true]);
    Page::create(['name' => 'French', 'path' => '/', 'locale' => 'fr', 'is_active' => true]);
    app()->setLocale('fr');
    $release = DeploymentRecord::create(['title' => 'Default language', 'deployment_type' => 'full', 'status' => 0]);
    expect(app(ReleaseManifestService::class)->freeze($release)->entries['page'])->toHaveCount(1)
        ->and(app(ReleaseManifestService::class)->freeze($release)->entries['page'][0]['locale'])->toBe('en');
});

it('freezes from the explicit release region instead of the administrator session', function () {
    config(['database.connections.west' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'filament-cms.regions' => [['id' => 'us', 'database' => 'west']]]);
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_20_100000_create_page_content_tables.php')->up();
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_22_100000_add_page_translation_groups.php')->up();
    Page::on('west')->create(['name' => 'West page', 'path' => '/', 'locale' => 'en', 'is_active' => true]);
    session(['filament-cms.region_id' => 'unknown']);
    $release = DeploymentRecord::create(['title' => 'Regional release', 'deployment_type' => 'full', 'deployment_contents' => ['locale' => 'en', 'region' => 'us'], 'status' => 0]);
    $entries = app(ReleaseManifestService::class)->freeze($release)->entries['page'];
    expect($entries)->toHaveCount(1)->and($entries[0]['projection']['name'])->toBe('West page');
});

it('freezes current candidate and raw content instead of its prior public revision', function () {
    $page = Page::create(['name' => 'Current', 'path' => '/new', 'is_active' => true, 'extra_data' => ['updated_at' => 'legitimate']]);
    $page->revisions()->create([...$page->contentSnapshot(), 'name' => 'Old', 'path' => '/old', 'is_stash' => false, 'released_at' => now()]);
    $release = DeploymentRecord::create(['title' => 'Candidate', 'deployment_type' => 'full', 'status' => 0]);
    $entry = app(ReleaseManifestService::class)->freeze($release)->entries['page'][0];
    expect($entry['projection']['name'])->toBe('Current')->and($entry['path'])->toBe('/new')
        ->and($entry['content_snapshot']['extra_data'])->toBe(['updated_at' => 'legitimate'])
        ->and($entry['comparison_projection'])->not->toHaveKey('updated_at');
});

it('promotes frozen raw content once across repeated successful events', function () {
    $raw = ['media' => ['id' => 7, 'updated_at' => 'nested data']];
    $page = Page::create(['name' => 'Frozen', 'path' => '/', 'is_active' => true, 'extra_data' => $raw, 'changed' => true]);
    $release = DeploymentRecord::create(['title' => 'Candidate', 'deployment_type' => 'full', 'status' => 0]);
    app(ReleaseManifestService::class)->freeze($release);
    $page->update(['name' => 'Later']);
    event(new PublicationSucceeded($release));
    event(new PublicationSucceeded($release));
    expect($page->revisions()->count())->toBe(1)
        ->and($page->currentPublishedRevision->name)->toBe('Frozen')
        ->and($page->currentPublishedRevision->extra_data)->toBe($raw)
        ->and($page->fresh()->changed)->toBeTrue();
});

it('authenticates lifecycle page retrieval and hides raw promotion metadata', function () {
    config(['filament-cms.deployment.build_api_token' => 'build-secret', 'filament-cms.settings.fields' => ['title' => ['default' => 'Frozen setting']], 'filament-cms.settings.public' => ['title']]);
    Page::create(['name' => 'Frozen', 'path' => '/', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    $release->forceFill(['tenant_id' => app(TenantContext::class)->requireId(), 'scope_key' => 'test'])->save();
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $url = '/headless/releases/'.$manifest->release_id.'/pages';
    $this->getJson($url)->assertForbidden();
    $this->withToken('build-secret')->getJson($url)->assertOk()->assertJsonMissingPath('data.0.content_snapshot');
    $manifest->update(['status' => 'succeeded']);
    $this->getJson($url)->assertOk();
    config(['filament-cms.settings.fields.title.default' => 'Later setting']);
    $this->getJson('/headless/releases/'.$manifest->release_id.'/global-settings')->assertOk()->assertJsonPath('data.title', 'Frozen setting');
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
    TenantDomain::where('host', 'localhost')->update(['tenant_id' => $other->id]);
    $this->getJson($url)->assertNotFound();
});

it('freezes public settings and definition changes through the shared registry', function () {
    config(['filament-cms.settings.fields' => ['public_title' => ['default' => 'First'], 'secret' => ['default' => 'private']], 'filament-cms.settings.public' => ['public_title']]);
    $component = PageComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $field = $component->fields()->create(['title' => 'Heading', 'slug' => 'heading', 'resolver' => 'text', 'options' => ['nested' => ['updated_at' => 'keep']]]);
    $release = DeploymentRecord::create(['title' => 'First', 'deployment_type' => 'full', 'status' => 0]);
    $entries = app(ReleaseManifestService::class)->freeze($release)->entries;
    expect($entries['global_settings'][0]['projection']['values'])->toBe(['public_title' => 'First']);
    $field->update(['weight' => 2]);
    $next = DeploymentRecord::create(['title' => 'Next', 'deployment_type' => 'full', 'status' => 0]);
    $new = app(ReleaseManifestService::class)->freeze($next)->entries;
    expect($new['global_page_definitions'])->not->toBe($entries['global_page_definitions'])
        ->and($new['global_page_definitions'][0]['projection']['component'][0]['fields'][0]['options']['nested']['updated_at'])->toBe('keep');
});

it('promotes only frozen regional pages and keeps stash and inactive drafts out', function () {
    config(['database.connections.release_region' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'filament-cms.regions' => [['id' => 'target', 'database' => 'release_region']]]);
    foreach (['2026_10_20_100000_create_page_content_tables.php', '2026_10_22_100000_add_page_translation_groups.php', '2026_10_27_120000_add_page_revision_publication_manifest_key.php'] as $migration) {
        (require dirname(__DIR__, 2).'/database/migrations/'.$migration)->up();
    }
    $default = Page::create(['name' => 'Other region', 'path' => '/', 'is_active' => true, 'changed' => true]);
    $raw = [['layout' => 'hero', 'key' => 'nested', 'attributes' => ['image' => ['disk' => 'public', 'path' => 'x.jpg']]]];
    $page = Page::on('release_region')->create(['name' => 'Target', 'path' => '/', 'is_active' => true, 'changed' => true, 'components' => $raw]);
    $page->revisions()->create([...$page->contentSnapshot(), 'name' => 'Stash', 'is_stash' => true]);
    Page::on('release_region')->create(['name' => 'Draft', 'path' => '/draft', 'is_active' => false]);
    $release = DeploymentRecord::create(['title' => 'Regional', 'deployment_type' => 'full', 'deployment_contents' => ['region' => 'target'], 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    expect($manifest->entries['page'])->toHaveCount(1);
    event(new PublicationSucceeded($release));
    event(new PublicationSucceeded($release));
    expect($page->revisions()->where('is_stash', false)->count())->toBe(1)
        ->and($page->currentPublishedRevision->components)->toBe($raw)
        ->and($page->fresh()->changed)->toBeFalse()
        ->and($default->fresh()->changed)->toBeTrue();
});

it('promotes pages only after lifecycle success and handles duplicate callbacks', function () {
    config(['filament-cms.multilingual_enabled' => false, 'filament-cms.supported_locales' => ['en' => 'English'], 'filament-cms.deployment.frontend_driver' => 'astro', 'filament-cms.deployment.build_webhook_url' => 'https://build.test', 'filament-cms.deployment.webhook_secret' => 'secret']);
    Http::fake(['*' => Http::response([], 202)]);
    $page = Page::create(['name' => 'Candidate', 'path' => '/renamed', 'is_active' => true, 'changed' => true]);
    $page->revisions()->create([...$page->contentSnapshot(), 'name' => 'Previous', 'path' => '/previous', 'released_at' => now(), 'is_stash' => false]);
    $lifecycle = app(PublicationLifecycle::class);
    $failed = $lifecycle->start(['locale' => 'en']);
    $lifecycle->complete($failed, $failed->releaseManifest->attempt_id, false);
    expect($page->revisions()->count())->toBe(1)->and($page->fresh()->changed)->toBeTrue();
    $success = $lifecycle->start(['locale' => 'en']);
    $lifecycle->complete($success, $success->releaseManifest->attempt_id, true);
    $lifecycle->complete($success, $success->releaseManifest->attempt_id, true);
    expect($page->revisions()->count())->toBe(2)->and($page->fresh()->changed)->toBeFalse()
        ->and($page->fresh()->publishedProjection()['name'])->toBe('Candidate')
        ->and($page->fresh()->publishedProjection()['path'])->toBe('/renamed');
});
