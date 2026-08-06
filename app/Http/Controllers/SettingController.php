<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Whitelisted public site settings for the frontend.
     */
    public function index()
    {
        $keys = [
            'site_name',
            'site_tagline',
            'contact_email',
            'contact_phone',
            'whatsapp',
            'address',
            'service_fee_percent',
        ];

        $all = Setting::asMap();
        $public = [];
        foreach ($keys as $key) {
            $public[$key] = $all[$key] ?? null;
        }

        return response()->json($public);
    }
}
