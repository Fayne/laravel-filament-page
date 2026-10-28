<?php

namespace Fayne\Page\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageWidgetField extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['options' => 'array', 'rules' => 'array'];

    public function getTable(): string
    {
        return config('filament-page.tables.page_widget_fields', 'nova_page_widget_fields');
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(config('filament-page.models.page_widget'), 'nova_page_widget_id');
    }
}
