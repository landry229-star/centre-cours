<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfilePhotoUpdateRequest;
use App\Services\Media\PrivatePhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function update(ProfilePhotoUpdateRequest $request, PrivatePhotoStorage $photos): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        $newPath = $photos->store($request->file('photo'), "users/{$user->id}/profile");

        $user->forceFill(['profile_photo_path' => $newPath])->save();

        if ($oldPath !== null && $oldPath !== $newPath) {
            $photos->delete($oldPath);
        }

        return redirect()->route('profile.edit')->with('status', 'profile-photo-updated');
    }

    public function show(Request $request): StreamedResponse
    {
        $path = $request->user()?->profile_photo_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
