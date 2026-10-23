<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenancy\SwitchBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Tenancy\ActiveBranchResolver;
use Illuminate\Http\RedirectResponse;

class ActiveBranchController extends Controller
{
    public function update(SwitchBranchRequest $request, ActiveBranchResolver $branches): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Branch $branch */
        $branch = $request->branch();

        $branches->switchTo($user, $branch, $request->session());

        return redirect()->route('dashboard')->with('success', __('app.branch_switched', ['branch' => $branch->name]));
    }
}
