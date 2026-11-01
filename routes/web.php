<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\Admin\ArrearsController;
use App\Http\Controllers\Web\Admin\CentreSettingsController;
use App\Http\Controllers\Web\Admin\ClassroomController as AdminClassroomController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\EnrollmentController;
use App\Http\Controllers\Web\Admin\EnrollmentStatementController;
use App\Http\Controllers\Web\Admin\FeeDueDateController;
use App\Http\Controllers\Web\Admin\FeeScheduleController;
use App\Http\Controllers\Web\Admin\GuardianAccountController;
use App\Http\Controllers\Web\Admin\GuardianController as AdminGuardianController;
use App\Http\Controllers\Web\Admin\PaymentController;
use App\Http\Controllers\Web\Admin\ReportingController;
use App\Http\Controllers\Web\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Web\Admin\SchoolYearActivationController;
use App\Http\Controllers\Web\Admin\SchoolYearController as AdminSchoolYearController;
use App\Http\Controllers\Web\Admin\SchoolYearPeriodController;
use App\Http\Controllers\Web\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Web\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\Web\Admin\TeacherAccountController;
use App\Http\Controllers\Web\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Web\Admin\TeacherDocumentController;
use App\Http\Controllers\Web\Admin\TeachingAssignmentController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\CommunicationAnnouncementController;
use App\Http\Controllers\Web\CommunicationMessageController;
use App\Http\Controllers\Web\DashboardRedirectController;
use App\Http\Controllers\Web\EvaluationController;
use App\Http\Controllers\Web\Guardian\AttendanceHistoryController;
use App\Http\Controllers\Web\Guardian\DashboardController as GuardianDashboardController;
use App\Http\Controllers\Web\Guardian\GradeHistoryController as GuardianGradeHistoryController;
use App\Http\Controllers\Web\Guardian\PaymentHistoryController as GuardianPaymentHistoryController;
use App\Http\Controllers\Web\Guardian\ScheduleController as GuardianScheduleController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationPreferenceController;
use App\Http\Controllers\Web\ProfilePhotoController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReportCardController;
use App\Http\Controllers\Web\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Web\Student\GradeHistoryController as StudentGradeHistoryController;
use App\Http\Controllers\Web\Student\ScheduleController as StudentScheduleController;
use App\Http\Controllers\Web\StudentPhotoController;
use App\Http\Controllers\Web\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Web\Teacher\ScheduleController as TeacherScheduleController;
use App\Http\Controllers\Web\TeacherPhotoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// "active" : bloque les comptes désactivés. Le logout reste dans routes/auth.php.
Route::middleware(['auth', 'active', 'password.change'])->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'edit'])
        ->name('notifications.preferences.edit');
    Route::patch('/notifications/preferences', [NotificationPreferenceController::class, 'update'])
        ->name('notifications.preferences.update');
    Route::get('/bulletins', [ReportCardController::class, 'index'])->name('report-cards.index');
    Route::get('/inscriptions/{enrollment}/bulletin', [ReportCardController::class, 'show'])
        ->name('report-cards.show');

    Route::prefix('communications')->name('communications.')->middleware('role:admin,guardian')->group(function () {
        Route::get('/announcements', [CommunicationAnnouncementController::class, 'index'])
            ->name('announcements.index');
        Route::get('/announcements/{announcement}', [CommunicationAnnouncementController::class, 'show'])
            ->name('announcements.show');

        Route::prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [CommunicationMessageController::class, 'index'])->name('index');
            Route::get('/create', [CommunicationMessageController::class, 'create'])->name('create');
            Route::post('/', [CommunicationMessageController::class, 'store'])->name('store');
            Route::get('/{conversation}', [CommunicationMessageController::class, 'show'])->name('show');
            Route::post('/{conversation}', [CommunicationMessageController::class, 'reply'])->name('reply');
        });
    });

    Route::prefix('admin/communications')->name('admin.communications.')->middleware('role:admin')->group(function () {
        Route::get('/announcements/create', [CommunicationAnnouncementController::class, 'create'])
            ->name('announcements.create');
        Route::post('/announcements', [CommunicationAnnouncementController::class, 'store'])
            ->name('announcements.store');
    });

    // Profil personnel (la suppression de compte est volontairement retirée).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::middleware('role:admin')->group(function () {
        Route::get('/profile/photo', [ProfilePhotoController::class, 'show'])->name('profile.photo');
        Route::post('/profile/photo', [ProfilePhotoController::class, 'update'])->name('profile.photo.update');
    });

    // Photos privées : accessibles à plusieurs rôles, la Policy décide.
    Route::get('/students/{student}/photo', StudentPhotoController::class)->name('students.photo');
    Route::get('/teachers/{teacher}/photo', TeacherPhotoController::class)->name('teachers.photo');

    // Reçu PDF (Phase 9) : admin ET parent concerné. PaymentPolicy::downloadReceipt décide.
    Route::get('/payments/{payment}/receipt', ReceiptController::class)->name('payments.receipt');

    // Phase 10 : présences, partagées entre l'admin et les enseignants. Les Policies limitent chaque enseignant
    // à ses propres séances ; le parent et l'élève n'ont pas accès à ces écrans (leurs espaces : Phase 14).
    Route::prefix('presences')->name('attendance.')->middleware('role:admin,teacher')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/nouvelle', [AttendanceController::class, 'create'])->name('create');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');
        Route::get('/export', [AttendanceController::class, 'export'])->name('export');
        Route::get('/{attendanceSession}', [AttendanceController::class, 'show'])->name('show');
        Route::put('/{attendanceSession}', [AttendanceController::class, 'update'])->name('update');
        // Limite de débit : même authentifié, on ne laisse pas deviner des jetons QR à la chaîne.
        Route::post('/{attendanceSession}/scan', [AttendanceController::class, 'scan'])
            ->middleware('throttle:120,1')->name('scan');
    });

    Route::prefix('notes')->name('grades.')->middleware('role:admin,teacher')->group(function () {
        Route::get('/', [EvaluationController::class, 'index'])->name('index');
        Route::get('/nouvelle', [EvaluationController::class, 'create'])->name('create');
        Route::post('/', [EvaluationController::class, 'store'])->name('store');
        Route::post('/{evaluation}/publish', [EvaluationController::class, 'publish'])->name('publish');
        Route::get('/{evaluation}', [EvaluationController::class, 'show'])->name('show');
        Route::put('/{evaluation}', [EvaluationController::class, 'update'])->name('update');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/settings/centre', [CentreSettingsController::class, 'edit'])->name('settings.centre.edit');
        Route::put('/settings/centre', [CentreSettingsController::class, 'update'])->name('settings.centre.update');
        Route::get('/settings/centre/logo', [CentreSettingsController::class, 'logo'])->name('settings.centre.logo');
        Route::get('/reports', [ReportingController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportingController::class, 'export'])->name('reports.export');

        // Phase 6
        Route::resource('students', AdminStudentController::class)->withTrashed(['show']);
        Route::resource('guardians', AdminGuardianController::class);
        Route::post('guardians/{guardian}/account', [GuardianAccountController::class, 'store'])
            ->name('guardians.account.store');

        // Phase 7
        Route::resource('teachers', AdminTeacherController::class);
        Route::post('teachers/{teacher}/documents', [TeacherDocumentController::class, 'store'])
            ->name('teachers.documents.store');
        Route::get('teachers/{teacher}/documents/{document}', [TeacherDocumentController::class, 'show'])
            ->scopeBindings()
            ->name('teachers.documents.show');
        Route::post('teachers/{teacher}/account', [TeacherAccountController::class, 'store'])
            ->name('teachers.account.store');
        Route::patch('teachers/{teacher}/account', [TeacherAccountController::class, 'update'])
            ->name('teachers.account.update');
        Route::post('teachers/{teacher}/account/resend-setup-link', [TeacherAccountController::class, 'resendSetupLink'])
            ->name('teachers.account.resend-setup-link');

        Route::resource('subjects', AdminSubjectController::class)->except(['show', 'create']);

        Route::resource('classrooms', AdminClassroomController::class);
        Route::resource('schedules', AdminScheduleController::class)->except(['show']);
        Route::post('classrooms/{classroom}/assignments', [TeachingAssignmentController::class, 'store'])
            ->name('classrooms.assignments.store');
        Route::resource('assignments', TeachingAssignmentController::class)->only(['edit', 'update', 'destroy']);

        // Phase 8
        Route::resource('school-years', AdminSchoolYearController::class)
            ->except(['show'])
            ->parameters(['school-years' => 'schoolYear']);
        Route::post('school-years/{schoolYear}/activate', [SchoolYearActivationController::class, 'store'])
            ->name('school-years.activate');
        Route::get('academic-periods', [SchoolYearPeriodController::class, 'index'])
            ->name('academic-periods.index');
        Route::get('academic-periods/{schoolYear}/edit', [SchoolYearPeriodController::class, 'edit'])
            ->name('academic-periods.edit');
        Route::put('academic-periods/{schoolYear}', [SchoolYearPeriodController::class, 'update'])
            ->name('academic-periods.update');

        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('students/{student}/enrollments/create', [EnrollmentController::class, 'create'])
            ->name('students.enrollments.create');
        Route::post('students/{student}/enrollments', [EnrollmentController::class, 'store'])
            ->name('students.enrollments.store');
        Route::get('enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transferForm'])
            ->name('enrollments.transfer.create');
        Route::post('enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer'])
            ->name('enrollments.transfer.store');
        Route::post('enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])
            ->name('enrollments.withdraw');

        // Phase 9 : tarifs, paiements, échéanciers, impayés
        Route::get('fee-schedules', [FeeScheduleController::class, 'index'])->name('fee-schedules.index');
        Route::post('fee-schedules', [FeeScheduleController::class, 'store'])->name('fee-schedules.store');
        Route::delete('fee-schedules/{feeSchedule}', [FeeScheduleController::class, 'destroy'])->name('fee-schedules.destroy');

        // Pas de edit/update/destroy sur les paiements : on annule, on ne modifie ni ne supprime jamais.
        Route::get('payments/arrears', ArrearsController::class)->name('payments.arrears');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
        Route::get('enrollments/{enrollment}/statement', EnrollmentStatementController::class)
            ->name('enrollments.statement');
        Route::patch('enrollments/{enrollment}/due-date', [FeeDueDateController::class, 'update'])
            ->name('enrollments.due-date');
    });

    Route::patch('/notes/corrections/{grade}', [EvaluationController::class, 'correctGrade'])
        ->middleware('role:admin')
        ->name('grades.corrections.store');

    Route::prefix('enseignant')->name('teacher.')->middleware('role:teacher')->group(function () {
        Route::get('/dashboard', TeacherDashboardController::class)->name('dashboard');
        Route::get('/emploi-du-temps', TeacherScheduleController::class)->name('schedules.index');
    });

    Route::prefix('parent')->name('guardian.')->middleware('role:guardian')->group(function () {
        Route::get('/dashboard', GuardianDashboardController::class)->name('dashboard');
        Route::get('/paiements', GuardianPaymentHistoryController::class)->name('payments.index');
        Route::get('/presences', AttendanceHistoryController::class)->name('attendance.index');
        Route::get('/presences/export', [AttendanceHistoryController::class, 'export'])->name('attendance.export');
        Route::get('/notes', GuardianGradeHistoryController::class)->name('grades.index');
        Route::get('/emploi-du-temps', GuardianScheduleController::class)->name('schedules.index');
    });

    Route::prefix('eleve')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/dashboard', StudentDashboardController::class)->name('dashboard');
        Route::get('/notes', StudentGradeHistoryController::class)->name('grades.index');
        Route::get('/emploi-du-temps', StudentScheduleController::class)->name('schedules.index');
    });
});

require __DIR__.'/auth.php';
