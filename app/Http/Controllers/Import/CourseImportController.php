<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CourseImportController extends Controller
{
    public function showImportForm()
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        return view('imports.courses');
    }

    public function processImport(Request $request)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Remove header
            array_shift($rows);

            $errors = [];
            $successCount = 0;
            $failedCount = 0;

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // Expected columns: group_name (course_name), description, teacher_id
                $data = [
                    'course_name' => $row[0] ?? null,
                    'description' => $row[1] ?? null,
                    'teacher_id' => $row[2] ?? null,
                ];

                $validator = Validator::make($data, [
                    'course_name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    // teacher_id should reference teachers.teacher_id
                    'teacher_id' => 'nullable|integer|exists:teachers,teacher_id',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->all(),
                    ];
                    $failedCount++;

                    continue;
                }

                // Check duplicate by course_name
                $existing = Course::where('course_name', $data['course_name'])->first();
                if ($existing) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['Duplicate course_name found'],
                    ];
                    $failedCount++;

                    continue;
                }

                Course::create([
                    'course_name' => $data['course_name'],
                    'description' => $data['description'] ?? null,
                    'teacher_id' => $data['teacher_id'] ?? null,
                    'department_id' => null,
                ]);

                $successCount++;
            }

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
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="courses_example.csv"',
        ];

        $sampleData = [
            ['course_name', 'description', 'teacher_id'],
            ['Intro to PHP', 'Basic PHP course', '5'],
            ['Advanced Laravel', 'Deep dive into Laravel', '6'],
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
