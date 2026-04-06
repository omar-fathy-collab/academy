<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    /**
     * Display the settings dashboard.
     */
    public function index()
    {
        $settings = [
            'site_name' => setting('site_name', 'ICT Academy'),
            'site_logo' => setting('site_logo', '/img/ictlogo1.png'),
            'site_font' => setting('site_font', 'Outfit, Inter, sans-serif'),
            'theme_template' => setting('theme_template', 'default'),
            'primary_color' => setting('primary_color', '#0d6efd'),
            'button_color' => setting('button_color', '#0d6efd'),
            'button_hover_color' => setting('button_hover_color', '#0b5ed7'),
            'bg_color_light' => setting('bg_color_light', '#f8fafc'),
            'bg_color_dark' => setting('bg_color_dark', '#0f172a'),
            'text_color_light' => setting('text_color_light', '#0f172a'),
            'text_color_dark' => setting('text_color_dark', '#f8fafc'),
            'footer_content' => setting('footer_content', '{}'), // send as json string to edit
            'enable_action_monitoring' => setting('enable_action_monitoring', '1') === '1',
        ];

        return view('settings.index', [
            'settings' => $settings
        ]);
    }

    /**
     * Update the global settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_font' => 'required|string|max:255',
            'theme_template' => 'required|string|max:50',
            'primary_color' => 'required|string|max:50',
            'button_color' => 'required|string|max:50',
            'button_hover_color' => 'required|string|max:50',
            'bg_color_light' => 'required|string|max:50',
            'bg_color_dark' => 'required|string|max:50',
            'text_color_light' => 'required|string|max:50',
            'text_color_dark' => 'required|string|max:50',
            'footer_content' => 'nullable|string',
            'enable_action_monitoring' => 'boolean',
        ]);

        // Handle File Upload for site_logo
        if ($request->hasFile('site_logo')) {
            $request->validate(['site_logo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048']);
            $file = $request->file('site_logo');
            $uploadPath = public_path('uploads/settings');
            
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $filename = 'site_logo_'.time().'.'.$file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            
            Setting::updateOrCreate(
                ['key' => 'site_logo'],
                ['value' => '/uploads/settings/'.$filename, 'type' => 'string']
            );
        }

        foreach ($validated as $key => $value) {
            if ($key === 'site_logo') continue; // Handled above
            
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '', 'type' => 'string']
            );
        }

        Artisan::call('view:clear');

        return redirect()->back()->with('success', 'Global settings updated successfully.');
    }
}

