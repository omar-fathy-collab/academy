<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;

use App\Models\ImportLog;
use App\Models\Profile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserImportController extends Controller
{
    public function showImportForm()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('imports.users');
    }

    public function processImport(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        try {
            // Load the spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Remove header row
            array_shift($rows);

            $errors = [];
            $successCount = 0;
            $failedCount = 0;

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because array is 0-based and header removed

                // Validate row data - تم تعديل قاعدة email
                $validator = Validator::make([
                    'username' => $row[0] ?? null,
                    'email' => $row[1] ?? null,
                    'pass' => $row[2] ?? null,
                    'role_id' => $row[3] ?? null,
                    'is_active' => $row[4] ?? null,
                    'nickname' => $row[5] ?? null,
                    'date_of_birth' => $row[6] ?? null,
                    'phone_number' => $row[7] ?? null,
                ], [
                    'username' => 'required|string|max:255',
                    'email' => 'required|string|max:255', // تم تغييرها من email إلى string
                    'pass' => 'required|string|min:8',
                    'role_id' => 'required|integer|exists:roles,id',
                    'is_active' => 'required|in:0,1',
                    'nickname' => 'nullable|string|max:255',
                    'date_of_birth' => 'nullable|date',
                    'phone_number' => 'nullable|string|max:20',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->all(),
                    ];
                    $failedCount++;

                    continue;
                }

                // Check for duplicates
                $existingUser = User::where('username', $row[0])
                    ->orWhere('email', $row[1])
                    ->orWhereHas('profile', function ($q) use ($row) {
                        $q->where('phone_number', $row[7]);
                    })
                    ->first();

                if ($existingUser) {
                    $duplicateField = '';
                    if ($existingUser->username == $row[0]) {
                        $duplicateField = 'username';
                    } elseif ($existingUser->email == $row[1]) {
                        $duplicateField = 'email';
                    } elseif ($existingUser->profile && $existingUser->profile->phone_number == $row[7]) {
                        $duplicateField = 'phone_number';
                    }

                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ["Duplicate $duplicateField found"],
                    ];
                    $failedCount++;

                    continue;
                }

                // Insert user
                $user = User::create([
                    'username' => $row[0],
                    'email' => $row[1],
                    'pass' => Hash::make($row[2]),
                    'role_id' => $row[3],
                    'is_active' => $row[4],
                ]);

                // Insert profile
                Profile::create([
                    'user_id' => $user->id,
                    'nickname' => $row[5] ?? null,
                    'date_of_birth' => $row[6] ? date('Y-m-d', strtotime($row[6])) : null,
                    'phone_number' => $row[7] ?? null,
                ]);

                // Handle role-specific inserts
                if ($row[3] == \App\Models\Role::TEACHER_ID) { // Teacher
                    \App\Models\Teacher::create([
                        'user_id' => $user->id,
                        'teacher_name' => $row[5] ?? null,
                    ]);
                } elseif ($row[3] == \App\Models\Role::STUDENT_ID) { // Student
                    \App\Models\Student::create([
                        'user_id' => $user->id,
                        'student_name' => $row[5] ?? null,
                    ]);
                }

                $successCount++;
            }

            // Log the import
            ImportLog::create([
                'file_name' => $fileName,
                'imported_by' => auth()->id(),
                'imported_at' => now(),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import completed',
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function downloadSample()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_example.csv"',
        ];

        // استخدام أمثلة لايميلات غير حقيقية
        $sampleData = [
            ['username', 'email', 'pass', 'role_id', 'is_active', 'nickname', 'date_of_birth', 'phone_number'],
            ['john_doe', 'any_email_1@example.com', 'password123', '2', '1', 'John Doe', '1990-01-01', '1234567890'],
            ['student1', 'test.email@fake.domain', 'password123', '3', '1', 'Student One', '2000-01-01', '1111111111'],
        ];

        $callback = function () use ($sampleData) {
            $file = fopen('php://output', 'w');
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
