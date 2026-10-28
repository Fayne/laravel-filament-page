<?php

use Fayne\Page\Http\Controllers\ReleasePageController;
use Illuminate\Support\Facades\Route;

Route::get(config('filament-page.headless.release_pages_path', 'releases/{releaseId}/pages'), [ReleasePageController::class, 'index'])->name('filament-cms.headless.pages');
