<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

class WebAuthnLoginController extends Controller
{
    public function options(AssertionRequest $request)
    {
        return $request->toVerify();
    }

    public function login(AssertedRequest $request, AuditService $audit)
    {
        $user = $request->login();

        if ($user) {
            $request->session()->regenerate();
            $audit->log('login', $user, ['method' => 'passkey']);
            return response()->json(['redirect' => route('dashboard')]);
        }

        return response()->json(['error' => 'Authentication failed'], 422);
    }
}
