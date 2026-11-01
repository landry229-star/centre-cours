<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuardianMessageRequest;
use App\Models\Guardian;
use App\Models\GuardianConversation;
use App\Services\Communications\CommunicationService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommunicationMessageController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GuardianConversation::class);
        $user = $request->user();
        $conversations = GuardianConversation::query()
            ->with(['guardian.user', 'latestMessage.sender'])
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)])
            ->when($user->isGuardian(), fn ($query) => $query->where('guardian_id', $user->guardian->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('communications.messages.index', compact('conversations'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', GuardianConversation::class);
        $guardians = $request->user()->isAdmin()
            ? Guardian::query()
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->with('user')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        return view('communications.messages.create', compact('guardians'));
    }

    public function store(
        StoreGuardianMessageRequest $request,
        CommunicationService $communications,
    ): RedirectResponse {
        $guardian = $request->user()->isAdmin()
            ? Guardian::query()
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->findOrFail($request->integer('guardian_id'))
            : Guardian::query()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $message = $communications->sendMessage(
                $request->user(),
                $guardian,
                $request->validated('body'),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['body' => $exception->getMessage()]);
        }

        return redirect()
            ->route('communications.messages.show', $message->guardian_conversation_id)
            ->with('success', 'Votre message a été envoyé.');
    }

    public function show(
        Request $request,
        GuardianConversation $conversation,
        CommunicationService $communications,
    ): View {
        abort_if(
            $request->user()->isGuardian()
                && (int) $conversation->guardian?->user_id !== (int) $request->user()->id,
            404,
        );
        Gate::authorize('view', $conversation);
        $communications->markMessagesRead($conversation, $request->user());
        $conversation->load(['guardian.user']);
        $messages = $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->paginate(50);
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return view('communications.messages.show', compact('conversation', 'messages'));
    }

    public function reply(
        StoreGuardianMessageRequest $request,
        GuardianConversation $conversation,
        CommunicationService $communications,
    ): RedirectResponse {
        abort_if(
            $request->user()->isGuardian()
                && (int) $conversation->guardian?->user_id !== (int) $request->user()->id,
            404,
        );
        Gate::authorize('view', $conversation);

        try {
            $communications->sendMessage(
                $request->user(),
                $conversation->guardian,
                $request->validated('body'),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['body' => $exception->getMessage()]);
        }

        return redirect()
            ->route('communications.messages.show', $conversation)
            ->with('success', 'Votre message a été envoyé.');
    }
}
