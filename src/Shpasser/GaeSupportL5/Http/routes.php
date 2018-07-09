<?php

use \Shpasser\GaeSupportL5\Http\Controllers\ArtisanConsoleController;


Route::get('artisan', array('as' => 'artisan',
    'uses' => ArtisanConsoleController::class . '@show'));

Route::post('artisan', array('as' => 'artisan',
    'uses' => ArtisanConsoleController::class . '@execute'));
