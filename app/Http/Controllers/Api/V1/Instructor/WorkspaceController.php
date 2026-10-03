<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\AssignmentSubmissionStatus;
use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminAssignmentResource;
use App\Http\Resources\Api\V1\AdminAssignmentSubmissionResource;
use App\Http\Resources\Api\V1\AdminQuizAttemptResource;
use App\Http\Resources\Api\V1\CourseResource;
use App\Http\Resources\Api\V1\CourseSectionResource;
use App\Http\Resources\Api\V1\InstructorQuizResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Quiz;
use App\PermissionName;
use App\QuizAttemptStatus;
use App\RoleName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeInstructor($request, PermissionName::CoursesView);
        $counts = Course::query()->where('instructor_id', $request->user()->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published', [CourseStatus::Published->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft', [CourseStatus::Draft->value])
            ->first();
        $awaitingGrading = $request->user()->can(PermissionName::AssignmentSubmissionsView->value)
            ? AssignmentSubmission::query()
                ->whereHas('assignment.course', fn ($query) => $query->where('instructor_id', $request->user()->id))
                ->where('status', AssignmentSubmissionStatus::Submitted)
                ->count()
            : 0;

        return response()->json(['data' => [
            'courses' => (int) $counts->total,
            'published_courses' => (int) $counts->published,
            'draft_courses' => (int) $counts->draft,
            'awaiting_grading' => $awaitingGrading,
        ]]);
    }

    public function courses(Request $request): AnonymousResourceCollection
    {
        $this->authorizeInstructor($request, PermissionName::CoursesView);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(CourseStatus::class)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $courses = Course::query()->where('instructor_id', $request->user()->id)->with('category')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')->orderByDesc('id');

        return CourseResource::collection($courses->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function course(Request $request, int $course): CourseResource
    {
        return new CourseResource($this->ownedCourse($request, $course)->load(['category', 'learningOutcomes', 'requirements']));
    }

    public function curriculum(Request $request, int $course): AnonymousResourceCollection
    {
        $ownedCourse = $this->ownedCourse($request, $course);
        $this->authorizeInstructor($request, PermissionName::CurriculumView);

        return CourseSectionResource::collection($ownedCourse->sections()->with('lessons.resources')->get());
    }

    public function quizzes(Request $request, int $course): AnonymousResourceCollection
    {
        return InstructorQuizResource::collection($this->ownedCourse($request, $course)->quizzes()->orderBy('id')->get());
    }

    public function quiz(Request $request, int $course, int $quiz): InstructorQuizResource
    {
        return new InstructorQuizResource($this->ownedQuiz($request, $course, $quiz));
    }

    public function quizAttempts(Request $request, int $course, int $quiz): AnonymousResourceCollection
    {
        $ownedQuiz = $this->ownedQuiz($request, $course, $quiz);
        Gate::authorize('viewInstructorResults', $ownedQuiz);

        return AdminQuizAttemptResource::collection($ownedQuiz->attempts()->with('user')->paginate(25));
    }

    public function quizAttempt(Request $request, int $course, int $quiz, int $attempt): AdminQuizAttemptResource
    {
        $ownedQuiz = $this->ownedQuiz($request, $course, $quiz);
        Gate::authorize('viewInstructorResults', $ownedQuiz);
        $ownedAttempt = $ownedQuiz->attempts()->with(['user', 'questions.options', 'questions.answer'])->findOrFail($attempt);
        abort_unless($ownedAttempt->status === QuizAttemptStatus::Submitted, 404);

        return new AdminQuizAttemptResource($ownedAttempt);
    }

    public function assignments(Request $request, int $course): AnonymousResourceCollection
    {
        $ownedCourse = $this->ownedCourse($request, $course);
        $this->authorizeInstructor($request, PermissionName::AssignmentSubmissionsView);

        return AdminAssignmentResource::collection($ownedCourse->assignments()->with('attachments')->orderBy('id')->get());
    }

    public function assignment(Request $request, int $course, int $assignment): AdminAssignmentResource
    {
        return new AdminAssignmentResource($this->ownedAssignment($request, $course, $assignment)->load('attachments'));
    }

    public function submissions(Request $request, int $course, int $assignment): AnonymousResourceCollection
    {
        $ownedAssignment = $this->ownedAssignment($request, $course, $assignment);
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(array_column(AssignmentSubmissionStatus::cases(), 'value'))],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return AdminAssignmentSubmissionResource::collection($ownedAssignment->submissions()->with('user')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->paginate($request->integer('per_page', 25))->withQueryString());
    }

    public function submission(Request $request, int $course, int $assignment, int $submission): AdminAssignmentSubmissionResource
    {
        $ownedSubmission = $this->ownedAssignment($request, $course, $assignment)->submissions()
            ->with(['user', 'files', 'grader', 'gradingEvents.reviewer'])->findOrFail($submission);
        Gate::authorize('review', $ownedSubmission);

        return new AdminAssignmentSubmissionResource($ownedSubmission);
    }

    private function ownedCourse(Request $request, int $courseId): Course
    {
        $this->authorizeInstructor($request, PermissionName::CoursesView);

        return Course::query()->where('instructor_id', $request->user()->id)->findOrFail($courseId);
    }

    private function ownedQuiz(Request $request, int $courseId, int $quizId): Quiz
    {
        return $this->ownedCourse($request, $courseId)->quizzes()->findOrFail($quizId);
    }

    private function ownedAssignment(Request $request, int $courseId, int $assignmentId): Assignment
    {
        $ownedCourse = $this->ownedCourse($request, $courseId);
        $this->authorizeInstructor($request, PermissionName::AssignmentSubmissionsView);

        return $ownedCourse->assignments()->findOrFail($assignmentId);
    }

    private function authorizeInstructor(Request $request, PermissionName $permission): void
    {
        abort_unless($request->user()->hasRole(RoleName::Instructor->value) && $request->user()->can($permission->value), 403);
    }
}
