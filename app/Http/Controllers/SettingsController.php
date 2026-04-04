<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Edit', [
            'settings' => AppSetting::getAll(),
        ]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $request->validate([
            'app_name' => 'required|string|max:100',
            'logo_light' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
        ]);

        AppSetting::set('app_name', $request->input('app_name'));

        if ($request->hasFile('logo_light')) {
            // Delete old logo
            $oldPath = AppSetting::get('logo_light');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('logo_light')->store('logos', 'public');
            AppSetting::set('logo_light', $path);
        }

        if ($request->hasFile('logo_dark')) {
            $oldPath = AppSetting::get('logo_dark');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('logo_dark')->store('logos', 'public');
            AppSetting::set('logo_dark', $path);
        }

        $audit->log('update_settings', null, ['app_name' => $request->input('app_name')]);

        return back()->with('success', 'Settings updated.');
    }

    public function removeLogo(Request $request, AuditService $audit)
    {
        $request->validate([
            'type' => 'required|in:light,dark',
        ]);

        $key = 'logo_' . $request->input('type');
        $path = AppSetting::get($key);

        if ($path) {
            Storage::disk('public')->delete($path);
            AppSetting::set($key, null);
        }

        $audit->log('remove_logo', null, ['type' => $request->input('type')]);

        return back()->with('success', ucfirst($request->input('type')) . ' mode logo removed.');
    }
}
