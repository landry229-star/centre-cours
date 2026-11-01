<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeEntriesRequest;
use App\Http\Resources\Api\V1\GradeResource;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\User;
use App\Services\Grades\GradebookService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user->isAdmin()
            && ! $user->isTeacher()
            && ! $user->isGuardian()
            && ! $user->isStudent()) {
            abort(403);
        }

        return GradeResource::collection(
            $this->visibleGrades($user)
                ->with(['evaluation.assignment.subject', 'enrollment.student'])
                ->orderByDesc('evaluation_id')
                ->orderBy('id')
                ->paginate(50),
        );
    }

    public function store(
        GradeEntriesRequest $request,
        Evaluation $evaluation,
        GradebookService $gradebook,
    ): JsonResponse {
        try {
            $gradebook->recordGrades($evaluation, $request->validated('entries'), $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Notes enregistrées.']);
    }

    private function visibleGrades(User $user): Builder
    {
        $query = Grade::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereHas('evaluation', fn (Builder $evaluationQuery) => $evaluationQuery
                ->where('teacher_id', $user->teacher->id));
        }

        if ($user->isGuardian() && $user->guardian !== null) {
            return $query->whereHas('evaluation', fn (Builder $evaluationQuery) => $evaluationQuery
                ->whereNotNull('published_at'))
                ->whereHas('enrollment.student', fn (Builder $studentQuery) => $studentQuery
                    ->whereHas('guardian', fn (Builder $guardianQuery) => $guardianQuery
                        ->where('user_id', $user->id)));
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereHas('evaluation', fn (Builder $evaluationQuery) => $evaluationQuery
                ->whereNotNull('published_at'))
                ->whereHas('enrollment', fn (Builder $enrollmentQuery) => $enrollmentQuery
                    ->where('student_id', $user->student->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
