<?php

namespace Fayne\Page\Support;

use Fayne\CMS\Contracts\ReleaseSnapshotProvider;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Support\ReleaseSnapshotContext;

final class PageDefinitionsReleaseSnapshotProvider implements ReleaseSnapshotProvider
{
    public function entityType(): string
    {
        return 'global_page_definitions';
    }

    public function snapshot(DeploymentRecord $release): array
    {
        return ReleaseSnapshotContext::run($release, function ($connection): array {
            $definitions = [];
            foreach (['component', 'widget'] as $kind) {
                $model = config("filament-page.models.page_{$kind}");
                $definitions[$kind] = (new $model)->setConnection($connection)->newQuery()->orderBy('id')->get()->map(fn ($record) => [
                    ...$record->only(['id', 'name', 'slug', 'weight', 'specifications']),
                    'fields' => $record->fields->map(fn ($field) => $field->only(['id', 'title', 'slug', 'resolver', 'options', 'rules', 'weight']))->all(),
                ])->all();
            }

            return [['entity_type' => $this->entityType(), 'entity_id' => 'definitions', 'projection' => $definitions]];
        });
    }
}
