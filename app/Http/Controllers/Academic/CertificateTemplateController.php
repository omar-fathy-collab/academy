<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use App\Models\CertificateTemplate;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    public function index()
    {
        $templates = CertificateTemplate::orderBy('created_at', 'desc')->paginate(20);

        return view('certificate-templates.index', [
            'templates' => $templates
        ]);
    }

    public function create()
    {
        return view('certificate-templates.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'html' => 'nullable|string',
            'font_style' => 'nullable|string',
            'text_color' => 'nullable|string',
            'signature_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'seal_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'blade_view' => 'nullable|string',
        ]);

        $data = $request->only([
            'name', 'html', 'font_style', 'text_color', 'is_active', 'blade_view',
        ]);

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            $data['background_image'] = $request->file('background_image')->store('certificate_templates', 'public');
        }

        if ($request->hasFile('signature_image')) {
            $data['signature_image'] = $request->file('signature_image')->store('certificate_templates', 'public');
        }

        if ($request->hasFile('seal_image')) {
            $data['seal_image'] = $request->file('seal_image')->store('certificate_templates', 'public');
        }

        CertificateTemplate::create($data);

        return redirect()->route('certificate_templates.index')
            ->with('success', 'Certificate template created successfully.');
    }

    public function preview($id)
    {
        $template = CertificateTemplate::findOrFail($id);

        // Sample data for preview
        $sampleCertificate = (object) [
            'user' => (object) ['name' => 'John Doe', 'email' => 'john@example.com'],
            'course' => (object) ['course_name' => 'Laravel Development'],
            'group' => (object) ['group_name' => 'Group A'],
            'certificate_number' => 'CERT-SAMPLE123',
            'issue_date' => now(),
            'attendance_percentage' => 95,
            'quiz_average' => 88,
            'final_rating' => 4.5,
            'remarks' => 'Excellent performance',
        ];

        return view('certificate-templates.preview', compact('sampleCertificate', 'template'));
    }

    public function edit($id)
    {
        $template = CertificateTemplate::findOrFail($id);

        return view('certificate-templates.edit', [
            'template' => $template
        ]);
    }

    public function update(Request $request, $id)
    {
        $template = CertificateTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'html' => 'nullable|string',
            'font_style' => 'nullable|string',
            'text_color' => 'nullable|string',
            'signature_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'seal_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'blade_view' => 'nullable|string',
        ]);

        $data = $request->only([
            'name', 'html', 'font_style', 'text_color', 'is_active', 'blade_view',
        ]);

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            $data['background_image'] = $request->file('background_image')->store('certificate_templates', 'public');
        }

        if ($request->hasFile('signature_image')) {
            $data['signature_image'] = $request->file('signature_image')->store('certificate_templates', 'public');
        }

        if ($request->hasFile('seal_image')) {
            $data['seal_image'] = $request->file('seal_image')->store('certificate_templates', 'public');
        }

        $template->update($data);

        return redirect()->route('certificate_templates.index')
            ->with('success', 'Certificate template updated successfully.');
    }

    public function destroy($id)
    {
        $template = CertificateTemplate::findOrFail($id);
        $template->delete();

        return redirect()->route('certificate_templates.index')
            ->with('success', 'Certificate template deleted successfully.');
    }
}
