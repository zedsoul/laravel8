<?php

use App\Http\Controllers\adminController;
use App\Http\Controllers\uploadController;
use App\Http\Controllers\userController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('users')->group(function (){
    Route::post('login',[userController::class,'login']);  //用户登录   zlc
    Route::post('registed',[userController::class,'registed']);  //用户注册  zlc
    Route::post('again',[userController::class,'again']);  //修改用户密码  zlc

});
Route::middleware('role:user')->group(function () {
    Route::post('test',[adminController::class,'test']);
});
Route::prefix('admin')->group(function (){
    Route::post('login',[adminController::class,'login']);  //用户登录  zlc
    Route::post('registed',[adminController::class,'registered']);  //用户注册 zlc
    Route::post('again',[adminController::class,'again']);  //修改用户密码  zlc
});
Route::middleware('jwt.refresh')->group(function () {

});
Route::post('upload',[uploadController::class,'upload']);//OSS上传(不可用)
Route::post('load',[uploadController::class,'load']);//分片上传 zlc
