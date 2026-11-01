<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware(['auth:sanctum', 'active', 'password.change'])
    ->name('user');

Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::put('/password', [SessionController::class, 'updatePassword'])->middleware('active')->name('password.update');

        Route::middleware(['active', 'password.change'])->group(function () {
            Route::get('/me', [SessionController::class, 'show'])->name('me');

            Route::get('/students', [StudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');

            Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
            Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->whereNumber('teacher')->name('teachers.show');

            Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
            Route::get('/classes/{classroom}', [ClassroomController::class, 'show'])->whereNumber('classroom')->name('classes.show');

            Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
            Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->whereNumber('subject')->name('subjects.show');

            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/sessions', [AttendanceController::class, 'sessions'])->name('attendance.sessions');
            Route::post('/attendance/sessions/{attendanceSession}/records', [AttendanceController::class, 'store'])
                ->whereNumber('attendanceSession')
                ->name('attendance.records.store');

            Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
            Route::post('/evaluations/{evaluation}/grades', [GradeController::class, 'store'])
                ->whereNumber('evaluation')
                ->name('grades.store');

            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');

            Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        });
    });
});
