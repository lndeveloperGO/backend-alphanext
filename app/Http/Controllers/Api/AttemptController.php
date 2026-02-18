<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Package;
use App\Services\AttemptService;
use Illuminate\Http\Request;
use App\Models\UserPackage;

class AttemptController extends Controller
{
    public function __construct(private AttemptService $attempts) {}

    // POST /api/packages/{package}/attempts
    public function start(Request $request, Package $package)
    {
        abort_unless($package->is_active, 404);

        if (!$package->is_free) {
            $hasAccess = UserPackage::where('user_id', $request->user()->id)
                ->where('package_id', $package->id)
                ->where(function ($q) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->exists();

            abort_if(!$hasAccess, 403, 'Paket belum dibeli atau sudah kadaluarsa.');
        }

        $attempt = $this->attempts->startAttempt($request->user()->id, $package);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'package_id' => $attempt->package_id,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'ends_at' => $attempt->ends_at,
                'remaining_seconds' => $this->attempts->remainingSeconds($attempt),
            ]
        ], 201);
    }


    // GET /api/attempts/{attempt}
    public function show(Request $request, Attempt $attempt)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        $attempt = $this->attempts->ensureNotExpired($attempt);

        $agg = $attempt->answers()
            ->selectRaw("COUNT(*) as total,
                         SUM(CASE WHEN selected_option_id IS NOT NULL THEN 1 ELSE 0 END) as done,
                         SUM(CASE WHEN is_marked = 1 THEN 1 ELSE 0 END) as marked")
            ->first();

        $totalQuestions = (int) ($agg->total ?? 0);
        $done = (int) ($agg->done ?? 0);
        $marked = (int) ($agg->marked ?? 0);


        $hasAnswerKey = UserPackage::where('user_id', $request->user()->id)
            ->where('package_id', $attempt->package_id)
            ->where('has_answer_key', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'package_id' => $attempt->package_id,
                'status' => $attempt->status,
                'has_answer_key' => $hasAnswerKey,
                'started_at' => $attempt->started_at,
                'ends_at' => $attempt->ends_at,
                'submitted_at' => $attempt->submitted_at,
                'total_score' => $attempt->total_score,
                'remaining_seconds' => $this->attempts->remainingSeconds($attempt),
                'progress' => [
                    'total' => $totalQuestions,
                    'done' => $done,
                    'undone' => max(0, $totalQuestions - $done),
                    'marked' => $marked,
                ],
                'nav' => $attempt->answers()
                    ->orderBy('id')
                    ->get(['question_id', 'selected_option_id', 'is_marked'])
                    ->map(fn($a) => [
                        'question_id' => $a->question_id,
                        'done' => !is_null($a->selected_option_id),
                        'marked' => (bool) $a->is_marked,
                    ]),
            ],
        ]);
    }

    // GET /api/attempts/{attempt}/questions/{no}
    public function question(Request $request, Attempt $attempt, int $no)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        $attempt = $this->attempts->ensureNotExpired($attempt);

        $answers = $attempt->answers()
            ->orderBy('id')
            ->with(['question.options'])
            ->get();

        $index = $no - 1;
        abort_if($index < 0 || $index >= $answers->count(), 404);

        $row = $answers[$index];
        $q = $row->question;

        $hasAnswerKey = UserPackage::where('user_id', $request->user()->id)
            ->where('package_id', $attempt->package_id)
            ->where('has_answer_key', true)
            ->exists();

        $showAnswerKey = $hasAnswerKey && $attempt->status === 'submitted';

        return response()->json([
            'success' => true,
            'data' => [
                'no' => $no,
                'question_id' => $q->id,
                'question' => $q->question,
                'question_type' => $q->question_type,
                'image_url' => $q->image_url,
                'explanation' => $showAnswerKey ? $q->explanation : null,
                'options' => $q->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'label' => $opt->label,
                    'text' => $opt->text,
                    'image_url' => $opt->image_url,
                    'is_correct' => $showAnswerKey ? ($opt->score_value > 0) : null,
                ]),
                'selected_option_id' => $row->selected_option_id,
                'is_marked' => (bool) $row->is_marked,
                'remaining_seconds' => $this->attempts->remainingSeconds($attempt),
                'status' => $attempt->status,
                'has_answer_key' => $hasAnswerKey,
            ],
        ]);
    }

    // POST /api/attempts/{attempt}/answers
    public function answer(Request $request, Attempt $attempt)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['required', 'integer'],
        ]);

        $ans = $this->attempts->answer(
            $attempt,
            $data['question_id'],
            $data['option_id']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'question_id' => $ans->question_id,
                'selected_option_id' => $ans->selected_option_id,
                'score_awarded' => $ans->score_awarded,
                'answered_at' => $ans->answered_at,
            ],
        ]);
    }

    // POST /api/attempts/{attempt}/mark
    public function mark(Request $request, Attempt $attempt)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
        ]);

        $ans = $this->attempts->toggleMark($attempt, $data['question_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'question_id' => $ans->question_id,
                'is_marked' => (bool) $ans->is_marked,
            ],
        ]);
    }

    // POST /api/attempts/{attempt}/submit
    public function submit(Request $request, Attempt $attempt)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        $attempt = $this->attempts->submit($attempt);
        $attempt->load('package');

        $totalQuestions = $attempt->answers()->count();
        $answered = $attempt->answers()->whereNotNull('selected_option_id')->count();
        $unanswered = max(0, $totalQuestions - $answered);

        $correctCount = $attempt->answers()->where('score_awarded', '>', 0)->count();
        $wrongCount = $attempt->answers()
            ->whereNotNull('selected_option_id')
            ->where('score_awarded', 0)
            ->count();

        $accuracy = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;
        $passingScore = (int) ($attempt->package->passing_score ?? 0);
        $isPassed = $attempt->total_score >= $passingScore;

        $progressPercent = $totalQuestions > 0
            ? (int) round(($answered / $totalQuestions) * 100)
            : 0;

        $hasAnswerKey = UserPackage::where('user_id', $request->user()->id)
            ->where('package_id', $attempt->package_id)
            ->where('has_answer_key', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status,
                'has_answer_key' => $hasAnswerKey,
                'submitted_at' => $attempt->submitted_at,
                'total_score' => $attempt->total_score,
                'passing_score' => $passingScore,
                'is_passed' => $isPassed,
                'summary' => [
                    'total_questions' => $totalQuestions,
                    'answered' => $answered,
                    'unanswered' => $unanswered,
                    'correct' => $correctCount,
                    'wrong' => $wrongCount,
                    'accuracy' => $accuracy,
                    'progress_percent' => $progressPercent,
                ],
            ],
        ]);
    }

    // GET /api/attempts/{attempt}/review
    public function review(Request $request, Attempt $attempt)
    {
        $this->authorizeAttemptOwner($request, $attempt);

        // Check if user has answer key access for this package
        $hasAccess = UserPackage::where('user_id', $request->user()->id)
            ->where('package_id', $attempt->package_id)
            ->where('has_answer_key', true)
            ->exists();

        abort_if(!$hasAccess, 403, 'Anda tidak memiliki akses kunci jawaban untuk paket ini.');

        $attempt->load('package');
        $answers = $attempt->answers()
            ->orderBy('id')
            ->with(['question.options'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'package_name' => $attempt->package->name,
                'total_score' => $attempt->total_score,
                'status' => $attempt->status,
                'submitted_at' => $attempt->submitted_at,
                'results' => $answers->map(function ($ans, $idx) {
                    $q = $ans->question;
                    return [
                        'no' => $idx + 1,
                        'question_id' => $q->id,
                        'question' => $q->question,
                        'question_type' => $q->question_type,
                        'image_url' => $q->image_url,
                        'explanation' => $q->explanation,
                        'options' => $q->options->map(fn($opt) => [
                            'id' => $opt->id,
                            'label' => $opt->label,
                            'text' => $opt->text,
                            'image_url' => $opt->image_url,
                            'is_correct' => $opt->score_value > 0,
                        ]),
                        'user_answer' => [
                            'selected_option_id' => $ans->selected_option_id,
                            'score_awarded' => $ans->score_awarded,
                            'is_correct' => $ans->score_awarded > 0,
                            'answered_at' => $ans->answered_at,
                        ],
                    ];
                }),
            ],
        ]);
    }

    // GET /api/user/attempts
    public function history(Request $request)
    {
        $rows = Attempt::query()
            ->where('attempts.user_id', $request->user()->id)
            ->join('packages', 'packages.id', '=', 'attempts.package_id')
            ->leftJoin('user_packages', function ($join) use ($request) {
                $join->on('user_packages.package_id', '=', 'attempts.package_id')
                    ->where('user_packages.user_id', '=', $request->user()->id);
            })
            ->select([
                'attempts.id',
                'attempts.package_id',
                'attempts.status',
                'attempts.started_at',
                'attempts.ends_at',
                'attempts.submitted_at',
                'attempts.total_score',
                'attempts.created_at',
                'attempts.updated_at',
                'packages.name as package_name',
                'packages.type as package_type',
                'packages.category_id as package_category_id',
                'user_packages.has_answer_key',
            ])
            ->orderByDesc('attempts.id')
            ->paginate(20);

        // ubah bentuk biar rapi (optional)
        $rows->getCollection()->transform(function ($r) {
            return [
                'id' => (int) $r->id,
                'package_id' => (int) $r->package_id,
                'status' => $r->status,
                'has_answer_key' => (bool) $r->has_answer_key,
                'started_at' => $r->started_at,
                'ends_at' => $r->ends_at,
                'submitted_at' => $r->submitted_at,
                'total_score' => (int) $r->total_score,
                'package' => [
                    'id' => (int) $r->package_id,
                    'name' => $r->package_name,
                    'type' => $r->package_type,
                    'category_id' => (int) $r->package_category_id,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function authorizeAttemptOwner(Request $request, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
