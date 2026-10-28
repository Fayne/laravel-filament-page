<?php

namespace Fayne\Page\Filament\Resources\PageComponentResource\RelationManagers;

use Fayne\Page\Filament\Resources\PageComponentFieldResource;
use Fayne\PageBuilder\Filament\RelationManagers\DefinitionFieldsRelationManager;

class FieldsRelationManager extends DefinitionFieldsRelationManager
{
    protected static ?string $relatedResource = PageComponentFieldResource::class;

    public static function getRelatedResource(): ?string
    {
        return PageComponentFieldResource::configuredResource();
    }
}
