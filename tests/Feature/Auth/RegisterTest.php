<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_validation_returns_json()
    {
        // Data tidak lengkap (name, email, dll kosong)
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password',
                    'phone',
                    'school_origin',
                    'birth_date',
                ]
            ]);
    }

    public function test_registration_phone_validation_returns_json()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '123456789', // Salah format
            'school_origin' => 'Test School',
            'birth_date' => '2000-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ])
            ->assertJsonStructure([
                'errors' => ['phone']
            ]);
    }
}
