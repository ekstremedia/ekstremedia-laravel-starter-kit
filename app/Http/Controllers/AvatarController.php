<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class AvatarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        try {
            $request->user()
                ->addMediaFromRequest('avatar')
                ->toMediaCollection('avatar');
        } catch (FileDoesNotExist|FileIsTooBig $e) {
            return back()->with('error', 'Upload failed: '.$e->getMessage());
        }

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->clearMediaCollection('avatar');

        return back()->with('success', 'Profile photo removed.');
    }
}
