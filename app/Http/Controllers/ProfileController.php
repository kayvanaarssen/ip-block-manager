<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'theme_preference' => $request->user()->theme_preference,
                'telegram_linked' => (bool) $request->user()->telegram_user_id,
                'telegram_user_id' => $request->user()->telegram_user_id,
            ],
        ]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($data);
        $audit->log('update_profile', $request->user());

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        $audit->log('change_password', $request->user());

        return back()->with('success', 'Password changed.');
    }

    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme_preference' => ['required', 'in:light,dark,auto'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Theme preference saved.');
    }

    public function generateTelegramToken(Request $request)
    {
        $token = Str::random(32);

        Cache::put("telegram_link:{$token}", $request->user()->id, now()->addMinutes(5));

        return back()->with('telegram_token', $token);
    }

    public function unlinkTelegram(Request $request, AuditService $audit)
    {
        $request->user()->update(['telegram_user_id' => null]);
        $audit->log('unlink_telegram', $request->user());

        return back()->with('success', 'Telegram account unlinked.');
    }
}
