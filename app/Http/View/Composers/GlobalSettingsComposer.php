<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class GlobalSettingsComposer
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function compose(View $view)
    {
        static $cachedData = null;

        if ($cachedData === null) {
            $user = $this->request->user();

            $cachedData = [
                'auth' => [
                    'user' => $user ? [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->name : 'Guest',
                        'role_id' => $user->role_id,
                        'profile_photo_url' => $user->profile_photo_url,
                        'isAdminFull' => $user->isAdminFull(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    ] : null,
                    'is_impersonating' => Session::has('impersonator_id'),
                ],
                'flash' => [
                    'success' => Session::get('success'),
                    'error' => Session::get('error'),
                    'warning' => Session::get('warning'),
                    'info' => Session::get('info'),
                    'fawry_payload' => Session::get('fawry_payload'),
                ],
                'global_settings' => [
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
                    'footer_content' => json_decode(setting('footer_content', '{}'), true),
                    'enable_action_monitoring' => setting('enable_action_monitoring', '1'),
                ],
            ];
        }

        $view->with('sharedData', $cachedData);
    }
}
