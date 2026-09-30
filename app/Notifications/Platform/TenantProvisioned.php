<?php

namespace App\Notifications\Platform;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantProvisioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tenant $tenant
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tenant \"{$this->tenant->name}\" is ready")
            ->line("Tenant \"{$this->tenant->name}\" has finished provisioning and is now active.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'message' => "Tenant \"{$this->tenant->name}\" has finished provisioning and is now active.",
        ];
    }
}
