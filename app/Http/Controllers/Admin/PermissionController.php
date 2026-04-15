<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => Permission::orderBy('name')->withCount('roles')->get()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'guard_name' => $p->guard_name,
                'roles_count' => $p->roles_count,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:permissions,name'],
        ]);

        Permission::create(['name' => $data['name']]);

        return back()->with('success', 'Permission created.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return back()->with('success', 'Permission deleted.');
    }
}
