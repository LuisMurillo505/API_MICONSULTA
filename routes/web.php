<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PlanController;

Route::get('/', function () {
    return view('welcome');
});



