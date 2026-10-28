<?php

use Fayne\Page\Filament\Resources\PageComponentFieldResource;
use Fayne\Page\Filament\Resources\PageComponentResource;
use Fayne\Page\Filament\Resources\PageResource;
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
use Fayne\Page\Support\PageType;

return [
    'models' => [
        'page_template' => PageTemplate::class,
        'page' => Page::class,
        'page_revision' => PageRevision::class,
        'page_component' => PageComponent::class,
        'page_component_field' => PageComponentField::class,
        'page_widget' => PageWidget::class,
        'page_widget_field' => PageWidgetField::class,
    ],
    'resources' => [
        'page_revision' => PageRevisionResource::class,
        'page' => PageResource::class,
        'page_widget' => PageWidgetResource::class,
        'page_template' => PageTemplateResource::class,
        'page_widget_field' => PageWidgetFieldResource::class,
        'page_component_field' => PageComponentFieldResource::class,
        'page_component' => PageComponentResource::class,
    ],
    'resource_slugs' => [],
    'tables' => [
        'pages' => 'filament_pages', 'page_revisions' => 'filament_page_revisions',
        'page_components' => 'filament_page_components', 'page_component_fields' => 'filament_page_component_fields',
        'page_widgets' => 'filament_page_widgets', 'page_widget_fields' => 'filament_page_widget_fields',
    ],
    'page_types_class' => PageType::class,
    'creation_limited_page_types' => ['homepage' => 1, '404_page' => 1],
    'page_type_changeable' => false,
    'fixed_components' => [], 'excluded_components' => [],
    'use_default_frontend_routes' => true,
    'page_preview_url' => env('FILAMENT_PAGE_PREVIEW_URL'),
    'headless' => ['release_pages_path' => 'releases/{releaseId}/pages'],
];
