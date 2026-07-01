<?php

namespace Lunar\Storefront\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class GetTwoFactorCodesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'optIn' => ! blank($user->two_factor_secret),
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'qrCode' => ! blank($user->two_factor_secret) ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $user->hasEnabledTwoFactorAuthentication() ? $user->recoveryCodes() : [],
            'status' => Session::get('status'),
        ]);
    }
}
