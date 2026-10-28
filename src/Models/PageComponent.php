<?php

namespace Fayne\Page\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageComponent extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['mockup' => 'array', 'specifications' => 'array'];

    public function getTable(): string
    {
        return config('filament-page.tables.page_components', 'nova_page_components');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(config('filament-page.models.page_component_field'), 'page_component_id')->orderBy('weight')->orderBy('id');
    }
}
