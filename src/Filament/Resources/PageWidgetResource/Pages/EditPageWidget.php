<?php

namespace Fayne\Page\Filament\Resources\PageWidgetResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageWidgetResource;

class EditPageWidget extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageWidgetResource::class;
}
