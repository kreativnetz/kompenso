<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'size:6'],
        ]);

        $teacher = Teacher::query()
            ->where('token', $data['token'])
            ->first();

        if (! $teacher || ! $teacher->isActive()) {
            throw ValidationException::withMessages([
                'token' => ['Ungültige Zugangsdaten.'],
            ]);
        }

        $stored = (string) $teacher->password;
        if (! hash_equals($stored, $data['password'])) {
            throw ValidationException::withMessages([
                'token' => ['Ungültige Zugangsdaten.'],
            ]);
        }

        $teacher->tokens()->delete();
        $plainToken = $teacher->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $plainToken,
            'teacher' => $this->teacherPayload($teacher),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'teacher' => $this->teacherPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'ok']);
    }

    private function teacherPayload(Teacher $teacher): array
    {
        $status = (int) $teacher->status;

        return [
            'id' => $teacher->id,
            'token' => $teacher->token,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'full_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'status' => $status,
            'role' => Teacher::roleLabel($status),
            'abilities' => [
                'manage_teachers' => $status >= 3,
                'assign_admin' => $status === 4,
                'assign_supervisions' => $status >= 3,
            ],
        ];
    }
}
