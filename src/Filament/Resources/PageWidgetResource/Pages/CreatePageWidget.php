<?php

namespace Fayne\Page\Filament\Resources\PageWidgetResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageWidgetResource;

class CreatePageWidget extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageWidgetResource::class;
}
