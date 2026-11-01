<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-teal-700">Communications</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                {{ auth()->user()->isAdmin() ? $conversation->guardian->full_name : 'Administration du centre' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">Conversation privée avec l’administration.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <x-flash />
            <a href="{{ route('communications.messages.index') }}" class="text-sm font-semibold text-teal-800 hover:underline">← Toutes les conversations</a>

            <section class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @forelse ($messages as $message)
                    <article @class([
                        'max-w-[90%] rounded-2xl p-4',
                        'justify-self-end bg-teal-700 text-white' => $message->sender_id === auth()->id(),
                        'justify-self-start bg-slate-100 text-slate-800' => $message->sender_id !== auth()->id(),
                    ])>
                        <p class="text-xs font-semibold {{ $message->sender_id === auth()->id() ? 'text-teal-100' : 'text-slate-500' }}">
                            {{ $message->sender->name }} · {{ \App\Support\Format::dateTime($message->created_at) }}
                        </p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $message->body }}</p>
                    </article>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">Aucun message dans cette conversation.</p>
                @endforelse
            </section>
            {{ $messages->links() }}

            <form method="POST" action="{{ route('communications.messages.reply', $conversation) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label for="body" class="text-sm font-medium text-slate-700">Votre réponse</label>
                <textarea id="body" name="body" rows="5" required maxlength="4000" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-700 focus:ring-teal-700">{{ old('body') }}</textarea>
                @error('body')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                <button type="submit" class="justify-self-end rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Envoyer la réponse</button>
            </form>
        </div>
    </div>
</x-app-layout>
