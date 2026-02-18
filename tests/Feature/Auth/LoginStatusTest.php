<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class LoginStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login()
    {
        $password = 'password123';
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role']
                ]
            ]);
    }

    public function test_inactive_user_cannot_login()
    {
        $password = 'password123';
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make($password),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => $password,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Silakan hubungi admin.',
            ]);
    }

    public function test_login_fails_with_wrong_password()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Password salah atau email tidak terdaftar.',
            ]);
    }
}
