<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentreSettingsUpdateRequest;
use App\Services\CentreSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CentreSettingsController extends Controller
{
    public function edit(CentreSettingsService $settings): View
    {
        return view('admin.settings.centre', [
            'settings' => $settings->current(),
        ]);
    }

    public function update(CentreSettingsUpdateRequest $request, CentreSettingsService $settings): RedirectResponse
    {
        $settings->update(
            $request->safe()->only(['name', 'address', 'phone', 'email']),
            $request->file('logo'),
        );

        return redirect()
            ->route('admin.settings.centre.edit')
            ->with('success', 'Les informations du centre ont été mises à jour.');
    }

    public function logo(CentreSettingsService $settings): StreamedResponse
    {
        $path = $settings->current()['logo_path'];

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
