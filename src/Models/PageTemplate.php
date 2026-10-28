<?php

namespace Fayne\Page\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;

class PageTemplate extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $table = 'cms_page_templates';

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = ['structure' => 'array', 'is_active' => 'boolean'];
}
