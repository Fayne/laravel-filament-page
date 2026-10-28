<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\Page\Models\PageWidgetField;
use Fayne\PageBuilder\Filament\Forms\FieldDefinitionSchema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageWidgetFieldResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.widget_fields');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageWidgetField::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_pagewidgetfield');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page_widget_field', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FieldDefinitionSchema::owner('nova_page_widget_id', config('filament-page.models.page_widget'), PageWidgetResource::class, __('filament-page::ui.owning_widget')),
            ...static::fieldFormComponents(),
        ]);
    }

    public static function fieldFormComponents(): array
    {
        return FieldDefinitionSchema::components(config('filament-page.models.page_widget'));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label(__('filament-page::ui.field_title'))->searchable(), TextColumn::make('resolver')->label(__('filament-page::translations.field_type'))->formatStateUsing(fn ($state, $record) => ContentFieldTypes::label($state, $record->options)), TextColumn::make('weight')->label(__('filament-page::ui.field_weight'))->sortable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => PageWidgetFieldResource\Pages\ListPageWidgetFields::route('/'), 'create' => PageWidgetFieldResource\Pages\CreatePageWidgetField::route('/create'), 'edit' => PageWidgetFieldResource\Pages\EditPageWidgetField::route('/{record}/edit')];
    }
}
