@php
    $brandColor = $template->brand_color ?? '#0F52BA';

    $companyName = $setting?->site_name ?? config('app.name');
    $companyEmail = $setting?->email ?? config('mail.from.address');
    $companyPhone = $setting?->phone ?? '';
    $companyAddress = $setting?->location ?? '';
    $companyWebsite = config('app.url');

    $logoCid = null;

    if (!empty($logoPath) && file_exists($logoPath)) {
        $logoCid = $message->embed($logoPath);
    }

    $quotationNo = $booking->booking_no ?? 'QT-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT);

    $customerName = $booking->full_name ?? ($booking->user?->name ?? 'Valued Customer');
    $customerEmail = $booking->email ?? ($booking->user?->email ?? '');
    $customerPhone = $booking->phone ?? ($booking->user?->phone ?? '');

    $companyCustomerName = $booking->company_name ?? '';
    $companyCustomerEmail = $booking->company_email ?? '';
    $companyCustomerPhone = $booking->company_phone ?? '';

    $isPricingPlan = $booking->booking_type === 'pricing_plan';

    $planName = $isPricingPlan
        ? $booking->pricingPlan?->title ?? ($booking->plan_name ?? 'Pricing Plan')
        : $booking->servicePlan?->name ?? ($booking->plan_name ?? ($booking->service?->card_title ?? 'Service Plan'));

    $serviceName = $isPricingPlan
        ? $booking->pricingPlan?->title ?? $booking->plan_name
        : $booking->service?->card_title ?? $booking->plan_name;

    $planDescription = $isPricingPlan
        ? $booking->pricingPlan?->description ?? 'Business service plan quotation.'
        : $booking->servicePlan?->description ?? ($booking->service?->short_description ?? 'Service quotation.');

    $billingCycle = match ($booking->billing_cycle) {
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'one_time' => 'One-time',
        'custom' => 'Custom',
        default => 'Negotiable',
    };

    $planPrice = (float) ($booking->plan_price ?? 0);
    $requestedPrice = $booking->requested_price !== null ? (float) $booking->requested_price : null;
    $quotedPrice = $booking->quoted_price !== null ? (float) $booking->quoted_price : null;
    $finalPrice = $booking->final_price !== null ? (float) $booking->final_price : null;

    $subtotal = $planPrice;
    $grandTotal = $finalPrice ?? ($quotedPrice ?? ($requestedPrice ?? $planPrice));
    $discountAmount =
        $quotedPrice !== null && $planPrice > 0 && $quotedPrice < $planPrice ? $planPrice - $quotedPrice : 0;

    $currency = $booking->currency === 'BDT' || blank($booking->currency) ? '৳' : $booking->currency . ' ';
    $formatMoney = fn($amount) => $currency . number_format((float) $amount, 2);

    $customerMessage = $booking->user_note ?: $booking->message;
    $statusLabel = ucfirst(str_replace('_', ' ', $booking->status ?? 'quoted'));
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotationNo }}</title>
</head>

