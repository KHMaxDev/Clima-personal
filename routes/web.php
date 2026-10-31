<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityGenerationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendar', CalendarController::class)->name('calendar');
    Route::get('/forecast', ForecastController::class)->name('forecast');
    Route::get('/history', HistoryController::class)->name('history');
    Route::get('/assistant', [AssistantController::class, 'show'])->name('assistant');

    Route::get('/weather', WeatherController::class)->name('weather.summary');

    Route::get('/weather/place', PlaceController::class)->name('weather.place');

    Route::get('/locations/search', LocationController::class)->name('locations.search');

    Route::post('/activities/assess', [ActivityController::class, 'assess'])->name('activities.assess');
    Route::post('/activities/generate/interpret', [ActivityGenerationController::class, 'interpret'])->name('activities.generate.interpret');
    Route::post('/activities/generate', [ActivityGenerationController::class, 'store'])->name('activities.generate.store');
    Route::get('/activities/calendar', [ActivityController::class, 'calendar'])->name('activities.calendar');
    Route::get('/activities/{activity}/alternatives', [ActivityController::class, 'alternatives'])->name('activities.alternatives');
    Route::post('/activities/{activity}/assistant', [AssistantController::class, 'generate'])->name('activities.assistant');

    Route::resource('activities', ActivityController::class);
});
