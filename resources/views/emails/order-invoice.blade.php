@php
    $brandColor = $template->brand_color ?: '#0F52BA';

    $customerName = $order->full_name ?: $order->user?->name ?? 'Customer';
    $customerEmail = $order->email ?: $order->user?->email ?? '';
    $customerPhone = $order->phone ?: $order->user?->phone ?? '';
    $customerCompany = $order->company_name ?: '';

    $invoiceNo = $order->order_no ?? 'INV-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

    $isPricingPlan = $order->order_type === 'pricing_plan';

    $itemName = $isPricingPlan
        ? $order->pricingPlan?->title ?? ($order->plan_name ?? 'Pricing Plan')
        : $order->service?->card_title ?? ($order->servicePlan?->name ?? ($order->plan_name ?? 'Service'));

    $description = $isPricingPlan
        ? $order->pricingPlan?->description ?? 'Business IT plan subscription.'
        : $order->servicePlan?->description ?? ($order->service?->short_description ?? 'Service order.');

    $billingCycle = match ($order->billing_cycle) {
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'one_time' => 'One-time',
        'custom' => 'Custom',
        default => 'Negotiable',
    };

    $statusLabel = ucfirst(str_replace('_', ' ', $order->status ?? 'awaiting_payment'));

    $subtotal =
        (float) ($order->amount ?? ($order->final_price ?? ($order->quoted_price ?? ($order->plan_price ?? 0))));
    $discount = 0;
    $total = max($subtotal - $discount, 0);

    $currency = $order->currency === 'BDT' || blank($order->currency) ? '৳' : $order->currency . ' ';
    $formatMoney = fn($amount) => $currency . number_format((float) $amount, 2);

    $companyName = $setting?->site_name ?? config('app.name');
    $companyEmail = $setting?->email ?? config('mail.from.address');
    $companyPhone = $setting?->phone ?? '';
    $companyAddress = $setting?->location ?? '';
    $companyWebsite = config('app.url');

    $logoCid = null;

    if (!empty($logoPath) && file_exists($logoPath)) {
        $logoCid = $message->embed($logoPath);
    }
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $template->title ?: 'Service Invoice' }}</title>
</head>

