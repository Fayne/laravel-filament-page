<?php

use Fayne\Page\Http\Controllers\DraftPreviewController;
use Illuminate\Support\Facades\Route;

Route::get(trim(config('filament-cms.headless.prefix', 'headless'), '/').'/preview/draft', DraftPreviewController::class)->middleware('api')->name('filament-cms.headless.preview.draft');
