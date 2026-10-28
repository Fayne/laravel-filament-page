<?php

namespace Fayne\Page\Filament\Resources\PageWidgetResource\Pages;

use Fayne\Page\Filament\Resources\PageWidgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageWidgets extends ListRecords
{
    protected static string $resource = PageWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
