<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\InvoiceTemplate;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingQuoteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public InvoiceTemplate $template;
    public ?SiteSetting $setting;
    public ?string $logoPath = null;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking->loadMissing([
            'user',
            'service',
            'servicePlan',
            'pricingPlan',
            'order',
        ]);

        $this->template = InvoiceTemplate::activeTemplate();
        $this->setting = SiteSetting::query()->first();

        $logoValue = $this->setting?->logo;

        if (! empty($logoValue)) {
            $cleanLogo = ltrim($logoValue, '/');

            $possibleLogoPath = str_starts_with($cleanLogo, 'storage/')
                ? public_path($cleanLogo)
                : public_path('storage/' . $cleanLogo);

            if (file_exists($possibleLogoPath)) {
                $this->logoPath = $possibleLogoPath;
            }
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quotation #' . $this->booking->booking_no,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-quote',
            with: [
                'booking' => $this->booking,
                'template' => $this->template,
                'setting' => $this->setting,
                'logoPath' => $this->logoPath,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
