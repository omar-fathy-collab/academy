<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;

use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GroupImportController extends Controller
{
    public function showImportForm()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('imports.groups');
    }

    public function processImport(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
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

                // Expected columns: group_name, course_id, subcourse_id, teacher_id, schedule, start_date, end_date, price, teacher_percentage, students
                $data = [
                    'group_name' => $row[0] ?? null,
                    'course_id' => $row[1] ?? null,
                    'subcourse_id' => $row[2] ?? null,
                    'teacher_id' => $row[3] ?? null,
                    'schedule' => $row[4] ?? null,
                    'start_date' => $row[5] ?? null,
                    'end_date' => $row[6] ?? null,
                    'price' => $row[7] ?? null,
                    'teacher_percentage' => $row[8] ?? null,
                    'students' => $row[9] ?? null, // student IDs (supports -, \\, |, , or ; separators)
                ];

                $validator = Validator::make($data, [
                    'group_name' => 'required|string|max:255',
                    'course_id' => 'required|integer|exists:courses,course_id',
                    'subcourse_id' => 'nullable|integer|exists:subcourses,subcourse_id',
                    'teacher_id' => 'required|integer|exists:teachers,teacher_id',
                    'schedule' => 'nullable|string|max:255',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date',
                    'price' => 'nullable|numeric',
                    'teacher_percentage' => 'nullable|numeric|min:0|max:100',
                    'students' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];
                    $failedCount++;

                    continue;
                }

                // Create group
                $group = Group::create([
                    'group_name' => $data['group_name'],
                    'course_id' => $data['course_id'],
                    'subcourse_id' => $data['subcourse_id'] ?? null,
                    'teacher_id' => $data['teacher_id'],
                    'schedule' => $data['schedule'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'price' => $data['price'] ?? 0,
                    'teacher_percentage' => $data['teacher_percentage'] ?? null,
                ]);

                // Attach students if provided (backslash-separated ids, e.g. 10\12\15)
                if (! empty($data['students'])) {
                    // Accept several separators: backslash, dash, comma, semicolon, pipe
                    $raw = preg_replace('/[\\\\,;|]+/', '-', $data['students']);
                    $raw = str_replace('–', '-', $raw); // en-dash to dash
                    $parts = explode('-', $raw);
                    $studentIds = array_values(array_filter(array_map(function ($s) {
                        $s = trim($s);

                        return $s === '' ? null : (int) $s;
                    }, $parts)));

                    // Validate student IDs exist
                    $validStudentIds = Student::whereIn('student_id', $studentIds)->pluck('student_id')->toArray();
                    if (! empty($validStudentIds)) {
                        $group->students()->sync($validStudentIds);
                    }

                    // record invalid ids as errors (if any)
                    $invalid = array_diff($studentIds, $validStudentIds);
                    if (! empty($invalid)) {
                        $errors[] = ['row' => $rowNumber, 'errors' => ['Invalid student IDs: '.implode(',', $invalid)]];
                        // proceed creating the group even if some student IDs were invalid
                    }

                    // Create invoices for each valid student (mirror add-group behavior)
                    try {
                        $students = Student::whereIn('student_id', $validStudentIds)->get();
                        foreach ($students as $student) {
                            $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

                            \App\Models\Invoice::create([
                                'student_id' => $student->student_id,
                                'group_id' => $group->group_id,
                                'invoice_number' => $invoiceNumber,
                                'description' => 'Group fee: '.$group->group_name,
                                'amount' => $group->price,
                                'amount_paid' => 0,
                                'due_date' => $group->start_date ? $group->start_date : now()->addDays(7)->toDateString(),
                                'status' => 'pending',
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to create invoices during import for group '.$group->group_id.': '.$e->getMessage());
                        $errors[] = ['row' => $rowNumber, 'errors' => ['Invoice creation failed: '.$e->getMessage()]];
                    }

                    // Create salary entry for the teacher for this group (mirror add-group behavior)
                    try {
                        $studentCount = count($validStudentIds);
                        $groupRevenue = ($group->price ?? 0) * $studentCount;
                        $teacherPerc = $group->teacher_percentage ?? 0;
                        $teacherShare = round((($teacherPerc) / 100) * $groupRevenue, 2);

                        \App\Models\Salary::create([
                            'teacher_id' => $group->teacher_id,
                            'month' => date('Y-m'),
                            'group_id' => $group->group_id,
                            'group_revenue' => $groupRevenue,
                            'teacher_share' => $teacherShare,
                            'deductions' => 0,
                            'bonuses' => 0,
                            'net_salary' => $teacherShare,
                            'status' => 'pending',
                            'payment_date' => null,
                            'updated_by' => auth()->id(),
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to create salary record during import for group '.$group->group_id.': '.$e->getMessage());
                        $errors[] = ['row' => $rowNumber, 'errors' => ['Salary creation failed: '.$e->getMessage()]];
                    }
                }

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

            return response()->json(['success' => true, 'message' => 'Import completed', 'data' => ['success_count' => $successCount, 'failed_count' => $failedCount, 'errors' => $errors]]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['success' => false, 'message' => 'Import failed: '.$e->getMessage()], 500);
        }
    }

    public function downloadSample()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="groups_example.csv"'];

        $sampleData = [
            ['group_name', 'course_id', 'subcourse_id', 'teacher_id', 'schedule', 'start_date', 'end_date', 'price', 'teacher_percentage', 'students'],
            ['Advanced Laravel1', '7', '', '1', 'Mon-Wed 6-8pm', '2025-10-01', '2025-12-31', '1500', '70', '10-12-15'],
            ['Intro to PHP1', '6', '', '2', 'Tue-Thu 5-7pm', '2025-11-01', '2026-01-31', '1200', '80', '8,9,11'],
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
