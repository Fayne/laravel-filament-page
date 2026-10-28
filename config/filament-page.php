<?php

use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Models\PageComponentField;
use Fayne\Page\Models\PageRevision;
use Fayne\Page\Models\PageWidget;
use Fayne\Page\Models\PageWidgetField;
use Fayne\Page\Support\PageType;

return [
    'models' => [
        'page' => Page::class,
        'page_revision' => PageRevision::class,
        'page_component' => PageComponent::class,
        'page_component_field' => PageComponentField::class,
        'page_widget' => PageWidget::class,
        'page_widget_field' => PageWidgetField::class,
    ],
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
