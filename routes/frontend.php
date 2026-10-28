<?php

use Fayne\Page\Http\Controllers\LocalizedPageController;
use Fayne\Page\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('v2/detail/{path?}', [LocalizedPageController::class, 'show'])->where('path', '.*')->name('v2.detail');
Route::get('v2/slugs', [LocalizedPageController::class, 'index'])->name('v2.index');
Route::get('detail/{path?}', [PageController::class, 'show'])->where('path', '.*')->name('detail');
Route::get('slugs', [PageController::class, 'index'])->name('index');