<body style="margin:0; padding:0; background:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8; padding:32px 0;">
        <tr>
            <td align="center">
                <table width="760" cellpadding="0" cellspacing="0"
                    style="background:#ffffff; border-radius:20px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:0 10px 35px rgba(15,23,42,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="padding:30px 30px 26px; border-bottom:1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top" style="width:55%;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td valign="middle" style="padding-right:14px;">
                                                    @if ($logoCid)
                                                        <table role="presentation" cellpadding="0" cellspacing="0"
                                                            border="0"
                                                            style="width:52px; height:52px; border-collapse:collapse;">
                                                            <tr>
                                                                <td align="center" valign="middle"
                                                                    style="width:52px; height:52px; padding:0;">
                                                                    <img src="{{ $logoCid }}"
                                                                        alt="{{ $companyName }}" width="52"
                                                                        height="52"
                                                                        style="width:52px; height:52px; max-width:52px; max-height:52px; object-fit:contain; display:block; border:0; outline:none; text-decoration:none;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @else
                                                        <table role="presentation" cellpadding="0" cellspacing="0"
                                                            border="0"
                                                            style="width:52px; height:52px; border-collapse:collapse;">
                                                            <tr>
                                                                <td align="center" valign="middle"
                                                                    style="width:52px; height:52px; background:{{ $brandColor }}; border-radius:10px;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endif
                                                </td>

                                                <td valign="middle">
                                                    <h1
                                                        style="margin:0; font-size:28px; line-height:1; color:#111827; text-transform:uppercase; letter-spacing:-0.02em;">
                                                        {{ $companyName }}
                                                    </h1>

                                                    <p
                                                        style="margin:8px 0 0; font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:#64748b;">
                                                        {{ $template->title ?? 'Service Quotation' }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <div style="margin-top:18px; font-size:13px; line-height:1.7; color:#475569;">
                                            @if ($companyAddress)
                                                <div>{{ $companyAddress }}</div>
                                            @endif

                                            @if ($companyEmail)
                                                <div>{{ $companyEmail }}</div>
                                            @endif

                                            @if ($companyPhone)
                                                <div>{{ $companyPhone }}</div>
                                            @endif

                                            @if ($companyWebsite)
                                                <a href="{{ $companyWebsite }}"
                                                    style="color:{{ $brandColor }}; text-decoration:none;">
                                                    {{ parse_url($companyWebsite, PHP_URL_HOST) ?? $companyWebsite }}
                                                </a>
                                            @endif
                                        </div>
                                    </td>

                                    <td valign="top" align="right" style="width:45%;">
                                        <h2
                                            style="margin:0 0 14px; font-size:30px; line-height:1; font-weight:800; color:{{ $brandColor }}; text-transform:uppercase;">
                                            Quotation
                                        </h2>

                                        <table cellpadding="0" cellspacing="0" align="right"
                                            style="font-size:12px; line-height:1.8; color:#475569;">
                                            <tr>
                                                <td
                                                    style="padding:2px 12px 2px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em;">
                                                    Quote #
                                                </td>
                                                <td style="padding:2px 0; font-weight:700; color:#111827;">
                                                    {{ $quotationNo }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td
                                                    style="padding:2px 12px 2px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em;">
                                                    Date
                                                </td>
                                                <td style="padding:2px 0; color:#334155;">
                                                    {{ $booking->updated_at?->format('M d, Y') ?? now()->format('M d, Y') }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td
                                                    style="padding:2px 12px 2px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em;">
                                                    Status
                                                </td>
                                                <td style="padding:2px 0;">
                                                    <span
                                                        style="display:inline-block; padding:5px 10px; border-radius:999px; background:#eef2ff; color:{{ $brandColor }}; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.08em;">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Customer + Booking Details --}}
                    <tr>
                        <td style="padding:24px 30px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top" style="width:50%; padding-right:10px;">
                                        <div
                                            style="border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#ffffff;">
                                            <p
                                                style="margin:0 0 8px; font-size:11px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.08em;">
                                                Bill To
                                            </p>

                                            <div style="font-size:14px; line-height:1.8; color:#334155;">
                                                <strong style="font-size:15px; color:#111827;">
                                                    {{ $customerName }}
                                                </strong><br>

                                                @if ($customerEmail)
                                                    {{ $customerEmail }}<br>
                                                @endif

                                                @if ($customerPhone)
                                                    {{ $customerPhone }}<br>
                                                @endif

                                                @if ($companyCustomerName)
                                                    <span
                                                        style="display:inline-block; margin-top:6px; font-weight:700; color:{{ $brandColor }};">
                                                        {{ $companyCustomerName }}
                                                    </span>
                                                @endif

                                                @if ($companyCustomerEmail)
                                                    <br>{{ $companyCustomerEmail }}
                                                @endif

                                                @if ($companyCustomerPhone)
                                                    <br>{{ $companyCustomerPhone }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td valign="top" style="width:50%; padding-left:10px;">
                                        <div
                                            style="border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#ffffff;">
                                            <p
                                                style="margin:0 0 8px; font-size:11px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.08em;">
                                                Booking Details
                                            </p>

                                            <div style="font-size:14px; line-height:1.8; color:#334155;">
                                                <strong>Booking Type:</strong>
                                                {{ $isPricingPlan ? 'Pricing Plan' : 'Service' }}<br>

                                                <strong>Service / Plan:</strong> {{ $planName }}<br>
                                                <strong>Billing:</strong> {{ $billingCycle }}<br>
                                                <strong>Status:</strong> {{ $statusLabel }}<br>

                                                {{-- @if ($customerMessage)
                                                    <div
                                                        style="margin-top:10px; padding:10px 12px; background:#f8fafc; border-left:3px solid {{ $brandColor }}; border-radius:8px; font-size:12px; line-height:1.6; color:#475569;">
                                                        <strong>Customer Message:</strong><br>
                                                        {{ $customerMessage }}
                                                    </div>
                                                @endif --}}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Quotation Table --}}
                    <tr>
                        <td style="padding:20px 30px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <thead>
                                    <tr style="background:{{ $brandColor }}; color:#ffffff;">
                                        <th align="left"
                                            style="padding:13px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; border-top-left-radius:8px;">
                                            Service / Plan
                                        </th>

                                        <th align="left"
                                            style="padding:13px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.08em;">
                                            Billing
                                        </th>

                                        <th align="right"
                                            style="padding:13px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; border-top-right-radius:8px;">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr>
                                        <td
                                            style="padding:16px 14px; border-left:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-size:14px; color:#111827; font-weight:700;">
                                            {{ $planName }}

                                            <div
                                                style="margin-top:5px; font-size:12px; font-weight:400; color:#64748b; line-height:1.6;">
                                                {{ $planDescription }}
                                            </div>
                                        </td>

                                        <td
                                            style="padding:16px 14px; border-bottom:1px solid #e5e7eb; font-size:13px; color:#334155;">
                                            {{ $billingCycle }}
                                        </td>

                                        <td align="right"
                                            style="padding:16px 14px; border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-size:14px; color:#111827; font-weight:700;">
                                            {{ $planPrice > 0 ? $formatMoney($planPrice) : 'Negotiable' }}
                                        </td>
                                    </tr>

                                    @if ($requestedPrice !== null)
                                        <tr>
                                            <td colspan="2"
                                                style="padding:13px 14px; border-left:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-size:13px; color:#64748b;">
                                                Customer Requested Amount
                                            </td>

                                            <td align="right"
                                                style="padding:13px 14px; border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-size:14px; color:#d97706; font-weight:700;">
                                                {{ $formatMoney($requestedPrice) }}
                                            </td>
                                        </tr>
                                    @endif

                                    @if ($booking->admin_note)
                                        <tr>
                                            <td colspan="3"
                                                style="padding:14px; border-left:1px solid #e5e7eb; border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; background:#f8fafc;">
                                                <p
                                                    style="margin:0 0 6px; font-size:11px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.08em;">
                                                    Quote Note / Terms
                                                </p>

                                                <p style="margin:0; font-size:13px; line-height:1.7; color:#475569;">
                                                    {{ $booking->admin_note }}
                                                </p>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Summary --}}
                    <tr>
                        <td style="padding:22px 30px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top" style="width:55%; padding-right:18px;">
                                        <div
                                            style="border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#f8fafc;">
                                            <p
                                                style="margin:0 0 8px; font-size:11px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.08em;">
                                                Next Step
                                            </p>

                                            <p style="margin:0; font-size:13px; line-height:1.7; color:#64748b;">
                                                Please review this quotation. If everything looks good, reply to this
                                                email to confirm. After confirmation, our team will prepare your order
                                                and payment process.
                                            </p>
                                        </div>
                                    </td>

                                    <td valign="top" style="width:45%; padding-left:18px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:6px 0; font-size:14px; color:#64748b;">
                                                    Listed Price
                                                </td>
                                                <td align="right"
                                                    style="padding:6px 0; font-size:14px; color:#334155;">
                                                    {{ $planPrice > 0 ? $formatMoney($subtotal) : 'Negotiable' }}
                                                </td>
                                            </tr>

                                            @if ($requestedPrice !== null)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:14px; color:#64748b;">
                                                        Requested Price
                                                    </td>
                                                    <td align="right"
                                                        style="padding:6px 0; font-size:14px; color:#d97706;">
                                                        {{ $formatMoney($requestedPrice) }}
                                                    </td>
                                                </tr>
                                            @endif

                                            @if ($discountAmount > 0)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:14px; color:#64748b;">
                                                        Discount
                                                    </td>
                                                    <td align="right"
                                                        style="padding:6px 0; font-size:14px; color:#dc2626;">
                                                        -{{ $formatMoney($discountAmount) }}
                                                    </td>
                                                </tr>
                                            @endif

                                            <tr>
                                                <td colspan="2" style="padding-top:8px;">
                                                    <div style="border-top:2px solid {{ $brandColor }};"></div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td
                                                    style="padding:14px 0 0; font-size:18px; font-weight:600; color:{{ $brandColor }}; text-transform:uppercase;">
                                                    Final Quote
                                                </td>

                                                <td align="right"
                                                    style="padding:14px 0 0; font-size:22px; font-weight:600; color:{{ $brandColor }};">
                                                    {{ $grandTotal > 0 ? $formatMoney($grandTotal) : 'Negotiable' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:34px 30px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border-top:1px solid #e5e7eb; padding-top:22px;">
                                <tr>
                                    <td valign="top" style="width:55%; padding-right:18px;">
                                        <p
                                            style="margin:0 0 8px; font-size:11px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.08em;">
                                            Terms & Conditions
                                        </p>

                                        <p style="margin:0; font-size:12px; line-height:1.7; color:#64748b;">
                                            {{ $template->terms_text ?: 'Please contact us if you have any questions regarding this quotation.' }}
                                        </p>
                                    </td>

                                    <td valign="bottom" align="right" style="width:45%; padding-left:18px;">
                                        <p
                                            style="margin:0 0 4px; font-size:22px; font-weight:700; color:{{ $brandColor }};">
                                            Thank you for your business!
                                        </p>

                                        <p style="margin:0; font-size:12px; color:#94a3b8;">
                                            {{ $template->footer_text ?: 'Empowering your digital infrastructure.' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-size:11px; color:#94a3b8;">
                    This quotation was generated from {{ $companyName }}. Please reply to this email for confirmation
                    or negotiation.
                </p>
            </td>
        </tr>
    </table>
</body>

</html>
