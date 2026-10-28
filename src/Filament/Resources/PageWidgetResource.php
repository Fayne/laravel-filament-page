<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\Page\Models\PageWidget;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageWidgetResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.widgets');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageWidget::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_pagewidget');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page_widget', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label(__('filament-page::ui.field_name'))->required(), TextInput::make('slug')->label(__('filament-page::ui.field_slug'))->required()->unique(ignoreRecord: true), Textarea::make('description')->label(__('filament-page::ui.field_description')), TextInput::make('weight')->label(__('filament-page::ui.field_weight'))->numeric()->default(0)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label(__('filament-page::ui.field_id'))->sortable(), TextColumn::make('name')->label(__('filament-page::ui.field_name'))->searchable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PageWidgetResource\RelationManagers\FieldsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => PageWidgetResource\Pages\ListPageWidgets::route('/'), 'create' => PageWidgetResource\Pages\CreatePageWidget::route('/create'), 'edit' => PageWidgetResource\Pages\EditPageWidget::route('/{record}/edit')];
    }
}
