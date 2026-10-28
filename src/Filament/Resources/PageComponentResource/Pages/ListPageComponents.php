<?php

namespace Fayne\Page\Filament\Resources\PageComponentResource\Pages;

use Fayne\Page\Filament\Resources\PageComponentResource;
use Fayne\Page\Services\ComponentDefinitionTransferService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ListPageComponents extends ListRecords
{
    protected static string $resource = PageComponentResource::class;

    #[Locked]
    public array $pendingDefinitions = [];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importDefinitions')->label(__('filament-page::ui.import_component_library'))
                ->authorize(fn () => PageComponentResource::canCreate())
                ->schema([
                    FileUpload::make('file')->label(__('filament-page::ui.json_file'))->acceptedFileTypes(['application/json'])->storeFiles(false)->maxSize(5120),
                    Textarea::make('json')->label(__('filament-page::ui.or_paste_json'))->rows(10)->rules(['nullable', 'json']),
                ])
                ->action(function (array $data): void {
                    $json = isset($data['file']) ? file_get_contents($data['file']->getRealPath()) : ($data['json'] ?? '');
                    try {
                        $definitions = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                    } catch (\JsonException $exception) {
                        throw ValidationException::withMessages(['json' => $exception->getMessage()]);
                    }
                    if (! is_array($definitions)) {
                        throw ValidationException::withMessages(['json' => __('filament-page::ui.select_a_file_or_paste_a_component_array')]);
                    }
                    $this->prepareImport($definitions);
                }),
            Action::make('exportDefinitions')->label(__('filament-page::ui.export_component_library'))
                ->authorize(fn () => PageComponentResource::canViewAny())
                ->action(function () {
                    Gate::authorize('viewAny', PageComponentResource::getModel());
                    $json = json_encode(app(ComponentDefinitionTransferService::class)->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                    return response()->streamDownload(fn () => print ($json), 'components.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
                }),
        ];
    }

    public function resolveDefinitionConflictsAction(): Action
    {
        return Action::make('resolveDefinitionConflicts')->label(__('filament-page::ui.resolve_duplicate_components'))
            ->authorize(fn () => PageComponentResource::canCreate())
            ->modalHeading(__('filament-page::ui.duplicate_slug_found'))
            ->modalDescription(fn () => implode('、', app(ComponentDefinitionTransferService::class)->conflicts($this->pendingDefinitions)))
            ->schema([Radio::make('mode')->label(__('filament-page::ui.import_conflict_handling'))->options(['skip' => __('filament-page::ui.skip_existing'), 'update' => __('filament-page::ui.update_existing')])->required()])
            ->action(fn (array $data) => $this->finishImport($this->pendingDefinitions, $data['mode']));
    }

    private function prepareImport(array $definitions): void
    {
        Gate::authorize('create', PageComponentResource::getModel());
        $conflicts = app(ComponentDefinitionTransferService::class)->conflicts($definitions);
        if ($conflicts !== []) {
            $this->pendingDefinitions = $definitions;
            $this->replaceMountedAction('resolveDefinitionConflicts');

            return;
        }
        $this->finishImport($definitions, 'skip');
    }

    private function finishImport(array $definitions, string $mode): void
    {
        Gate::authorize('create', PageComponentResource::getModel());
        $service = app(ComponentDefinitionTransferService::class);
        foreach ($service->conflicts($definitions) as $conflict) {
            [$kind, $slug] = explode(':', $conflict, 2);
            $model = config('filament-page.models.page_'.$kind);
            $record = $model::where('slug', $slug)->firstOrFail();
            Gate::authorize($mode === 'update' ? 'update' : 'view', $record);
        }
        // New embedded widgets require their own permission as well.
        Gate::authorize('create', config('filament-page.models.page_widget'));
        $service->import($definitions, $mode);
        $this->pendingDefinitions = [];
        $this->resetTable();
        Notification::make()->title(__('filament-page::ui.component_definitions_imported'))->success()->send();
    }
}
