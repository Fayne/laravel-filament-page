<?php

namespace Fayne\Page\Filament\Resources\PageWidgetFieldResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageWidgetFieldResource;

class EditPageWidgetField extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageWidgetFieldResource::class;
}
