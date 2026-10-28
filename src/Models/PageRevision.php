<?php

namespace Fayne\Page\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Page
{
    public function usesTranslationGroup(): bool
    {
        return false;
    }

    protected $casts = [
        'components' => 'array', 'fixed_components' => 'array', 'keywords' => 'array', 'meta' => 'array', 'extra_data' => 'array',
        'is_stash' => 'boolean', 'is_active' => 'boolean', 'no_index' => 'boolean', 'no_follow' => 'boolean', 'released_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('filament-page.tables.page_revisions', 'nova_page_revisions');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(config('filament-page.models.page'), 'page_id');
    }
}
