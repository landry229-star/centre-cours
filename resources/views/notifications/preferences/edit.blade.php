<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Préférences de notification</h2>
            <p class="mt-1 text-sm text-gray-500">Choisissez les notifications que vous souhaitez recevoir.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />

            <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PATCH')

                <div class="divide-y divide-slate-100">
                    @foreach ($preferences as $preference)
                        <label class="flex cursor-pointer items-start justify-between gap-6 py-4 first:pt-0 last:pb-0">
                            <span>
                                <span class="block font-medium text-slate-900">{{ $preference['label'] }}</span>
                                <span class="mt-1 block text-sm text-slate-500">
                                    Recevoir les notifications de cette catégorie.
                                </span>
                            </span>
                            <span class="shrink-0">
                                <input type="hidden" name="preferences[{{ $preference['value'] }}]" value="0">
                                <input
                                    type="checkbox"
                                    name="preferences[{{ $preference['value'] }}]"
                                    value="1"
                                    @checked($preference['enabled'])
                                    class="rounded border-slate-300 text-teal-700 shadow-sm focus:ring-teal-600"
                                >
                            </span>
                        </label>
                    @endforeach

                    <div class="flex items-start justify-between gap-6 py-4">
                        <span>
                            <span class="block font-medium text-slate-900">Messages privés</span>
                            <span class="mt-1 block text-sm text-slate-500">
                                Indispensables pour suivre les échanges avec le centre et toujours activés.
                            </span>
                        </span>
                        <input type="checkbox" checked disabled aria-label="Messages privés toujours activés" class="mt-1 rounded border-slate-300 text-teal-700 shadow-sm">
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 pt-5">
                    <x-primary-button>Enregistrer les préférences</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
