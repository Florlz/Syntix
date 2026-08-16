<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

final class AdminActivityNotification extends Notification
{
    /**
     * @param  array{kind: string, title: string, message: string, event_id?: string|null, action?: array{label: string, route: string, params?: array<string, string>}}  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
