<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /** Dummy-Lehrpersonen je Rolle. Passwort für alle: 6 Zeichen Klartext (wie Legacy). */
    public function run(): void
    {
        $rows = [
            ['token' => 'deaktiv', 'first_name' => 'Dora', 'last_name' => 'Deaktiviert', 'email' => 'deaktiv@example.test', 'status' => 0],
            ['token' => 'lehrer', 'first_name' => 'Lara', 'last_name' => 'Lehrerin', 'email' => 'lehrer@example.test', 'status' => 1],
            ['token' => 'sonder', 'first_name' => 'Sven', 'last_name' => 'Sonder', 'email' => 'sonder@example.test', 'status' => 2],
            ['token' => 'schule', 'first_name' => 'Saskia', 'last_name' => 'Schulleitung', 'email' => 'schule@example.test', 'status' => 3],
            ['token' => 'gott', 'first_name' => 'Gott', 'last_name' => 'Admin', 'email' => 'gott@example.test', 'status' => 4],
        ];

        foreach ($rows as $row) {
            Teacher::updateOrCreate(
                ['token' => $row['token']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'status' => $row['status'],
                    'password' => 'devpwd',
                ]
            );
        }
    }
}
