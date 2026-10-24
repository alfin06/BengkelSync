<?php

namespace App\Http\Controllers\Mechanics;

use App\Http\Controllers\Controller;
use App\Models\Mechanic;
use App\Services\Mechanics\MechanicService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a KTP photo from the private disk to users with mechanic.view_identity
 * (SPEC section 8.2). Never cached by the browser or proxies.
 */
class MechanicKtpController extends Controller
{
    public function __invoke(Mechanic $mechanic, MechanicService $mechanics): StreamedResponse
    {
        Gate::authorize('viewIdentity', $mechanic);

        abort_if($mechanic->ktp_path === null || ! $mechanics->disk()->exists($mechanic->ktp_path), 404);

        return $mechanics->disk()->response($mechanic->ktp_path, 'ktp-'.$mechanic->id, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
