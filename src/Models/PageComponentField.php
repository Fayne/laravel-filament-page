<?php

namespace Fayne\Page\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageComponentField extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['options' => 'array', 'rules' => 'array'];

    public function getTable(): string
    {
        return config('filament-page.tables.page_component_fields', 'nova_page_component_fields');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(config('filament-page.models.page_component'), 'page_component_id');
    }
}
