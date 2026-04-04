<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicThesisSubmissionController;
use App\Http\Controllers\Api\SchoolyearAdminController;
use App\Http\Controllers\Api\TeacherAdminController;
use App\Http\Controllers\Api\TeacherThesisBoardController;
use App\Http\Controllers\Api\ThesisSessionAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::get('/public/thesis-submission/context', [PublicThesisSubmissionController::class, 'context']);
Route::post('/public/thesis-submission', [PublicThesisSubmissionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/thesis-sessions/supervised', [TeacherThesisBoardController::class, 'supervisedSessions']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/teachers', [TeacherAdminController::class, 'index']);
    Route::patch('/teachers/{teacher}', [TeacherAdminController::class, 'update']);

    Route::get('/schoolyears', [SchoolyearAdminController::class, 'index']);
    Route::post('/schoolyears', [SchoolyearAdminController::class, 'store']);
    Route::patch('/schoolyears/{schoolyear}', [SchoolyearAdminController::class, 'update']);
    Route::delete('/schoolyears/{schoolyear}', [SchoolyearAdminController::class, 'destroy']);

    Route::get('/thesis-sessions', [ThesisSessionAdminController::class, 'index']);
    Route::post('/thesis-sessions', [ThesisSessionAdminController::class, 'store']);
    Route::patch('/thesis-sessions/{thesisSession}', [ThesisSessionAdminController::class, 'update']);
    Route::delete('/thesis-sessions/{thesisSession}', [ThesisSessionAdminController::class, 'destroy']);

    Route::get('/thesis-sessions/{thesisSession}/teacher-board', [TeacherThesisBoardController::class, 'teacherBoard']);
    Route::post('/thesis-sessions/{thesisSession}/supervisions', [TeacherThesisBoardController::class, 'storeSupervision']);
    Route::post('/thesis-sessions/{thesisSession}/supervisions/assign', [TeacherThesisBoardController::class, 'assignSupervision']);
    Route::post('/thesis-sessions/{thesisSession}/supervisions/{supervision}/withdraw', [TeacherThesisBoardController::class, 'withdrawSupervision']);
});
