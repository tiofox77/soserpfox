<?php

if (!function_exists('setting')) {
    /**
     * Get system setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    function setting($key, $default = null)
    {
        return \App\Models\SystemSetting::get($key, $default);
    }
}

if (!function_exists('app_logo')) {
    /**
     * Get application logo URL
     * 
     * @return string|null
     */
    function app_logo()
    {
        $logo = \App\Models\SystemSetting::get('app_logo');
        return $logo ? \Storage::url($logo) : null;
    }
}

if (!function_exists('app_favicon')) {
    /**
     * Get application favicon URL
     * 
     * @return string|null
     */
    function app_favicon()
    {
        $favicon = \App\Models\SystemSetting::get('app_favicon');
        return $favicon ? \Storage::url($favicon) : null;
    }
}

if (!function_exists('app_name')) {
    /**
     * Get application name
     * 
     * @return string
     */
    function app_name()
    {
        return \App\Models\SystemSetting::get('app_name', config('app.name', 'SOS ERP'));
    }
}
