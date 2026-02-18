<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    // GET /api/admin/users?search=&role=&is_active=
    public function index(Request $request)
    {
        $base = User::query()
            ->when($request->search, function ($qq) use ($request) {
                $s = $request->search;
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when($request->role, fn($qq) => $qq->where('role', $request->role))
            ->when(!is_null($request->is_active), fn($qq) => $qq->where(
                'is_active',
                filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
            ));

        $paginated = (clone $base)
            ->orderBy('id', 'desc')
            ->paginate(20, ['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        // Summary counts (global, tidak ikut filter search/role/is_active)
        $summary = [
            'total_users' => User::count(),
            'total_active' => User::where('is_active', true)->count(),
            'total_inactive' => User::where('is_active', false)->count(),
            'total_admin' => User::where('role', 'admin')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $paginated,
            'summary' => $summary,
        ]);
    }

    // POST /api/admin/users
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:user,admin'],
            'is_active' => ['required', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ], 201);
    }

    // GET /api/admin/users/{user}
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    // PATCH /api/admin/users/{user}
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'role' => ['sometimes', 'required', 'in:user,admin'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        // safety: admin jangan bisa menonaktifkan dirinya sendiri
        if (isset($data['is_active']) && $request->user()->id === $user->id && $data['is_active'] === false) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak boleh menonaktifkan akun sendiri.',
            ], 422);
        }

        // safety: admin jangan bisa menurunkan role dirinya sendiri
        if (isset($data['role']) && $request->user()->id === $user->id && $data['role'] !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak boleh mengubah role akun sendiri.',
            ], 422);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        // safety: jangan delete diri sendiri
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak boleh menghapus akun sendiri.',
            ], 422);
        }

        // safety: jangan delete sesama admin
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak boleh menghapus sesama admin.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted',
        ]);
    }
}
