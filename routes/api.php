<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\admin\StoreController;
use App\Http\Controllers\admin\CategoryController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


//Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('forget_password', [AuthController::class, 'forgetPassword']);
    Route::post('resend_otp', [AuthController::class, 'resendOTP']);

    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('change_password', [AuthController::class, 'chnagePassword']);
    });
});


//Social login 
Route::prefix('social')->group(function () {
    Route::get('login', [AuthController::class, 'socialLogin']);
    Route::post('google/callback', [AuthController::class, 'googleCallback']);
});


//Admin Routes
Route::group(['prefix' => 'admin', 'middleware' => ['jwt.auth', 'admin']], function(){
    Route::controller(StoreController::class)->group(function(){
        Route::post('addStore', 'addStore')->name('admin.addStore');
    });
    Route::controller(CategoryController::class)->group(function(){
        Route::post('addCategory', 'addCategory');
        Route::get('showCategory/{id}', 'showCategory');
        Route::post('updateCategory/{id}', 'updateCategory');
        Route::post('searchCategory', 'searchCategory');
        Route::get('getAllCategories', 'getAllCategories');
        Route::delete('deleteCategory/{id}', 'deleteCategory');
    });
});

