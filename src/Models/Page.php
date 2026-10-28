<?php

namespace Fayne\Page\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\HasTranslationGroup;
use Fayne\CMS\Support\Concerns\RegionAware;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;

class Page extends Model
{
    use BelongsToTenant;
    use HasTranslationGroup;
    use RegionAware;

    protected $guarded = ['id'];

    protected $attributes = ['type' => 'basic', 'no_index' => false, 'no_follow' => false, 'is_active' => false];

    protected $hidden = ['translation_group_id'];

    protected $casts = [
        'components' => 'array', 'fixed_components' => 'array', 'keywords' => 'array', 'meta' => 'array', 'extra_data' => 'array',
        'changed' => 'boolean', 'has_stash' => 'boolean', 'is_active' => 'boolean', 'no_index' => 'boolean', 'no_follow' => 'boolean',
    ];

    protected array $translatableFields = ['name', 'title', 'description', 'keywords'];

    public function getTable(): string
    {
        return config('filament-page.tables.pages', 'nova_pages');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublicPath(Builder $query, string $path): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereHas('currentPublishedRevision', fn (Builder $revision) => $revision->where('path', $path))
            ->orWhere(fn (Builder $legacy) => $legacy->whereDoesntHave('currentPublishedRevision')->where('path', $path)));
    }

    public function publishedProjection(?array $fields = null): array
    {
        $published = $this->currentPublishedRevision;
        if (! $published) {
            return $this->publicProjection($fields);
        }
        $page = clone $this;
        $page->forceFill(Arr::except($published->contentSnapshot(), ['locale', 'fallback_id', 'translation_group_id']));

        return $page->publicProjection($fields);
    }

    public function translationVariants(): HasMany
    {
        return $this->hasMany(static::class, 'translation_group_id', 'translation_group_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(config('filament-page.models.page_revision'), 'page_id');
    }

    public function currentPublishedRevision(): HasOne
    {
        return $this->hasOne(config('filament-page.models.page_revision'), 'page_id')->where('is_stash', false)->whereNotNull('released_at')->latestOfMany('released_at');
    }

    public function stashRevision(): HasOne
    {
        return $this->hasOne(config('filament-page.models.page_revision'), 'page_id')->where('is_stash', true);
    }

    public function publicProjection(?array $fields = null): array
    {
        $projection = $this->attributesToArray();
        if ($fields !== null) {
            $projection = Arr::only($projection, $fields);
        }
        $definitions = new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'), $this->getConnectionName());
        foreach (['components', 'fixed_components'] as $field) {
            if ($fields !== null && ! in_array($field, $fields, true)) {
                continue;
            }
            $projection[$field] = app(ContentOutputResolver::class)->resolve($this->{$field} ?? [], $definitions, connection: $this->getConnectionName(), locale: $this->locale);
        }

        return $projection;
    }

    public function contentSnapshot(): array
    {
        return $this->only(['path', 'type', 'name', 'title', 'description', 'keywords', 'meta', 'components', 'fixed_components', 'extra_data', 'no_index', 'no_follow', 'is_active', 'body_classes', 'main_classes', 'locale', 'fallback_id']);
    }
}
