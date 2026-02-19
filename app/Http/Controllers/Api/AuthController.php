<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Notifications\ResetPasswordNotification;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $normalizedPhone = null;

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],

            'phone' => [
                'required',
                'string',
                'max:30',
                function ($attribute, $value, $fail) use (&$normalizedPhone) {
                    $normalizedPhone = $this->normalizePhone($value);

                    // strict ID format
                    if (!preg_match('/^\+62\d{9,13}$/', $normalizedPhone)) {
                        $fail('Format nomor handphone tidak valid. Gunakan nomor Indonesia (contoh: 0812xxxxxx).');
                        return;
                    }

                }
            ],

            'school_origin' => ['required', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before:today'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // pakai hasil normalisasi dari closure
        $data['phone'] = $normalizedPhone;

        // debug log
        Log::info('REGISTER PHONE', ['raw' => $request->phone, 'normalized' => $data['phone']]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'phone' => $data['phone'],
            'school_origin' => $data['school_origin'],
            'birth_date' => $data['birth_date'],
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'school_origin' => $user->school_origin,
                    'birth_date' => optional($user->birth_date)->toDateString(),
                ],
            ],
        ], 201);
    }


    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak terdaftar.',
            ], 401);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah atau email tidak terdaftar.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Silakan hubungi admin.',
            ], 403);
        }

        // Optional: hapus token lama kalau mau 1 device
        // $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'school_origin' => $user->school_origin,
                'birth_date' => optional($user->birth_date)->toDateString(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30', 'unique:users,phone,' . $user->id],
            'school_origin' => ['sometimes', 'required', 'string', 'max:150'],
            'birth_date' => ['sometimes', 'required', 'date', 'before:today'],
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = $this->normalizePhone($data['phone']);
            $this->assertValidIndonesianPhone($data['phone']);

            $exists = User::where('phone', $data['phone'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => ['Nomor handphone sudah digunakan.'],
                ]);
            }
        }


        $user->update($data);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'school_origin' => $user->school_origin,
                'birth_date' => optional($user->birth_date)->toDateString(),
            ],
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan.',
            ], 404);
        }

        $token = Password::createToken($user);
        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'success' => true,
            'message' => 'Link reset password telah dikirim ke email Anda.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password berhasil diperbarui.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid atau sudah kedaluwarsa.',
        ], 400);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah.',
                'errors' => [
                    'current_password' => ['Password saat ini salah.']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        // buang semua selain angka dan +
        $phone = trim($phone);
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        // kalau diawali 00 (mis 0062) -> +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // jika ada +, buang + untuk proses, nanti kita tambah lagi
        $hasPlus = str_starts_with($phone, '+');
        $digits = $hasPlus ? substr($phone, 1) : $phone;

        // kasus Indonesia:
        // 0812xxxx -> 62812xxxx
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        // 812xxxx (tanpa 0) -> 62812xxxx 
        if (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        // Pastikan mulai dengan 62 untuk Indonesia
        if (!str_starts_with($digits, '62')) {
            // fallback: tetap pakai digits apa adanya
            // tapi tetap tambahkan +
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    private function assertValidIndonesianPhone(string $phone): void
    {
        // Format harus +62 diikuti 9-13 digit (total digit setelah +62 = 9..13)
        if (!preg_match('/^\+62\d{9,13}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Format nomor handphone tidak valid. Gunakan nomor Indonesia (contoh: 0812xxxxxx).'],
            ]);
        }
    }
}
