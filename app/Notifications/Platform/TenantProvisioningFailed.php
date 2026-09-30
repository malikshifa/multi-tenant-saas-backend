<?php

namespace App\Notifications\Platform;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tenant $tenant,
        protected string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Provisioning failed for tenant \"{$this->tenant->name}\"")
            ->line("Provisioning failed for tenant \"{$this->tenant->name}\".")
            ->line("Reason: {$this->reason}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'reason' => $this->reason,
            'message' => "Provisioning failed for tenant \"{$this->tenant->name}\".",
        ];
    }
}
