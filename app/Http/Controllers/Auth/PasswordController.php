<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, UserService $users): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $users->changePassword($user, $request->string('password')->toString());

        return back()->with('success', __('profile.password_updated'));
    }
}
