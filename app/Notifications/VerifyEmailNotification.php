<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued version of the framework's email verification notification.
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
