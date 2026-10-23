<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;

/**
 * Picks the branch a user works in for this request (CLAUDE.md rule 2).
 *
 * The active branch lives in the session and is re-checked against the user's branch
 * access on every request, so a removed assignment takes effect immediately.
 */
class ActiveBranchResolver
{
    private const SESSION_KEY = 'active_branch_id';

    public function resolve(User $user, Session $session): ?Branch
    {
        $branches = $user->accessibleBranches();

        // Without a (valid) choice in the session, start in the oldest branch: the main one.
        $branch = $branches->clone()->whereKey($session->get(self::SESSION_KEY))->first()
            ?? $branches->oldest('id')->first();

        if ($branch === null) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        $session->put(self::SESSION_KEY, $branch->getKey());

        return $branch;
    }

    public function switchTo(User $user, Branch $branch, Session $session): void
    {
        if (! $user->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        $session->put(self::SESSION_KEY, $branch->getKey());
    }
}
