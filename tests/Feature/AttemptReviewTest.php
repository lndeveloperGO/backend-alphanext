<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_forbidden_without_answer_key_access()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::create(['name' => 'Review Cat']);
        $package = Package::create([
            'name' => 'Review Pkg',
            'type' => 'latihan',
            'category_id' => $category->id,
            'duration_seconds' => 3600,
        ]);

        $attempt = Attempt::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'submitted',
            'started_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        // No UserPackage record or record with has_answer_key = false
        UserPackage::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => 1,
            'has_answer_key' => false,
        ]);

        $response = $this->getJson("/api/attempts/{$attempt->id}/review");

        $response->assertStatus(403);
    }

    public function test_review_is_granted_with_answer_key_access()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::create(['name' => 'Review Cat']);
        $package = Package::create([
            'name' => 'Review Pkg',
            'type' => 'latihan',
            'category_id' => $category->id,
            'duration_seconds' => 3600,
        ]);

        $q1 = Question::create(['category_id' => $category->id, 'question' => 'Q1', 'explanation' => 'Explain 1']);
        $opt1 = QuestionOption::create([
            'question_id' => $q1->id,
            'label' => 'A',
            'text' => 'Correct',
            'score_value' => 5
        ]);

        $package->questions()->attach($q1->id, ['order_no' => 1]);

        $attempt = Attempt::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'submitted',
            'started_at' => now(),
            'ends_at' => now()->addHour(),
            'total_score' => 5,
        ]);

        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'selected_option_id' => $opt1->id,
            'score_awarded' => 5,
            'answered_at' => now(),
        ]);

        UserPackage::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => 1,
            'has_answer_key' => true,
        ]);

        $response = $this->getJson("/api/attempts/{$attempt->id}/review");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'attempt_id' => $attempt->id,
                    'results' => [
                        [
                            'no' => 1,
                            'explanation' => 'Explain 1',
                            'options' => [
                                [
                                    'id' => $opt1->id,
                                    'is_correct' => true
                                ]
                            ],
                            'user_answer' => [
                                'selected_option_id' => $opt1->id,
                                'is_correct' => true
                            ]
                        ]
                    ]
                ]
            ]);
    }
}
