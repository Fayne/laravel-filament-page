<?php

namespace Fayne\Page\Tests\Feature;

require_once __DIR__.'/../Fixtures/ResourceExtensions.php';

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Filament\Resources\PageComponentResource\Pages\EditPageComponent;
use Fayne\Page\Filament\Resources\PageComponentResource\RelationManagers\FieldsRelationManager;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\CreatePage;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Models\Page;
use Fayne\Page\Services\PageTemplateService;
use Fayne\Page\Support\PagePreviewSession;
use Fayne\Page\Tests\Fixtures\ExtensionTestCase;
use Fayne\Page\Tests\Fixtures\HostPage;
use Fayne\Page\Tests\Fixtures\HostPageComponent;
use Fayne\Page\Tests\Fixtures\HostPageComponentFieldResource;
use Fayne\Page\Tests\Fixtures\HostPageResource;
use Fayne\Page\Tests\Fixtures\HostPageTemplate;
use Fayne\Page\Tests\Fixtures\HostPageTemplateResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\ResourceConfiguration;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

class ResourceExtensionTest extends ExtensionTestCase
{
    private function editor(): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $this->grantPermissions($admin, Permission::where('name', 'like', 'tenant.%')->get()->all());
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    public function test_plugin_and_inherited_pages_resolve_every_configured_resource(): void
    {
        $registered = Filament::getPanel('admin')->getResources();
        foreach (config('filament-page.resources') as $key => $resource) {
            $this->assertContains($resource, $registered);
            $this->assertSame(config('filament-page.models.'.$key), $resource::getModel());
            $this->assertSame('host-'.str_replace('_', '-', $key).'s', $resource::getSlug());
            foreach ($resource::getRelations() as $manager) {
                $this->assertContains($manager::getRelatedResource(), config('filament-page.resources'));
            }
            foreach ($resource::getPages() as $page) {
                $this->assertSame($resource, $page->getPage()::getResource());
            }
        }
        $this->assertNotContains(PageResource::class, $registered);
    }

    public function test_slug_fallback_preserves_native_host_resource_slug(): void
    {
        config(['filament-page.resource_slugs.page' => null]);
        $this->assertSame('native-pages', HostPageResource::getSlug());
        config(['filament-page.resource_slugs.page' => 'configured-pages']);
        $this->assertSame('configured-pages', HostPageResource::getSlug());
    }

    public function test_builtin_create_and_edit_use_host_form_and_model(): void
    {
        $this->editor();
        $page = Livewire::test(CreatePage::class);
        $page->fillForm(['name' => 'Host record', 'path' => '/host', 'type' => 'basic', 'components' => [], 'fixed_components' => [], 'locale' => 'en', 'is_active' => false, 'extra_data' => ['host_note' => 'Created by host']])
            ->call('create')->assertHasNoFormErrors();
        $record = HostPage::where('name', 'Host record')->firstOrFail();
        $this->assertSame('Created by host', $record->extra_data['host_note']);
        Livewire::test(EditPage::class, ['record' => $record->id])
            ->assertFormSet(['extra_data.host_note' => 'Created by host'])
            ->fillForm(['name' => 'Edited by host'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edited by host', $record->fresh()->name);
        $this->get(HostPageResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSee('Host page editor');
    }

    public function test_configured_related_field_resource_is_used_for_creation_links(): void
    {
        $this->editor();
        $component = HostPageComponent::create(['name' => 'Hero', 'slug' => 'host-hero']);
        $manager = FieldsRelationManager::class;
        $fieldResource = HostPageComponentFieldResource::class;
        $this->assertSame($fieldResource, $manager::getRelatedResource());
        $view = Livewire::test($manager, ['ownerRecord' => $component, 'pageClass' => EditPageComponent::class]);
        $url = $fieldResource::getUrl('create', [$component->fields()->getForeignKeyName() => $component->id]);
        $this->assertSame($url, $view->instance()->getTable()->getHeaderActions()[0]->getUrl());
        $field = $component->fields()->create(['title' => 'Heading', 'slug' => 'heading', 'resolver' => 'text']);
        $view->mountAction(TestAction::make('edit')->table($field))
            ->assertFormFieldExists('host_marker');

    }

    public function test_models_and_resources_can_be_replaced_independently(): void
    {
        config(['filament-page.resources.page' => PageResource::class]);
        $this->assertSame(HostPage::class, PageResource::getModel());
        $this->assertSame(PageResource::class, CreatePage::getResource());
        config(['filament-page.resources.page' => HostPageResource::class, 'filament-page.models.page' => Page::class]);
        $this->assertSame(Page::class, HostPageResource::getModel());
    }

    public function test_custom_resource_still_enforces_tenant_record_scope(): void
    {
        $this->editor();
        $tenant = Tenant::create(['name' => 'Other', 'slug' => 'other-resource', 'is_active' => true]);
        $foreign = app(TenantContext::class)->run($tenant, fn () => HostPage::create(['name' => 'Foreign', 'path' => '/foreign']));
        Livewire::test(EditPage::class, ['record' => $foreign->id])->assertNotFound();
    }

    public function test_language_switch_redirects_to_the_host_resource(): void
    {
        config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
        $this->editor();
        $en = HostPage::create(['name' => 'English', 'path' => '/language', 'locale' => 'en']);
        $zh = HostPage::create(['name' => '中文', 'path' => '/language', 'locale' => 'zh_CN', 'translation_group_id' => $en->translation_group_id]);
        Livewire::test(EditPage::class, ['record' => $en->id])
            ->callAction('switchLanguage', data: ['locale' => 'zh_CN'])->assertRedirect(HostPageResource::getUrl('edit', ['record' => $zh]));
    }

    public function test_templates_and_public_projection_use_configured_models(): void
    {
        $admin = $this->editor();
        $page = HostPage::create(['name' => 'Host API', 'path' => '/host-api', 'type' => 'basic', 'locale' => 'en', 'is_active' => true, 'components' => []]);
        $template = app(PageTemplateService::class)->save($page, ['name' => 'Host template']);
        $this->assertInstanceOf(HostPageTemplate::class, $template);
        $this->assertInstanceOf(HostPageTemplate::class, HostPageTemplateResource::getEloquentQuery()->firstOrFail());
        Livewire::test(CreatePage::class)->callAction('useTemplate', data: ['template' => $template->id])->assertHasNoFormErrors();
        $this->getJson('/modules/pages/v2/detail/host-api')->assertOk()->assertJsonPath('host_projection', true);
        $token = app(PagePreviewSession::class)->issue($page, $admin);
        $this->getJson('/headless/preview/draft?page=/host-api', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.projection.host_projection', true);
    }

    public function test_unconfigured_slug_keeps_filament_panel_configuration(): void
    {
        config(['filament-page.resource_slugs.page' => null]);
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $panel->resources([ResourceConfiguration::make(HostPageResource::class, 'special')->slug('panel-content')]);
        HostPageResource::withConfiguration('special', function () use ($panel): void {
            $this->assertSame('panel-content', HostPageResource::getSlug($panel));
        });
    }
}
