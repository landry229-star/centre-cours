<?php

namespace App\Http\Controllers\Web;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        $preferences = collect(NotificationCategory::preferenceCategories())
            ->map(fn (NotificationCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'enabled' => $request->user()->receivesNotificationCategory($category->value),
            ])
            ->all();

        return view('notifications.preferences.edit', compact('preferences'));
    }

    public function update(Request $request): RedirectResponse
    {
        $categoryKeys = collect(NotificationCategory::preferenceCategories())
            ->map(fn (NotificationCategory $category): string => $category->value)
            ->all();
        $rules = collect($categoryKeys)
            ->mapWithKeys(fn (string $category): array => ["preferences.{$category}" => ['required', 'boolean']])
            ->all();
        $validated = $request->validate([
            'preferences' => ['required', 'array:'.implode(',', $categoryKeys)],
            ...$rules,
        ]);

        $preferences = collect($validated['preferences'])
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();

        $request->user()
            ->forceFill(['notification_preferences' => $preferences])
            ->save();

        return redirect()
            ->route('notifications.preferences.edit')
            ->with('success', 'Vos préférences de notification ont été enregistrées.');
    }
}
