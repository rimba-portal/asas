<?php

declare(strict_types=1);

namespace Bites\Base\Services;

use App\Models\User;
use Bites\Base\Actions\SendNotification;

class NotificationService
{
    public function send(User $user, string $message, array $context = []): void
    {
        app(SendNotification::class)
            ->execute($user, $message, $context);
    }

    public function sendToMany(iterable $users, string $message): void
    {
        foreach ($users as $user) {
            $this->send($user, $message);
        }
    }
}
