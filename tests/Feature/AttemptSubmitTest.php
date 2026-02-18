<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_returns_detailed_response()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = \App\Models\Category::create(['name' => 'Test Cat']);
        $package = \App\Models\Package::create([
            'name' => 'Test Pkg',
            'type' => 'latihan',
            'category_id' => $category->id,
            'duration_seconds' => 3600,
            'passing_score' => 70,
        ]);

        $q1 = \App\Models\Question::create(['category_id' => $category->id, 'question' => 'Q1']);
        $opt1 = \App\Models\QuestionOption::create([
            'question_id' => $q1->id,
            'label' => 'A',
            'text' => 'Correct',
            'score_value' => 100
        ]);
        $opt2 = \App\Models\QuestionOption::create([
            'question_id' => $q1->id,
            'label' => 'B',
            'text' => 'Wrong',
            'score_value' => 0
        ]);

        $q2 = \App\Models\Question::create(['category_id' => $category->id, 'question' => 'Q2']);
        $opt3 = \App\Models\QuestionOption::create([
            'question_id' => $q2->id,
            'label' => 'A',
            'text' => 'Correct',
            'score_value' => 100
        ]);

        $package->questions()->attach($q1->id, ['order_no' => 1]);
        $package->questions()->attach($q2->id, ['order_no' => 2]);

        $attempt = \App\Models\Attempt::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        // Answer Q1 correctly
        \App\Models\AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'selected_option_id' => $opt1->id,
            'score_awarded' => 100,
            'answered_at' => now(),
        ]);

        // Answer Q2 wrongly (left unanswered for this test to check counts)
        \App\Models\AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $q2->id,
            'selected_option_id' => null,
            'score_awarded' => 0,
        ]);

        $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'attempt_id' => $attempt->id,
                    'status' => 'submitted',
                    'total_score' => 100,
                    'passing_score' => 70,
                    'is_passed' => true,
                    'summary' => [
                        'total_questions' => 2,
                        'answered' => 1,
                        'unanswered' => 1,
                        'correct' => 1,
                        'wrong' => 0,
                        'accuracy' => 50,
                        'progress_percent' => 50,
                    ]
                ]
            ]);
    }
}
