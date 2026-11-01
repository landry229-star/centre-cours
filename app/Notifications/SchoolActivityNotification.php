<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\Notification;

class SchoolActivityNotification extends Notification
{
    public function __construct(
        public readonly string|NotificationCategory $category,
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'category' => $this->category instanceof NotificationCategory
                ? $this->category->value
                : $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
