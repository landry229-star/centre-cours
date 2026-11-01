<?php

use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\GuardianConversation;
use App\Models\GuardianMessage;
use App\Models\User;
use App\Notifications\SchoolActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('prunes only in-app notifications older than two years and keeps communication history', function () {
    $this->travelTo('2026-10-04 22:00:00');

    $recipient = User::factory()->guardian()->create();
    $recipient->notify(new SchoolActivityNotification('payment', 'Ancienne', 'Notification ancienne.', route('dashboard')));
    $oldNotification = $recipient->notifications()->firstOrFail();
    DB::table('notifications')->where('id', $oldNotification->id)->update([
        'created_at' => now()->subYears(2)->subSecond(),
    ]);

    $recipient->notify(new SchoolActivityNotification('payment', 'Limite', 'À conserver.', route('dashboard')));
    $boundaryNotification = $recipient->notifications()
        ->where('id', '!=', $oldNotification->id)
        ->firstOrFail();
    DB::table('notifications')->where('id', $boundaryNotification->id)->update([
        'created_at' => now()->subYears(2),
    ]);

    $admin = User::factory()->admin()->create();
    $announcement = Announcement::factory()->create(['created_by' => $admin->id]);
    $announcement->forceFill(['created_at' => now()->subYears(3), 'updated_at' => now()->subYears(3)])->save();
    $guardian = Guardian::factory()->create(['user_id' => $recipient->id]);
    $conversation = GuardianConversation::factory()->create(['guardian_id' => $guardian->id]);
    $conversation->forceFill([
        'last_message_at' => now()->subYears(3),
        'created_at' => now()->subYears(3),
        'updated_at' => now()->subYears(3),
    ])->save();
    $message = GuardianMessage::factory()->create([
        'guardian_conversation_id' => $conversation->id,
        'sender_id' => $admin->id,
    ]);
    $message->forceFill(['created_at' => now()->subYears(3), 'updated_at' => now()->subYears(3)])->save();

    $this->artisan('notifications:prune')
        ->assertSuccessful()
        ->expectsOutput('1 notification(s) ancienne(s) supprimée(s).');

    expect($recipient->notifications()->whereKey($oldNotification->id)->exists())->toBeFalse();
    expect($recipient->notifications()->whereKey($boundaryNotification->id)->exists())->toBeTrue();
    $this->assertModelExists($announcement);
    $this->assertModelExists($conversation);
    $this->assertModelExists($message);
});
