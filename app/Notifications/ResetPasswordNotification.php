<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued version of the framework's password reset notification.
 */
final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
