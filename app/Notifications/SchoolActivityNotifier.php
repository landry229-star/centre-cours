<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;

class SchoolActivityNotifier
{
    public function send(
        User $recipient,
        NotificationCategory|string $category,
        string $title,
        string $message,
        string $url,
    ): void {
        $category = $category instanceof NotificationCategory ? $category->value : $category;

        if (! $recipient->receivesNotificationCategory($category)) {
            return;
        }

        $recipient->notify(new SchoolActivityNotification($category, $title, $message, $url));
    }
}
