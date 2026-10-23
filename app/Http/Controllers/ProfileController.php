<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone === null ? '' : Phone::format($user->phone),
                'locale' => $user->locale ?? app()->getLocale(),
            ],
            'locales' => config('app.supported_locales'),
        ]);
    }

    public function update(UpdateProfileRequest $request, UserService $users): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, phone?: string|null, locale: string} $data */
        $data = $request->validated();

        $users->updateProfile($user, $data);

        return back()->with('success', __('profile.saved'));
    }
}
