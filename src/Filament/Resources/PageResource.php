<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\Operations\Operations;
use Fayne\Page\Filament\Concerns\HasResourceConfiguration;
use Fayne\Page\Models\Page;
use Fayne\Page\Services\PageContentValidator;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PageResource extends Resource
{
    use HasResourceConfiguration;

    protected static string $configurationKey = 'page';

    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.pages');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = Page::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_page');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page', static::$model);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! config('filament-page.models.page')::isMultilingualEnabled(), fn (Builder $query) => $query->locale());
    }

    public static function form(Schema $schema): Schema
    {
        $definitions = new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'));

        return $schema->components([
            Tabs::make('page-editor')->columnSpanFull()->tabs([
                Tab::make(__('filament-page::translations.basic'))->columns(2)->schema([
                    TextInput::make('name')->label(__('filament-page::translations.name'))->required()->maxLength(255),
                    TextInput::make('path')->label(__('filament-page::translations.path'))->required()->maxLength(255),
                    Select::make('type')->label(__('filament-page::translations.type'))->options(config('filament-page.page_types_class')::getSelectOptions())->required()->default(config('filament-page.default_page_type', 'basic'))->live()->afterStateUpdated(function ($state, Set $set) use ($definitions): void {
                        $raw = app(PageContentValidator::class)->initialFixed($state);
                        $set('fixed_components', (new ContentStateAdapter($definitions))->toBuilder($raw));
                    }),
                    Select::make('locale')->label(__('filament-page::translations.locale'))
                        ->options(fn (): array => config('filament-page.models.page')::isMultilingualEnabled() ? ContentLocale::supported() : [ContentLocale::default() => ContentLocale::supported()[ContentLocale::default()]])
                        ->default(fn (): string => ContentLocale::default())->required()
                        ->disabled(fn (?Page $record): bool => $record?->exists ?? false)->dehydrated(fn (?Page $record): bool => ! ($record?->exists ?? false)),
                    Toggle::make('is_active')->label(__('filament-page::translations.active')),
                ]),
                Tab::make(__('filament-page::translations.content'))->schema([
                    View::make('filament-page::filament.quick-builder')->visible(fn (Get $get): bool => ! in_array($get('type'), config('filament-page.excluded_components', []), true))->columnSpanFull(),
                    ContentBuilder::make('components')->label(__('filament-page::translations.components'))->visible(fn (Get $get): bool => ! in_array($get('type'), config('filament-page.excluded_components', []), true))->definitions($definitions, $definitions->layouts(), $definitions->layouts(true))->columnSpanFull(),
                    ContentBuilder::make('fixed_components')->label(__('filament-page::translations.fixed'))->definitions($definitions, $definitions->layouts(), $definitions->layouts(true))->addable(false)->deletable(false)->reorderable(false)->columnSpanFull(),
                ]),
                Tab::make(__('filament-page::translations.seo'))->columns(2)->schema([
                    TextInput::make('title')->label(__('filament-page::translations.title'))->maxLength(255)->columnSpanFull(),
                    Textarea::make('description')->label(__('filament-page::translations.description'))->columnSpanFull(),
                    TagsInput::make('keywords')->label(__('filament-page::translations.keywords'))->columnSpanFull(),
                    KeyValue::make('meta')->label(__('filament-page::translations.meta'))->keyLabel(__('filament-page::translations.meta_key'))->valueLabel(__('filament-page::translations.meta_value'))->addActionLabel(__('filament-page::translations.meta_add'))->helperText(__('filament-page::translations.meta_help'))->columnSpanFull(),
                    Toggle::make('no_index')->label(__('filament-page::translations.no_index')),
                    Toggle::make('no_follow')->label(__('filament-page::translations.no_follow')),
                ]),
                Tab::make(__('filament-page::translations.advanced'))->columns(2)->schema([
                    TextInput::make('body_classes')->label(__('filament-page::translations.body_classes')),
                    TextInput::make('main_classes')->label(__('filament-page::translations.main_classes')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tableName = $query->getModel()->getTable();
                $representative = DB::table($tableName.' as variant')
                    ->select('variant.id')
                    ->whereColumn('variant.translation_group_id', $tableName.'.translation_group_id')
                    ->whereColumn('variant.tenant_id', $tableName.'.tenant_id')
                    ->orderByRaw('CASE WHEN variant.locale = ? THEN 0 ELSE 1 END', [ContentLocale::default()])
                    ->orderBy('variant.id')->limit(1);

                return $query->where($tableName.'.id', '=', $representative)
                    ->with('translationVariants');
            })
            ->columns([
                TextColumn::make('name')->label(__('filament-page::translations.name'))->searchable(),
                TextColumn::make('path')->label(__('filament-page::translations.path'))->searchable()->sortable(),
                TextColumn::make('translation_languages')->label(__('filament-page::translations.locale'))
                    ->state(fn (Page $record): string => $record->translationVariants
                        ->map(fn (Page $variant): string => ContentLocale::supported()[$variant->locale] ?? $variant->locale)->join(' / ')),
                ToggleColumn::make('is_active')->label(__('filament-page::translations.active'))
                    ->state(fn (Page $record): bool => $record->translationVariants->every(fn (Page $variant): bool => $variant->is_active))
                    ->disabled(fn (Page $record): bool => $record->translationVariants->contains(fn (Page $variant): bool => ! Gate::allows('update', $variant)))
                    ->updateStateUsing(function (Page $record, bool $state): bool {
                        app(Operations::class)->run('page.activation.update', function () use ($record, $state): void {
                            $record->getConnection()->transaction(function () use ($record, $state): void {
                                $variants = $record->translations();
                                foreach ($variants as $variant) {
                                    Gate::authorize('update', $variant);
                                }
                                foreach ($variants as $variant) {
                                    $variant->update(['is_active' => $state]);
                                }
                            });
                        }, ['is_active' => $state], $record, ['source' => 'filament']);

                        return $state;
                    }),
            ])
            ->filters([
                SelectFilter::make('locale')->label(__('filament-page::translations.locale'))
                    ->options(fn (): array => config('filament-page.models.page')::isMultilingualEnabled() ? ContentLocale::supported() : [ContentLocale::default() => ContentLocale::supported()[ContentLocale::default()]])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('translationVariants', fn (Builder $variants): Builder => $variants->where('locale', $data['value'])),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('filament-page::translations.delete_scope'))
                    ->schema([
                        Toggle::make('all_languages')->label(__('filament-page::translations.delete_all_languages'))->default(false),
                    ])
                    ->using(function (Page $record, array $data): bool {
                        return $record->getConnection()->transaction(function () use ($record, $data): bool {
                            $targets = ($data['all_languages'] ?? false) ? $record->translations() : collect([$record]);
                            foreach ($targets as $target) {
                                Gate::authorize('delete', $target);
                            }
                            foreach ($targets as $target) {
                                if (! $target->delete()) {
                                    throw new \RuntimeException(__('filament-page::errors.page_delete_failed'));
                                }
                            }

                            return true;
                        });
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => PageResource\Pages\ListPages::route('/'), 'create' => PageResource\Pages\CreatePage::route('/create'), 'view' => PageResource\Pages\ViewPage::route('/{record}'), 'edit' => PageResource\Pages\EditPage::route('/{record}/edit')];
    }
}
