<?php

if (!function_exists('setting')) {
    /**
     * Get or set a setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting($key, $default = null)
    {
        static $settings = null;

        if ($settings === null) {
            try {
                // Fetch all settings once per request
                $settings = \App\Models\Setting::all()->pluck('value', 'key')->toArray();
            } catch (\Exception $e) {
                // Settings table might not exist yet during initial setup/migration
                $settings = [];
            }
        }

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }
}