<body style="margin:0; padding:0; background:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:30px 12px;">
        <tr>
            <td align="center">
                <table width="720" cellpadding="0" cellspacing="0"
                    style="width:720px; max-width:100%; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e2e8f0;">

                    <tr>
                        <td style="padding:26px 32px 22px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="58%" valign="top">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td valign="middle" style="padding-right:14px;">
                                                    <table width="88" height="56" cellpadding="0" cellspacing="0"
                                                        style="width:88px; height:56px;">
                                                        <tr>
                                                            <td align="center" valign="middle"
                                                                style="width:88px; height:56px; text-align:center; vertical-align:middle;">
                                                                @if ($logoCid)
                                                                    <img src="{{ $logoCid }}"
                                                                        alt="{{ $companyName }}" width="80"
                                                                        style="width:80px; max-width:80px; max-height:48px; display:inline-block; vertical-align:middle;">
                                                                @else
                                                                    <span
                                                                        style="font-size:10px; font-weight:700; color:#94a3b8;">
                                                                        Logo
                                                                    </span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>

                                                <td valign="middle">
                                                    <h1
                                                        style="margin:0; font-size:18px; line-height:22px; font-weight:800; letter-spacing:-0.02em; color:#0f172a; text-transform:uppercase;">
                                                        {{ $companyName }}
                                                    </h1>

                                                    <p
                                                        style="margin:5px 0 0; font-size:9px; line-height:13px; text-transform:uppercase; letter-spacing:1.6px; color:#64748b;">
                                                        {{ $template->title ?: 'Service Invoice' }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <div style="margin-top:16px; font-size:11px; line-height:18px; color:#64748b;">
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
                                                <div>{{ $companyWebsite }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <td width="42%" valign="top" align="right">
                                        <h2
                                            style="margin:0 0 14px; font-size:26px; line-height:30px; font-weight:900; color:{{ $brandColor }};">
                                            INVOICE
                                        </h2>

                                        <table cellpadding="0" cellspacing="0" align="right"
                                            style="font-size:11px; line-height:17px;">
                                            <tr>
                                                <td
                                                    style="padding:3px 14px 3px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px;">
                                                    Invoice #
                                                </td>
                                                <td style="padding:3px 0; font-weight:700; color:#0f172a;">
                                                    {{ $invoiceNo }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td
                                                    style="padding:3px 14px 3px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px;">
                                                    Date Issued
                                                </td>
                                                <td style="padding:3px 0; color:#475569;">
                                                    {{ $order->created_at?->format('M d, Y') ?? now()->format('M d, Y') }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td
                                                    style="padding:3px 14px 3px 0; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px;">
                                                    Status
                                                </td>
                                                <td style="padding:3px 0;">
                                                    <span
                                                        style="display:inline-block; padding:4px 9px; border-radius:999px; background:#dcfce7; color:#15803d; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px;">
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

                    <tr>
                        <td style="padding:0 32px 12px;">
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border:1px solid #e2e8f0; border-radius:14px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p
                                            style="margin:0 0 9px; font-size:10px; line-height:14px; font-weight:800; text-transform:uppercase; letter-spacing:1.1px; color:#94a3b8;">
                                            Bill To
                                        </p>

                                        <div style="font-size:12px; line-height:20px; color:#475569;">
                                            <div>
                                                <strong style="color:#0f172a;">Name:</strong>
                                                {{ $customerName }}
                                            </div>

                                            @if ($customerEmail)
                                                <div>
                                                    <strong style="color:#0f172a;">Email:</strong>
                                                    {{ $customerEmail }}
                                                </div>
                                            @endif

                                            @if ($customerPhone)
                                                <div>
                                                    <strong style="color:#0f172a;">Phone:</strong>
                                                    {{ $customerPhone }}
                                                </div>
                                            @endif

                                            @if ($customerCompany)
                                                <div style="color:{{ $brandColor }}; font-weight:700;">
                                                    <strong style="color:#0f172a;">Company:</strong>
                                                    {{ $customerCompany }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 32px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border-collapse:collapse; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                <thead>
                                    <tr style="background:{{ $brandColor }};">
                                        <th align="left"
                                            style="padding:11px 12px; font-size:10px; color:#ffffff; text-transform:uppercase; letter-spacing:0.8px;">
                                            Item
                                        </th>

                                        <th align="left"
                                            style="padding:11px 12px; font-size:10px; color:#ffffff; text-transform:uppercase; letter-spacing:0.8px;">
                                            Description
                                        </th>

                                        <th align="center"
                                            style="padding:11px 12px; font-size:10px; color:#ffffff; text-transform:uppercase; letter-spacing:0.8px;">
                                            Billing
                                        </th>

                                        <th align="right"
                                            style="padding:11px 12px; font-size:10px; color:#ffffff; text-transform:uppercase; letter-spacing:0.8px;">
                                            Unit Price
                                        </th>

                                        <th align="right"
                                            style="padding:11px 12px; font-size:10px; color:#ffffff; text-transform:uppercase; letter-spacing:0.8px;">
                                            Total
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr style="background:#ffffff;">
                                        <td
                                            style="padding:13px 12px; border-top:1px solid #e2e8f0; font-size:11px; font-weight:800; color:#0f172a;">
                                            {{ $itemName }}
                                        </td>

                                        <td
                                            style="padding:13px 12px; border-top:1px solid #e2e8f0; font-size:10px; line-height:16px; color:#64748b;">
                                            {{ $description }}
                                        </td>

                                        <td align="center"
                                            style="padding:13px 12px; border-top:1px solid #e2e8f0; font-size:11px; color:#475569;">
                                            {{ $billingCycle }}
                                        </td>

                                        <td align="right"
                                            style="padding:13px 12px; border-top:1px solid #e2e8f0; font-size:11px; color:#475569;">
                                            {{ $formatMoney($subtotal) }}
                                        </td>

                                        <td align="right"
                                            style="padding:13px 12px; border-top:1px solid #e2e8f0; font-size:11px; font-weight:800; color:#0f172a;">
                                            {{ $formatMoney($total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    @if ($order->start_date || $order->end_date)
                        <tr>
                            <td style="padding:16px 32px 0;">
                                <table width="100%" cellpadding="0" cellspacing="0"
                                    style="border:1px solid #e2e8f0; border-radius:14px;">
                                    <tr>
                                        <td style="padding:14px 16px; font-size:12px; color:#475569;">
                                            <strong style="color:#0f172a;">Service Period:</strong>
                                            {{ $order->start_date?->format('M d, Y') ?? 'N/A' }}
                                            -
                                            {{ $order->end_date?->format('M d, Y') ?? 'N/A' }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:22px 32px 0;" align="right">
                            <table width="290" cellpadding="0" cellspacing="0" style="max-width:100%;">
                                <tr>
                                    <td style="padding:5px 0; font-size:12px; color:#64748b;">
                                        Subtotal
                                    </td>

                                    <td align="right" style="padding:5px 0; font-size:12px; color:#334155;">
                                        {{ $formatMoney($subtotal) }}
                                    </td>
                                </tr>

                                @if ($discount > 0)
                                    <tr>
                                        <td style="padding:5px 0; font-size:12px; color:#64748b;">
                                            Discount
                                        </td>

                                        <td align="right" style="padding:5px 0; font-size:12px; color:#dc2626;">
                                            -{{ $formatMoney($discount) }}
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td colspan="2"
                                        style="padding-top:8px; border-top:2px solid {{ $brandColor }};"></td>
                                </tr>

                                <tr>
                                    <td
                                        style="padding-top:7px; font-size:15px; font-weight:900; text-transform:uppercase; color:{{ $brandColor }};">
                                        Total Amount
                                    </td>

                                    <td align="right"
                                        style="padding-top:7px; font-size:17px; font-weight:900; color:{{ $brandColor }};">
                                        {{ $formatMoney($total) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px;">
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border-top:1px solid #e2e8f0;">
                                <tr>
                                    <td width="50%" valign="top" style="padding-top:22px;">
                                        <h4
                                            style="margin:0 0 8px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1.1px; color:#94a3b8;">
                                            Terms & Conditions
                                        </h4>

                                        <p style="margin:0; font-size:11px; line-height:18px; color:#64748b;">
                                            {{ $template->terms_text ?: 'Please contact support for invoice related queries.' }}
                                        </p>
                                    </td>

                                    <td width="50%" valign="bottom" align="right" style="padding-top:22px;">
                                        <p
                                            style="margin:0 0 6px; font-size:16px; line-height:21px; font-weight:800; color:{{ $brandColor }};">
                                            Thank you for your business!
                                        </p>

                                        <p style="margin:0; font-size:11px; line-height:17px; color:#94a3b8;">
                                            {{ $template->footer_text ?: 'Empowering your digital infrastructure.' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
