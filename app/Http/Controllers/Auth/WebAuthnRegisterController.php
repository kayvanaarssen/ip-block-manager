<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class WebAuthnRegisterController extends Controller
{
    public function options(AttestationRequest $request)
    {
        return $request->toCreate();
    }

    public function register(AttestedRequest $request, AuditService $audit)
    {
        $credential = $request->save();
        $audit->log('register_passkey', $request->user(), ['credential_id' => $credential->id]);
        return response()->json(['success' => true]);
    }

    public function destroy(WebAuthnCredential $credential, AuditService $audit)
    {
        if ($credential->user_id !== auth()->id()) {
            abort(403);
        }

        $audit->log('delete_passkey', auth()->user(), ['credential_id' => $credential->id]);
        $credential->delete();

        return back()->with('success', 'Passkey removed.');
    }
}
