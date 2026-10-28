<?php

namespace Fayne\Page\Tests\Fixtures;

use Fayne\Page\Filament\Resources\PageComponentFieldResource;
use Fayne\Page\Filament\Resources\PageComponentResource;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\EditPage;
use Fayne\Page\Filament\Resources\PageRevisionResource;
use Fayne\Page\Filament\Resources\PageTemplateResource;
use Fayne\Page\Filament\Resources\PageWidgetFieldResource;
use Fayne\Page\Filament\Resources\PageWidgetResource;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageComponentField;
use Fayne\Page\Models\PageRevision;
use Fayne\Page\Models\PageTemplate;
use Fayne\Page\Models\PageWidget;
use Fayne\Page\Models\PageWidgetField;
use Fayne\Page\Tests\TestCase;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HostPageTemplate extends PageTemplate {}

class HostPageTemplateResource extends PageTemplateResource {}

class HostPage extends Page
{
    public function publicProjection(?array $fields = null): array
    {
        return [...parent::publicProjection($fields), 'host_projection' => true];
    }
}

class HostPageResource extends PageResource
{
    protected static ?string $slug = 'native-pages';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([...$schema->getComponents(), TextInput::make('extra_data.host_note')]);
    }

    public static function getPages(): array
    {
        return [...parent::getPages(), 'edit' => HostEditPage::route('/{record}/edit')];
    }
}

class HostPageComponent extends PageComponent {}

class HostPageComponentResource extends PageComponentResource {}

class HostPageComponentField extends PageComponentField {}

class HostPageComponentFieldResource extends PageComponentFieldResource
{
    public static function fieldFormComponents(): array
    {
        return [...parent::fieldFormComponents(), TextInput::make('host_marker')->dehydrated(false)];
    }
}

class HostPageWidget extends PageWidget {}

class HostPageWidgetResource extends PageWidgetResource {}

class HostPageWidgetField extends PageWidgetField {}

class HostPageWidgetFieldResource extends PageWidgetFieldResource
{
    public static function fieldFormComponents(): array
    {
        return [...parent::fieldFormComponents(), TextInput::make('host_marker')->dehydrated(false)];
    }
}

class HostPageRevision extends PageRevision {}

class HostPageRevisionResource extends PageRevisionResource {}

class ExtensionTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-page.models.page_template', HostPageTemplate::class);
        $app['config']->set('filament-page.resources.page_template', HostPageTemplateResource::class);
        $app['config']->set('filament-page.resource_slugs.page_template', 'host-page-templates');
        $app['config']->set('filament-page.models.page', HostPage::class);
        $app['config']->set('filament-page.resources.page', HostPageResource::class);
        $app['config']->set('filament-page.resource_slugs.page', 'host-pages');
        $app['config']->set('filament-page.models.page_component', HostPageComponent::class);
        $app['config']->set('filament-page.resources.page_component', HostPageComponentResource::class);
        $app['config']->set('filament-page.resource_slugs.page_component', 'host-page-components');
        $app['config']->set('filament-page.models.page_component_field', HostPageComponentField::class);
        $app['config']->set('filament-page.resources.page_component_field', HostPageComponentFieldResource::class);
        $app['config']->set('filament-page.resource_slugs.page_component_field', 'host-page-component-fields');
        $app['config']->set('filament-page.models.page_widget', HostPageWidget::class);
        $app['config']->set('filament-page.resources.page_widget', HostPageWidgetResource::class);
        $app['config']->set('filament-page.resource_slugs.page_widget', 'host-page-widgets');
        $app['config']->set('filament-page.models.page_widget_field', HostPageWidgetField::class);
        $app['config']->set('filament-page.resources.page_widget_field', HostPageWidgetFieldResource::class);
        $app['config']->set('filament-page.resource_slugs.page_widget_field', 'host-page-widget-fields');
        $app['config']->set('filament-page.models.page_revision', HostPageRevision::class);
        $app['config']->set('filament-page.resources.page_revision', HostPageRevisionResource::class);
        $app['config']->set('filament-page.resource_slugs.page_revision', 'host-page-revisions');
    }
}

class HostEditPage extends EditPage
{
    public function getTitle(): string
    {
        return 'Host page editor';
    }
}
