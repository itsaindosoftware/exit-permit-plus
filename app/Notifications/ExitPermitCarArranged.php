<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExitPermitCarArranged extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipient = trim((string) ($notifiable->name ?? ''));
        $greeting = $recipient !== '' ? 'Hello ' . $recipient . ',' : 'Hello,';
        $vehiclePlate = strtoupper((string) ($this->exitPermit->vehicle_plate ?? ''));
        $driverName = (string) ($this->exitPermit->driver_name ?? '');

        return (new MailMessage())
            ->subject('Car Arrangement Completed')
            ->greeting($greeting)
            ->line(sprintf('Car and driver arrangement is complete for Exit Permit #%d.', $this->exitPermit->id))
            ->line('Vehicle Plate: ' . ($vehiclePlate !== '' ? $vehiclePlate : '-'))
            ->line('Driver Name: ' . ($driverName !== '' ? $driverName : '-'))
            ->action('View Exit Permit', route('exit-permits.show', $this->exitPermit));
    }
}
