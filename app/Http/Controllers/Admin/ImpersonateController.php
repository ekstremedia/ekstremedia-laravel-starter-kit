<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonateController extends Controller
{
    public function take(User $user, ImpersonateManager $manager): RedirectResponse
    {
        $me = auth()->user();

        if (! $me->canImpersonate() || ! $user->canBeImpersonated()) {
            abort(403);
        }

        // Impersonation activity is intentionally NOT logged to activity_log;
        // the banner at the top of every page already signals the session is
        // impersonated, and the noise drowned out useful admin signal.
        // Re-enable by uncommenting if you need an audit trail.
        // activity('impersonation')
        //     ->causedBy($me)
        //     ->performedOn($user)
        //     ->event('started')
        //     ->log("{$me->email} impersonated {$user->email}");

        $manager->take($me, $user);

        return redirect()->route('app.landing');
    }

    public function leave(ImpersonateManager $manager): RedirectResponse
    {
        if ($manager->isImpersonating()) {
            $impersonator = User::find($manager->getImpersonatorId());
            $me = auth()->user();

            // See take() — impersonation activity is intentionally not logged.
            // activity('impersonation')
            //     ->causedBy($impersonator)
            //     ->performedOn($me)
            //     ->event('stopped')
            //     ->log("{$impersonator?->email} stopped impersonating {$me->email}");

            $manager->leave();
        }

        return redirect()->route('admin.users.index');
    }
}
