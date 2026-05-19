<?php

namespace App\Mail;

use App\Models\InvoiceTemplate;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public InvoiceTemplate $template;
    public ?SiteSetting $setting;
    public ?string $logoPath = null;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing([
            'booking',
            'user',
            'service',
            'servicePlan',
            'pricingPlan',
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

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->template->subject_prefix ?: 'Invoice') . ' #' . $this->order->order_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-invoice',
            with: [
                'order' => $this->order,
                'template' => $this->template,
                'setting' => $this->setting,
                'logoPath' => $this->logoPath,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
