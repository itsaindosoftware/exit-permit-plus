<?php

namespace App\Notifications;

use App\Models\ExitPermit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArrangeCarDriverRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly ExitPermit $exitPermit)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipient = trim((string) ($notifiable->name ?? ''));
        $greeting = $recipient !== '' ? 'Hello ' . $recipient . ',' : 'Hello,';
        $vehiclePlate = $this->exitPermit->vehicle_plate
            ? strtoupper((string) $this->exitPermit->vehicle_plate)
            : null;

        return (new MailMessage())
            ->subject('Action Needed: Arrange Car for Exit Permit')
            ->greeting($greeting)
            ->line(sprintf(
                'Exit Permit #%d for %s is ready for car and driver arrangement.',
                $this->exitPermit->id,
                (string) ($this->exitPermit->user?->name ?? 'Employee')
            ))
            ->line('License Plate: ' . ($vehiclePlate ?? 'NOT SET'))
            ->action('Open Schedule Car', route('schedule-cars.edit', $this->exitPermit));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $vehiclePlate = $this->exitPermit->vehicle_plate
            ? strtoupper((string) $this->exitPermit->vehicle_plate)
            : null;

        return [
            'type' => 'arrange_car_driver',
            'title' => 'Car and Driver Arrangement Requested',
            'message' => sprintf(
                'Exit Permit request from %s needs a car/driver arranged. Field 1.4 License Plate: %s.',
                (string) ($this->exitPermit->user?->name ?? 'Employee'),
                $vehiclePlate ?? 'NOT SET'
            ),
            'exit_permit_id' => $this->exitPermit->id,
            'destination' => $this->exitPermit->destination,
            'permit_date' => $this->exitPermit->permit_date ? (string) $this->exitPermit->permit_date : null,
            'vehicle_plate' => $vehiclePlate,
        ];
    }
}
