<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserStatisticsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $cacheKey = "dash:stats:user:{$user->id}";
        $ttlSeconds = 30;

        $data = Cache::remember($cacheKey, $ttlSeconds, function () use ($user) {

            // =========================
            // 1) Completed counts
            // =========================
            $completedPracticeCount = DB::table('attempts')
                ->join('packages', 'packages.id', '=', 'attempts.package_id')
                ->where('attempts.user_id', $user->id)
                ->where('attempts.status', 'submitted')
                ->where('packages.type', 'latihan')
                ->count('attempts.id');

            $completedTryoutCount = DB::table('attempts')
                ->join('packages', 'packages.id', '=', 'attempts.package_id')
                ->where('attempts.user_id', $user->id)
                ->where('attempts.status', 'submitted')
                ->where('packages.type', 'tryout')
                ->count('attempts.id');

            // =========================
            // 2) Active packages (from entitlement: user_packages) + free packages
            // =========================
            $now = now();

            $activePackagesEntitled = DB::table('user_packages')
                ->join('packages', 'packages.id', '=', 'user_packages.package_id')
                ->where('user_packages.user_id', $user->id)
                ->select([
                    'packages.id as package_id',
                    'packages.name',
                    'packages.type',
                    'packages.category_id',
                    'categories.type as category_type',
                    'categories.name as category_name',
                    'packages.is_free',
                    'packages.is_active',
                    'user_packages.starts_at',
                    'user_packages.ends_at',
                ])
                ->join('categories', 'categories.id', '=', 'packages.category_id')
                ->orderByDesc('user_packages.id')
                ->limit(50)
                ->get()
                ->map(function ($p) use ($now) {
                    $notStarted = !is_null($p->starts_at) && $now->lt($p->starts_at);
                    $isExpired  = !is_null($p->ends_at) && $now->gt($p->ends_at);

                    $status = $notStarted ? 'upcoming' : ($isExpired ? 'expired' : 'active');

                    return [
                        'package_id' => (int) $p->package_id,
                        'name' => $p->name,
                        'type' => $p->type,
                        'category_id' => (int) $p->category_id,
                        'category_type' => $p->category_type,
                        'category_name' => $p->category_name,
                        'starts_at' => $p->starts_at,
                        'ends_at' => $p->ends_at,
                        'status' => $status,
                        'is_free' => (bool) ($p->is_free ?? false),
                    ];
                })
                ->values();

            // free packages (public access)
            $freePackages = DB::table('packages')
                ->where('is_active', true)
                ->where('is_free', true)
                ->select([
                    'packages.id as package_id',
                    'packages.name',
                    'packages.type',
                    'packages.category_id',
                    'categories.type as category_type',
                    'categories.name as category_name',
                ])
                ->join('categories', 'categories.id', '=', 'packages.category_id')
                ->orderByDesc('packages.id')
                ->limit(50)
                ->get()
                ->map(fn ($p) => [
                    'package_id' => (int) $p->package_id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'category_id' => (int) $p->category_id,
                    'category_type' => $p->category_type,
                    'category_name' => $p->category_name,
                    'starts_at' => null,
                    'ends_at' => null,
                    'status' => 'active',
                    'is_free' => true,
                ])
                ->values();

            // merge + unique (prioritaskan entitlement jika ada duplicate)
            $activePackages = $activePackagesEntitled
                ->merge($freePackages)
                ->unique('package_id')
                ->values();

            // count active only (exclude expired/upcoming)
            $activePackagesCount = $activePackages->where('status', 'active')->count();

            $inProgressCount = DB::table('attempts')
                ->where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->count();

            // =========================
            // 3) Study time (minutes)
            // =========================
            $timeAgg = DB::table('attempts')
                ->where('user_id', $user->id)
                ->selectRaw("
                    SUM(
                        CASE
                            WHEN status = 'submitted' AND submitted_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, started_at, submitted_at)
                            WHEN status = 'expired' THEN TIMESTAMPDIFF(SECOND, started_at, ends_at)
                            ELSE 0
                        END
                    ) as total_seconds
                ")
                ->first();

            $studyTimeMinutes = (int) floor(((int) ($timeAgg->total_seconds ?? 0)) / 60);

            // =========================
            // 4) Recent submitted attempts (score/percent)
            // =========================
            $attemptScoreRows = DB::table('attempts')
                ->where('attempts.user_id', $user->id)
                ->where('attempts.status', 'submitted')
                ->join('packages', 'packages.id', '=', 'attempts.package_id')
                ->select([
                    'attempts.id as attempt_id',
                    'attempts.package_id',
                    'packages.name as package_name',
                    'packages.type as package_type',
                    'attempts.total_score',
                    'attempts.submitted_at',
                    'attempts.started_at',
                ])
                ->orderByDesc('attempts.id')
                ->limit(50)
                ->get();

            $packageIds = $attemptScoreRows->pluck('package_id')->unique()->values()->all();

            $maxScoreByPackage = [];
            foreach ($packageIds as $pid) {
                $pid = (int) $pid;
                $maxScoreByPackage[$pid] = $this->getMaxPackageScoreCached($pid);
            }

            $scorePercents = [];
            foreach ($attemptScoreRows as $a) {
                $pkgId = (int) $a->package_id;
                $max = $maxScoreByPackage[$pkgId] ?? 0;
                $percent = $max > 0 ? (int) round(((int) $a->total_score / $max) * 100) : 0;
                $scorePercents[] = $percent;
            }

            $averageScorePercent = count($scorePercents) > 0
                ? (int) round(array_sum($scorePercents) / count($scorePercents))
                : 0;

            // =========================
            // 5) Recent activity (10 last)
            // =========================
            $recentActivity = [];
            foreach ($attemptScoreRows->take(10) as $a) {
                $attemptId = (int) $a->attempt_id;
                $pkgId = (int) $a->package_id;

                $correctCount = DB::table('attempt_answers')
                    ->where('attempt_id', $attemptId)
                    ->where('score_awarded', '>', 0)
                    ->count();

                $totalQuestions = DB::table('package_questions')
                    ->where('package_id', $pkgId)
                    ->count();

                $max = $maxScoreByPackage[$pkgId] ?? 0;
                $percent = $max > 0 ? (int) round(((int) $a->total_score / $max) * 100) : 0;

                $recentActivity[] = [
                    'attempt_id' => $attemptId,
                    'package_id' => $pkgId,
                    'package_name' => $a->package_name,
                    'package_type' => $a->package_type,
                    'score_percent' => $percent,
                    'correct_count' => $correctCount,
                    'total_questions' => $totalQuestions,
                    'submitted_at' => $a->submitted_at,
                ];
            }

            // =========================
            // 6) Current rank (per last submitted tryout/akbar)
            // =========================
            $rank = null;
            $rankPackageId = null;
            $rankPackageName = null;

            $latestRankAttempt = DB::table('attempts')
                ->join('packages', 'packages.id', '=', 'attempts.package_id')
                ->where('attempts.user_id', $user->id)
                ->where('attempts.status', 'submitted')
                ->whereIn('packages.type', ['tryout', 'akbar'])
                ->orderByDesc('attempts.submitted_at')
                ->select('attempts.package_id', 'packages.name as package_name')
                ->first();

            if ($latestRankAttempt) {
                $rankPackageId = (int) $latestRankAttempt->package_id;
                $rankPackageName = $latestRankAttempt->package_name;
                $rank = $this->getMyRankForPackage((int) $user->id, $rankPackageId);
            }

            // =========================
            // 7) Learning progress (bar)
            // =========================
            $practiceProgress = min(100, (int) round(($completedPracticeCount / max(1, 30)) * 100));
            $tryoutProgress   = min(100, (int) round(($completedTryoutCount / max(1, 10)) * 100));
            $materialsProgress = 0;

            return [
                'summary' => [
                    'active_packages' => $activePackagesCount,
                    'in_progress_attempts' => $inProgressCount,
                    'completed_practices' => $completedPracticeCount,
                    'completed_tryouts' => $completedTryoutCount,
                    'average_score_percent' => $averageScorePercent,
                    'current_rank' => $rank,
                    'rank_package_id' => $rankPackageId,
                    'rank_package_name' => $rankPackageName,
                    'study_time_minutes' => $studyTimeMinutes,
                ],
                'learning_progress' => [
                    'practice_questions_percent' => $practiceProgress,
                    'tryout_completion_percent' => $tryoutProgress,
                    'materials_studied_percent' => $materialsProgress,
                ],
                'active_packages' => $activePackages,
                'recent_activity' => $recentActivity,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function getMaxPackageScoreCached(int $packageId): int
    {
        $key = "pkg:max_score:{$packageId}";
        $ttlSeconds = 21600;

        return (int) Cache::remember($key, $ttlSeconds, function () use ($packageId) {
            $row = DB::table('package_questions')
                ->where('package_questions.package_id', $packageId)
                ->joinSub(
                    DB::table('question_options')
                        ->select('question_id', DB::raw('MAX(score_value) as max_score'))
                        ->groupBy('question_id'),
                    'qmax',
                    'qmax.question_id',
                    '=',
                    'package_questions.question_id'
                )
                ->select(DB::raw('SUM(qmax.max_score) as max_package_score'))
                ->first();

            return (int) ($row->max_package_score ?? 0);
        });
    }

    private function getMyRankForPackage(int $userId, int $packageId): ?int
    {
        $cacheKey = "rank:user:{$userId}:pkg:{$packageId}";
        $ttl = 30;

        return Cache::remember($cacheKey, $ttl, function () use ($userId, $packageId) {

            $myBest = DB::selectOne("
                WITH best AS (
                    SELECT
                        a.user_id,
                        a.total_score,
                        a.submitted_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY a.user_id
                            ORDER BY a.total_score DESC, a.submitted_at ASC, a.id ASC
                        ) AS rn
                    FROM attempts a
                    JOIN packages p ON p.id = a.package_id
                    WHERE a.package_id = ?
                      AND a.status IN ('submitted','expired')
                      AND a.submitted_at IS NOT NULL
                      AND p.type IN ('tryout','akbar')
                )
                SELECT total_score, submitted_at
                FROM best
                WHERE rn = 1 AND user_id = ?
                LIMIT 1
            ", [$packageId, $userId]);

            if (!$myBest) return null;

            $better = DB::selectOne("
                WITH best AS (
                    SELECT
                        a.user_id,
                        a.total_score,
                        a.submitted_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY a.user_id
                            ORDER BY a.total_score DESC, a.submitted_at ASC, a.id ASC
                        ) AS rn
                    FROM attempts a
                    JOIN packages p ON p.id = a.package_id
                    WHERE a.package_id = ?
                      AND a.status IN ('submitted','expired')
                      AND a.submitted_at IS NOT NULL
                      AND p.type IN ('tryout','akbar')
                )
                SELECT COUNT(*) AS cnt
                FROM best
                WHERE rn = 1
                  AND (
                    total_score > ?
                    OR (total_score = ? AND submitted_at < ?)
                  )
            ", [$packageId, $myBest->total_score, $myBest->total_score, $myBest->submitted_at]);

            return 1 + (int) ($better->cnt ?? 0);
        });
    }
}
