<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\Operations\Operations;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ComponentDefinitionTransferService
{
    public function defaults(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../resources/defaults/components.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function model(string $kind): string
    {
        return config('filament-page.models.page_'.$kind);
    }

    private function definitions(array $data): array
    {
        if (! array_is_list($data)) {
            throw ValidationException::withMessages(['json' => __('filament-page::errors.component_array_expected')]);
        }
        $widgets = [];
        $visit = function (array $items, int $depth = 0) use (&$visit, &$widgets): void {
            if ($depth > 10) {
                throw ValidationException::withMessages(['json' => __('filament-page::errors.widget_nesting_too_deep')]);
            }
            Validator::make(['items' => $items], [
                'items' => 'array', 'items.*' => 'array',
                'items.*.name' => 'required|string|max:255', 'items.*.slug' => 'required|string|max:255|distinct',
                'items.*.fields' => 'present|array', 'items.*.fields.*' => 'array',
                'items.*.fields.*.title' => 'required|string|max:255',
                'items.*.fields.*.slug' => 'required|string|max:255',
                'items.*.fields.*.resolver' => 'required|string|max:255',
                'items.*.fields.*.options' => 'nullable|array', 'items.*.fields.*.rules' => 'nullable|array',
                'items.*.fields.*.widgets' => 'sometimes|array',
                'items.*.fields.*.widgets.*.id' => 'required|integer',
                'items.*.weight' => 'sometimes|integer|min:0',
                'items.*.mockup' => 'nullable|array', 'items.*.specifications' => 'nullable|array',
            ])->validate();
            foreach ($items as $item) {
                $slugs = array_column($item['fields'], 'slug');
                if (count($slugs) !== count(array_unique($slugs))) {
                    throw ValidationException::withMessages(['json' => __('filament-page::errors.duplicate_field_slugs')]);
                }
                foreach ($item['fields'] as $field) {
                    if ($field['resolver'] !== 'widget') {
                        try {
                            (new FieldSchemaFactory)->make([...$field, 'name' => $field['slug']]);
                        } catch (\InvalidArgumentException|\JsonException $exception) {
                            throw ValidationException::withMessages(['json' => $item['slug'].'.'.$field['slug'].': '.$exception->getMessage()]);
                        }
                    }
                    $nested = $field['widgets'] ?? [];
                    $visit($nested, $depth + 1);
                    foreach ($nested as $widget) {
                        if (isset($widgets[$widget['slug']]) && $widgets[$widget['slug']] !== $widget) {
                            throw ValidationException::withMessages(['json' => __('filament-page::errors.conflicting_widget', ['slug' => $widget['slug']])]);
                        }
                        $widgets[$widget['slug']] = $widget;
                    }
                    if (($field['resolver'] ?? '') === 'widget') {
                        $ids = array_column($nested, 'id');
                        if (! is_array($field['options']['widgets'] ?? [])) {
                            throw ValidationException::withMessages(['json' => __('filament-page::errors.widget_references_array')]);
                        }
                        foreach ($field['options']['widgets'] ?? [] as $id) {
                            if (! in_array($id, $ids)) {
                                throw ValidationException::withMessages(['json' => __('filament-page::errors.missing_embedded_widget')]);
                            }
                        }
                    }
                }
            }
        };
        $visit($data);

        return $widgets;
    }

    public function conflicts(array $data): array
    {
        $widgets = $this->definitions($data);
        $conflicts = [];
        foreach (['component' => $data, 'widget' => $widgets] as $kind => $items) {
            foreach ($this->model($kind)::whereIn('slug', array_column($items, 'slug'))->pluck('slug') as $slug) {
                $conflicts[] = $kind.':'.$slug;
            }
        }

        return $conflicts;
    }

    public function import(array $data, string $mode): void
    {
        app(Operations::class)->run('page.definitions.import', function () use ($data, $mode): void {
            if (! in_array($mode, ['skip', 'update'], true)) {
                throw ValidationException::withMessages(['mode' => __('filament-page::errors.choose_import_mode')]);
            }
            $widgets = $this->definitions($data);
            $model = $this->model('component');
            (new $model)->getConnection()->transaction(function () use ($data, $widgets, $mode): void {
                $ids = [];
                $existingWidgets = $this->model('widget')::pluck('slug')->all();
                foreach ($widgets as $slug => $widget) {
                    $ids[$slug] = $this->model('widget')::firstOrCreate(['slug' => $slug], Arr::only($widget, ['name', 'description', 'weight', 'mockup', 'specifications']))->getKey();
                }
                foreach (['widget' => $widgets, 'component' => $data] as $kind => $items) {
                    foreach ($items as $item) {
                        $record = $this->model($kind)::where('slug', $item['slug'])->first();
                        // Widgets were allocated above so references can resolve in any order.
                        $wasExisting = $record && ! $record->wasRecentlyCreated;
                        if ($kind === 'widget') {
                            $wasExisting = in_array($item['slug'], $existingWidgets, true);
                        }
                        if ($wasExisting && $mode === 'skip') {
                            continue;
                        }
                        $record ??= new ($this->model($kind));
                        $record->fill(Arr::only($item, ['name', 'slug', 'description', 'weight', 'mockup', 'specifications']))->save();
                        foreach ($item['fields'] as $field) {
                            $attributes = Arr::only($field, ['title', 'slug', 'resolver', 'options', 'rules', 'weight']);
                            if ($field['resolver'] === 'widget') {
                                $selected = $field['options']['widgets'] ?? array_column($field['widgets'] ?? [], 'id');
                                $attributes['options']['widgets'] = [];
                                foreach ($field['widgets'] ?? [] as $widget) {
                                    if (in_array($widget['id'], $selected)) {
                                        $attributes['options']['widgets'][] = $ids[$widget['slug']];
                                    }
                                }
                            }
                            $record->fields()->updateOrCreate(['slug' => $field['slug']], $attributes);
                        }
                    }
                }
            });
        }, ['data' => $data, 'mode' => $mode], null, []);
    }

    public function export(): array
    {
        $serialize = function ($record, array $seen = []) use (&$serialize): array {
            $item = Arr::only($record->toArray(), ['id', 'name', 'slug', 'description', 'weight', 'mockup', 'specifications']);
            if (in_array($record::class.':'.$record->slug, $seen)) {
                throw ValidationException::withMessages(['json' => __('filament-page::errors.circular_widget_export')]);
            }
            $item['fields'] = [];
            foreach ($record->fields as $field) {
                $attributes = Arr::only($field->toArray(), ['title', 'slug', 'resolver', 'options', 'rules', 'weight']);
                if ($field->resolver === 'widget') {
                    $attributes['widgets'] = [];
                    foreach ($field->options['widgets'] ?? [] as $id) {
                        $widget = $this->model('widget')::findOrFail($id);
                        $attributes['widgets'][] = $serialize($widget, [...$seen, $record::class.':'.$record->slug]);
                    }
                }
                $item['fields'][] = $attributes;
            }

            return $item;
        };

        return $this->model('component')::orderBy('weight')->orderBy('id')->get()->map(fn ($record) => $serialize($record))->all();
    }
}
