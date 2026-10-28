<?php

namespace Fayne\Page\Support;

use Fayne\CMS\Contracts\ReleaseSnapshotProvider;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\PublicationRegion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

final class PageReleaseSnapshotProvider implements ReleaseSnapshotProvider
{
    public function entityType(): string
    {
        return 'page';
    }

    public function snapshot(DeploymentRecord $release): array
    {
        $region = PublicationRegion::resolve(data_get($release->deployment_contents, 'region'));
        $connection = config('database.default');
        if ($region !== null && $region !== '') {
            $definition = collect(config('filament-cms.regions', []))->first(fn ($item): bool => (string) $item['id'] === (string) $region);
            $connection = $definition['database'] ?? null;
            if (! $connection || ! config('database.connections.'.$connection)) {
                throw ValidationException::withMessages(['region' => __('filament-page::errors.release_region_database_missing')]);
            }
        }

        return Context::scope(function () use ($release, $connection): array {
            $model = config('filament-page.models.page');
            $locale = ContentLocale::resolve(data_get($release->deployment_contents, 'locale'));

            return (new $model)->setConnection($connection)->newQuery()->published()->locale($locale)->get()->map(function ($page) use ($release): array {
                $projection = $page->publicProjection();

                return [...(data_get($release->deployment_contents, 'publication_mode') === 'whole_site' ? ['translation_group_id' => $page->translation_group_id] : []), 'entity_type' => 'page', 'entity_id' => (string) $page->getKey(), 'revision_id' => $page->currentPublishedRevision?->getKey(), 'path' => $projection['path'], 'locale' => $page->locale, 'projection' => $projection, 'comparison_projection' => Arr::only($projection, array_keys($page->contentSnapshot())), 'content_snapshot' => $page->contentSnapshot(), 'connection' => $page->getConnectionName()];
            })->all();
        }, ['filament-cms.region_id' => $region ?? '']);
    }
}
