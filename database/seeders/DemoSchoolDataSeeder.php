<?php

namespace Database\Seeders;

use App\Enums\AttendanceSessionType;
use App\Enums\AttendanceStatus;
use App\Enums\ClassroomStatus;
use App\Enums\DayOfWeek;
use App\Enums\EnrollmentStatus;
use App\Enums\EvaluationType;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Communications\CommunicationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSchoolDataSeeder extends Seeder
{
    public function run(CommunicationService $communications): void
    {
        if (app()->isProduction()) {
            return;
        }

        DB::transaction(function () use ($communications): void {
            $schoolYear = SchoolYear::current();

            if ($schoolYear === null) {
                throw new RuntimeException('Une année scolaire active est nécessaire pour générer les données de démonstration.');
            }

            $admin = $this->demoUser('admin@centre.test', Role::Admin);
            $teacherUser = $this->demoUser('enseignant@centre.test', Role::Teacher);
            $guardianUser = $this->demoUser('parent@centre.test', Role::Guardian);
            $studentUser = $this->demoUser('eleve@centre.test', Role::Student);
            $teacher = $this->teacherProfile($teacherUser);
            $guardian = $this->guardianProfile($guardianUser);
            $students = [
                $this->studentProfile('ELV-DEMO-0001', $guardian, $studentUser, 'Awa', 'DÉMO'),
                $this->studentProfile('ELV-DEMO-0002', $guardian, null, 'Koffi', 'DÉMO'),
            ];
            $levels = Level::query()->whereIn('name', ['6ème', '5ème'])->get()->keyBy('name');
            $rooms = collect(['Salle 1', 'Salle 2'])
                ->mapWithKeys(fn (string $name): array => [$name => Room::query()->firstOrCreate(
                    ['name' => $name],
                    ['capacity' => 40],
                )]);
            $subject = Subject::query()->firstOrCreate(
                ['code' => 'MATHDEMO'],
                ['name' => 'Mathématiques'],
            );
            $demoDate = $schoolYear->start_date->toImmutable();
            $classrooms = [];

            foreach ([
                ['name' => '6ème A', 'level' => '6ème', 'room' => 'Salle 1', 'student' => $students[0]],
                ['name' => '5ème A', 'level' => '5ème', 'room' => 'Salle 2', 'student' => $students[1]],
            ] as $index => $definition) {
                $level = $levels->get($definition['level']);

                if ($level === null) {
                    throw new RuntimeException("Le niveau de démonstration {$definition['level']} n’existe pas.");
                }

                FeeSchedule::query()->firstOrCreate(
                    ['school_year_id' => $schoolYear->id, 'level_id' => $level->id],
                    ['monthly_amount' => 12000],
                );

                $classroom = Classroom::query()->firstOrNew([
                    'school_year_id' => $schoolYear->id,
                    'name' => $definition['name'],
                ]);
                $classroom->forceFill([
                    'level_id' => $level->id,
                    'capacity' => 35,
                    'room_id' => $rooms->get($definition['room'])->id,
                    'head_teacher_id' => $teacher->id,
                    'status' => ClassroomStatus::Active,
                ])->save();
                $classrooms[] = $classroom;

                $assignment = TeachingAssignment::query()
                    ->where('classroom_id', $classroom->id)
                    ->where('subject_id', $subject->id)
                    ->first() ?? new TeachingAssignment;
                $assignment->forceFill([
                    'classroom_id' => $classroom->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'coefficient' => 2,
                    'weekly_hours' => 4,
                ])->save();

                $enrollment = Enrollment::query()
                    ->where('student_id', $definition['student']->id)
                    ->where('school_year_id', $schoolYear->id)
                    ->first() ?? new Enrollment;
                $enrollment->forceFill([
                    'student_id' => $definition['student']->id,
                    'classroom_id' => $classroom->id,
                    'school_year_id' => $schoolYear->id,
                    'enrolled_at' => $schoolYear->start_date->toDateString(),
                    'ended_at' => null,
                    'status' => EnrollmentStatus::Active,
                ])->save();

                $this->seedSchedule($classroom, $assignment, $rooms->get($definition['room']), $admin, $index);
                $evaluation = $this->seedEvaluation($classroom, $assignment, $teacher, $admin, $demoDate);
                $this->seedGrade($evaluation, $enrollment, $teacherUser, $index === 0 ? 16 : 13);
                $this->seedAttendance($classroom, $teacher, $admin, $enrollment, $demoDate, $index === 0);
                $this->seedPayment($enrollment, $admin, $demoDate, $index === 0 ? 12000 : 6000, $index);
            }

            $this->seedAnnouncement($classrooms[0], $admin, $communications);
        });
    }

    private function seedAnnouncement(
        Classroom $classroom,
        User $admin,
        CommunicationService $communications,
    ): void {
        $title = 'Annonce fictive — classe de démonstration';

        if (Announcement::query()
            ->where('classroom_id', $classroom->id)
            ->where('title', $title)
            ->exists()) {
            return;
        }

        $communications->publishAnnouncement([
            'title' => $title,
            'body' => 'Ceci est un contenu de démonstration destiné à vérifier l’affichage des annonces et des notifications. Il ne correspond pas à une communication réelle de l’établissement.',
            'classroom_id' => $classroom->id,
        ], $admin);
    }

    private function demoUser(string $email, Role $role): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        if ($user->role !== $role) {
            throw new RuntimeException("Le compte de démonstration {$email} doit avoir le rôle {$role->value}.");
        }

        return $user;
    }

    private function teacherProfile(User $user): Teacher
    {
        $teacher = Teacher::query()->where('user_id', $user->id)->first() ?? Teacher::factory()->make();
        $teacher->forceFill([
            'user_id' => $user->id,
            'first_name' => 'Enseignant',
            'last_name' => 'DÉMO',
            'gender' => Gender::Male,
            'specialty' => 'Mathématiques',
            'status' => TeacherStatus::Active,
        ])->save();

        return $teacher;
    }

    private function guardianProfile(User $user): Guardian
    {
        $guardian = Guardian::query()->where('user_id', $user->id)->first() ?? new Guardian;
        $guardian->forceFill([
            'user_id' => $user->id,
            'first_name' => 'Parent',
            'last_name' => 'DÉMO',
            'phone' => '+229 01 90 00 00 00',
            'email' => $user->email,
        ])->save();

        return $guardian;
    }

    private function studentProfile(
        string $matricule,
        Guardian $guardian,
        ?User $user,
        string $firstName,
        string $lastName,
    ): Student {
        $student = Student::query()->where('matricule', $matricule)->first() ?? new Student;
        $student->forceFill([
            'user_id' => $user?->id,
            'guardian_id' => $guardian->id,
            'matricule' => $matricule,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $firstName === 'Awa' ? Gender::Female : Gender::Male,
            'birth_date' => $firstName === 'Awa' ? '2014-03-15' : '2013-08-20',
            'birth_place' => 'Cotonou',
            'guardian_relationship' => GuardianRelationship::Guardian,
            'status' => StudentStatus::Active,
            'qr_token' => 'DEMO-'.str_replace('-', '', $matricule),
        ])->save();

        return $student;
    }

    private function seedSchedule(
        Classroom $classroom,
        TeachingAssignment $assignment,
        Room $room,
        User $admin,
        int $index,
    ): void {
        $schedule = Schedule::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('day_of_week', DayOfWeek::Monday->value)
            ->where('starts_at', $index === 0 ? '08:00:00' : '10:00:00')
            ->first() ?? new Schedule;
        $schedule->forceFill([
            'classroom_id' => $classroom->id,
            'teaching_assignment_id' => $assignment->id,
            'room_id' => $room->id,
            'created_by' => $admin->id,
            'day_of_week' => DayOfWeek::Monday,
            'starts_at' => $index === 0 ? '08:00:00' : '10:00:00',
            'ends_at' => $index === 0 ? '09:00:00' : '11:00:00',
        ])->save();
    }

    private function seedEvaluation(
        Classroom $classroom,
        TeachingAssignment $assignment,
        Teacher $teacher,
        User $admin,
        CarbonImmutable $date,
    ): Evaluation {
        $evaluation = Evaluation::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('title', 'Évaluation de démonstration')
            ->first() ?? new Evaluation;
        $evaluation->forceFill([
            'classroom_id' => $classroom->id,
            'teaching_assignment_id' => $assignment->id,
            'teacher_id' => $teacher->id,
            'created_by' => $admin->id,
            'title' => 'Évaluation de démonstration',
            'type' => EvaluationType::Quiz,
            'evaluation_date' => $date->toDateString(),
            'coefficient' => 1,
            'subject_coefficient' => 2,
            'published_at' => $date->startOfDay(),
            'published_by' => $admin->id,
        ])->save();

        return $evaluation;
    }

    private function seedGrade(Evaluation $evaluation, Enrollment $enrollment, User $teacher, int $score): void
    {
        $grade = Grade::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('enrollment_id', $enrollment->id)
            ->first() ?? new Grade;
        $grade->forceFill([
            'evaluation_id' => $evaluation->id,
            'enrollment_id' => $enrollment->id,
            'classroom_id' => $evaluation->classroom_id,
            'entered_by' => $teacher->id,
            'score' => $score,
            'comment' => 'Résultat de démonstration',
        ])->save();
    }

    private function seedAttendance(
        Classroom $classroom,
        Teacher $teacher,
        User $admin,
        Enrollment $enrollment,
        CarbonImmutable $date,
        bool $present,
    ): void {
        $session = AttendanceSession::query()
            ->where('classroom_id', $classroom->id)
            ->where('session_type', AttendanceSessionType::Daily->value)
            ->whereDate('session_date', $date->toDateString())
            ->first() ?? new AttendanceSession;
        $session->forceFill([
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'teacher_id' => $teacher->id,
            'session_type' => AttendanceSessionType::Daily,
            'session_date' => $date->toDateString(),
            'starts_at' => '00:00:00',
            'ends_at' => null,
            'created_by' => $admin->id,
        ])->save();

        $attendance = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('enrollment_id', $enrollment->id)
            ->first() ?? new Attendance;
        $attendance->forceFill([
            'attendance_session_id' => $session->id,
            'classroom_id' => $classroom->id,
            'enrollment_id' => $enrollment->id,
            'status' => $present ? AttendanceStatus::Present : AttendanceStatus::Absent,
            'arrived_at' => $present ? '08:00:00' : null,
            'note' => null,
            'marked_by' => $admin->id,
        ])->save();
    }

    private function seedPayment(
        Enrollment $enrollment,
        User $admin,
        CarbonImmutable $month,
        int $amount,
        int $index,
    ): void {
        $receipt = sprintf('DEMO-%s-%04d', $month->format('Ym'), $index + 1);
        $payment = Payment::query()->where('receipt_number', $receipt)->first() ?? new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'month' => $month->startOfMonth()->toDateString(),
            'amount' => $amount,
            'method' => $index === 0 ? PaymentMethod::MobileMoney : PaymentMethod::Cash,
            'reference' => $index === 0 ? 'DEMO-MOMO-001' : null,
            'payer_name' => 'Parent Démo',
            'paid_at' => $month->toDateString(),
            'receipt_number' => $receipt,
            'recorded_by' => $admin->id,
        ])->save();
    }
}
