<?php

declare(strict_types=1);

namespace Bites\Base\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendNotification
{
    public function execute(User $user, string $message, array $context = []): void
    {
        // Placeholder (plug into Mail / DB / Broadcast)
        Log::info('Notification', [
            'user_id' => $user->id,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
