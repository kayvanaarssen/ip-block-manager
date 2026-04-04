<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;

class PasskeyController extends Controller
{
    public function registerOptions(Request $request)
    {
        $action = app(GeneratePasskeyRegisterOptionsAction::class);

        return $action->execute($request->user());
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'passkey' => ['required', 'json'],
            'options' => ['required', 'json'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $action = app(StorePasskeyAction::class);

        try {
            $action->execute(
                $request->user(),
                $request->input('passkey'),
                $request->input('options'),
                $request->getHost(),
                ['name' => $request->input('name', 'Passkey ' . now()->format('Y-m-d H:i'))],
            );

            $audit->log('register_passkey', $request->user());

            return back()->with('success', 'Passkey registered successfully.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'passkey' => 'Something went wrong registering the passkey. Please try again.',
            ]);
        }
    }

    public function destroy(string $passkey, Request $request, AuditService $audit)
    {
        $request->user()->passkeys()->where('id', $passkey)->delete();

        $audit->log('delete_passkey', $request->user());

        return back()->with('success', 'Passkey removed.');
    }
}
