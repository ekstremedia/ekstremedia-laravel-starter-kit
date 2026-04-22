<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $activity = Activity::query()
            ->where('causer_id', $user->getKey())
            ->where('causer_type', $user::class)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'created_at' => $a->created_at?->toIso8601String(),
                'description' => $a->description,
                'event' => $a->event,
                'log_name' => $a->log_name,
            ])
            ->values()
            ->all();

        return Inertia::render('Home', [
            'userDetail' => [
                'id' => $user->getKey(),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
                'roles' => $user->getRoleNames()->all(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'activity' => $activity,
        ]);
    }
}
