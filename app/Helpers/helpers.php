<?php

if (! function_exists('storage_url')) {
    /**
     * Generate a URL for a file stored in the storage/app/public directory.
     *
     * @param  string  $path
     * @return string
     */
    function storage_url($path)
    {
        // Remove leading slashes if any
        $path = ltrim($path, '/');

        // Return the full URL to the public storage path
        return url('storage/'.$path);
    }
}

if (! function_exists('render_files')) {
    function render_files($file_path_json)
    {
        if (! $file_path_json) {
            return '<span class="text-muted">No File</span>';
        }

        $files = json_decode($file_path_json, true);
        if (! is_array($files) || empty($files)) {
            return '<span class="text-muted">No File</span>';
        }

        $out = '';
        foreach ($files as $f) {
            // Normalize file path (convert backslashes to forward slashes)
            $f = str_replace('\\', '/', $f);

            $candidate = $f;

            // If it's already a full URL, trust it
            if (preg_match('#^https?://#i', $candidate)) {
                $out .= '<a href="'.e($candidate).'" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success me-1"><i class="fas fa-download"></i> Download</a>';

                continue;
            }

            // Common legacy replacements to find file on disk
            $alternatives = [
                $candidate,
                str_replace('uploads/profiles', 'uploads/profile_pictures', $candidate),
                str_replace('storage/profile_pictures', 'uploads/profile_pictures', $candidate),
                str_replace('uploads/profile_pictures', 'uploads/profile_pictures', $candidate),
            ];

            $found = false;
            foreach ($alternatives as $alt) {
                $alt = ltrim($alt, '/');
                if (file_exists(public_path($alt))) {
                    $candidate = $alt;
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $out .= '<a href="'.asset($candidate).'" download class="btn btn-sm btn-outline-success me-1"><i class="fas fa-download"></i> Download</a>';
            } else {
                // Only show filename, not full path
                $filename = basename($f);
                $out .= '<span class="text-danger">File not found: '.htmlspecialchars($filename).'</span>';
            }
        }

        return $out;
    }
}

if (! function_exists('safe_group_name')) {
    /**
     * Create a safe ID for group names with special characters
     *
     * @param  string  $groupName
     * @return string
     */
    function safe_group_name($groupName)
    {
        // Remove any problematic characters for HTML IDs
        $safe = preg_replace('/[^a-zA-Z0-9_\x{0600}-\x{06FF}]/u', '_', $groupName);
        $safe = preg_replace('/_{2,}/', '_', $safe); // Replace multiple underscores
        $safe = trim($safe, '_');

        // Add prefix to ensure it starts with a letter
        if (preg_match('/^[0-9]/', $safe)) {
            $safe = 'group_'.$safe;
        }

        return $safe;
    }
}

if (! function_exists('escape_js_string')) {
    /**
     * Escape string for JavaScript usage
     *
     * @param  string  $string
     * @return string
     */
    function escape_js_string($string)
    {
        return addslashes(htmlspecialchars($string, ENT_QUOTES, 'UTF-8', false));
    }
}

if (! function_exists('encode_group_name')) {
    /**
     * Encode group name for URLs
     *
     * @param  string  $groupName
     * @return string
     */
    function encode_group_name($groupName)
    {
        return rawurlencode($groupName);
    }
}

if (! function_exists('decode_group_name')) {
    /**
     * Decode group name from URL
     *
     * @param  string  $groupName
     * @return string
     */
    function decode_group_name($groupName)
    {
        return rawurldecode($groupName);
    }
}

if (! function_exists('group_url')) {
    /**
     * Generate safe URL for group operations
     *
     * @param  string  $route
     * @param  string  $groupName
     * @return string
     */
    function group_url($route, $groupName)
    {
        return route($route, encode_group_name($groupName));
    }
}

if (! function_exists('render_group_name')) {
    /**
     * Render group name with safe onclick attribute
     *
     * @param  string  $groupName
     * @param  bool  $withCopyButton
     * @return string
     */
    function render_group_name($groupName, $withCopyButton = true)
    {
        $safeName = escape_js_string($groupName);

        if ($withCopyButton) {
            return sprintf(
                '<strong style="cursor: pointer;" onclick="copyGroupName(\'%s\')" title="انقر للنسخ">%s</strong>',
                $safeName,
                e($groupName)
            );
        }

        return e($groupName);
    }
}
