<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use App\Models\PricingOrder;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanOrderInvoiceController extends Controller
{
    public function download(PricingOrder $order)
    {
        $order->loadMissing(['user', 'pricingPlan']);

        $template = InvoiceTemplate::activeTemplate();
        $setting = SiteSetting::query()->first();

        $pdf = Pdf::loadView('pdf.order-invoice', [
            'order' => $order,
            'template' => $template,
            'setting' => $setting,
        ])
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->download(($order->order_no ?? 'invoice').'.pdf');
    }
}
