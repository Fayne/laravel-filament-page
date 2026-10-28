<?php

use Fayne\CMS\Events\PublicationSucceeded;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Publishing\WholeSiteSnapshot;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Fayne\Page\Support\PromoteReleasedPages;
use Fayne\Page\Tests\TestCase;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WholeSitePageSnapshotTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        foreach (['cn', 'us'] as $region) {
            $app['config']->set('database.connections.'.$region, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        }
        $app['config']->set('filament-cms.regions', [['id' => 'cn', 'database' => 'cn'], ['id' => 'us', 'database' => 'us']]);
        $app['config']->set('filament-cms.multilingual_enabled', true);
        $app['config']->set('filament-cms.supported_locales', ['en' => 'English', 'zh_CN' => 'Chinese']);
        $app['config']->set('filament-cms.publication_routing', ['region_mode' => 'host', 'regions' => [['id' => 'cn', 'host' => 'cn.test'], ['id' => 'us', 'host' => 'us.test']], 'language_prefixes' => ['en' => '/en', 'zh_CN' => '/zh']]);
    }

    private function seedPages(): DeploymentRecord
    {
        $tenant = Tenant::findOrFail(app(TenantContext::class)->requireId());
        foreach (['cn', 'us'] as $region) {
            $tenant->domains()->create(['host' => $region.'.test', 'is_active' => true]);
            $group = (string) Str::uuid();
            foreach (['en', 'zh_CN'] as $locale) {
                Page::on($region)->create(['name' => $region.' '.$locale, 'title' => $locale, 'path' => '/about', 'locale' => $locale, 'is_active' => true, 'translation_group_id' => $group]);
            }
        }

        return DeploymentRecord::create(['title' => 'Whole site', 'deployment_type' => 'frontend', 'deployment_contents' => ['publication_mode' => 'whole_site']]);
    }

    public function test_reads_all_real_region_databases_and_freezes_translation_groups_without_id_guesses(): void
    {
        $release = $this->seedPages();
        $this->assertFalse(Schema::hasTable('nova_pages'));
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        $rows = $manifest->entries['page'];
        $this->assertCount(4, $rows);
        $this->assertSame('1', $rows[0]['entity_id']);
        $this->assertSame('1', $rows[2]['entity_id']);
        $this->assertSame('cn en', $rows[0]['projection']['name']);
        $this->assertSame('us en', $rows[2]['projection']['name']);
        $this->assertSame(['https://cn.test/en/about', 'https://cn.test/zh/about'], array_column($rows[0]['translations'], 'url'));
        $this->assertSame('https://us.test/zh/about', $rows[3]['url']);
        $this->assertArrayNotHasKey('translation_group_id', json_decode($manifest->public_payload, true)['entries']['page'][0]);
    }

    public function test_does_not_guess_missing_translations_from_overlapping_ids(): void
    {
        $release = $this->seedPages();
        Page::on('cn')->where('locale', 'zh_CN')->delete();
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertCount(1, $rows[0]['translations']);
        $this->assertSame('en', $rows[0]['translations'][0]['locale']);
    }

    public function test_keeps_existing_language_paths_when_no_additional_prefix_is_configured(): void
    {
        $release = $this->seedPages();
        foreach (['cn', 'us'] as $connection) {
            foreach (Page::on($connection)->get() as $page) {
                $page->forceFill(['path' => '/'.$page->locale.'/about'])->save();
            }
        }
        config(['filament-cms.publication_routing.language_prefixes' => []]);
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertSame('/en/about', $rows[0]['path']);
        $this->assertSame('/zh_CN/about', $rows[1]['path']);
        $this->assertSame('/en/about', Page::on('cn')->findOrFail(1)->path);
    }

    public function test_capture_always_uses_whole_site_provider_metadata_for_comparison_previews(): void
    {
        $release = $this->seedPages();
        $release->deployment_contents = ['site_id' => 'preview'];
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertCount(2, $rows[0]['translations']);
    }

    public function test_aborts_freeze_when_one_region_database_is_offline(): void
    {
        $release = $this->seedPages();
        config(['database.connections.us.database' => '/missing/path/region.sqlite']);
        DB::purge('us');
        try {
            app(ReleaseManifestService::class)->freeze($release);
            $this->fail('Unavailable regional connection must fail the complete freeze.');
        } catch (SQLiteDatabaseDoesNotExistException $exception) {
            $this->assertSame(0, $release->releaseManifest()->count());
        }
    }

    public function test_promotes_each_raw_source_only_once_when_two_regions_share_a_connection(): void
    {
        $release = $this->seedPages();
        config(['filament-cms.regions' => [['id' => 'cn', 'database' => 'cn'], ['id' => 'us', 'database' => 'cn']]]);
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        $release->setRelation('releaseManifest', $manifest);
        DB::connection('cn')->enableQueryLog();
        app(PromoteReleasedPages::class)->handle(new PublicationSucceeded($release));
        $updates = collect(DB::connection('cn')->getQueryLog())->filter(fn ($query) => str_starts_with($query['query'], 'select * from "nova_pages"'));
        $this->assertCount(2, $updates);
        $this->assertSame(2, DB::connection('cn')->table('nova_page_revisions')->count());
    }
}
