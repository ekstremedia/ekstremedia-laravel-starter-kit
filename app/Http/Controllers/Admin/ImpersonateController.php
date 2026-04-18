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

        activity('impersonation')
            ->causedBy($me)
            ->performedOn($user)
            ->event('started')
            ->log("{$me->email} impersonated {$user->email}");

        $manager->take($me, $user);

        return redirect()->route('app.landing');
    }

    public function leave(ImpersonateManager $manager): RedirectResponse
    {
        if ($manager->isImpersonating()) {
            $impersonator = User::find($manager->getImpersonatorId());
            $me = auth()->user();

            activity('impersonation')
                ->causedBy($impersonator)
                ->performedOn($me)
                ->event('stopped')
                ->log("{$impersonator?->email} stopped impersonating {$me->email}");

            $manager->leave();
        }

        return redirect()->route('admin.users.index');
    }
}
