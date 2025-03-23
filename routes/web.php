<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
 */

Route::get('/', function () {
    return view('welcome');
})->name('welcome');
Route::get('/no-login', function () {
    return response(["messsage" => "Unauthenticated"], 401);
})->name('welcome-no-login');
Route::get('/download-ticket/{urlKey}', [\App\Http\Controllers\TicketDownloadCtrl::class, 'index'])->name('home-view-ticket');
Route::post('/preview-ticket/{urlKey}', [\App\Http\Controllers\TicketDownloadCtrl::class, 'previewTicket'])->name('preview-ticket');
Route::post('/download-ticket/{urlKey}', [\App\Http\Controllers\TicketDownloadCtrl::class, 'downloadTicket'])->name('download-ticket');

// Route for protect special files
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/storage/invoice_inv/SWnQC_1737453214.pdf', function (Request $req) {
        dd($req);
        $path = storage_path('app/public/storage/invoice_inv/' . $file);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    });
    Route::get('/login-first', function () {
        return view('welcome');
    })->name('welcome');
});
