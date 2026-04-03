<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManager($request);

        $teachers = Teacher::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'teachers' => $teachers->map(fn (Teacher $t) => $this->teacherRow($t)),
        ]);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $this->ensureManager($request);

        $data = $request->validate([
            'status' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4])],
        ]);

        $auth = $request->user();
        $newStatus = (int) $data['status'];

        if ((int) $auth->status === 3) {
            if ((int) $teacher->status === 4) {
                abort(403, 'Schulleitung kann Administratoren nicht bearbeiten.');
            }
            if ($newStatus === 4) {
                abort(403, 'Schulleitung kann niemanden zum Administrator ernennen.');
            }
        }

        $teacher->update(['status' => $newStatus]);
        $teacher->refresh();

        return response()->json([
            'teacher' => $this->teacherRow($teacher),
        ]);
    }

    private function ensureManager(Request $request): void
    {
        if ((int) $request->user()->status < 3) {
            abort(403, 'Keine Berechtigung für die Lehrpersonen-Verwaltung.');
        }
    }

    private function teacherRow(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'token' => $teacher->token,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'full_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'status' => (int) $teacher->status,
            'role' => Teacher::roleLabel((int) $teacher->status),
        ];
    }
}
