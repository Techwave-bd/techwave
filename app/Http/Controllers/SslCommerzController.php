<?php

namespace App\Http\Controllers;

use App\Events\PricingOrderCreated;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Mail\OrderInvoiceMail;
use App\Models\PricingOrder;
use App\Models\PricingPlan;
use App\Models\PricingPlanBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SslCommerzController extends Controller
{
    public function pay(Request $request, PricingPlan $pricingPlan)
    {
        if (! Auth::check()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please login first to purchase a plan.');
        }

        abort_if($pricingPlan->status !== 'active', 404);

        $userId = Auth::id();

        if ($this->userHasActiveOrPendingPlan($userId)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'pricing_plan' => 'You already have an active or pending IT plan. You cannot purchase or book another plan until the current one expires or is completed.',
                ]);
        }

        $validated = $request->validate([
            'billing' => ['required', 'in:monthly,yearly'],

            // Personal info
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],

            // Company info
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'email', 'max:255'],
            'company_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/'],
            'customer_address' => ['required', 'string', 'max:500'],
        ], [
            'customer_phone.regex' => 'Please enter a valid Bangladeshi phone number. Example: 01712345678',
            'company_phone.regex' => 'Please enter a valid Bangladeshi company phone number. Example: 01712345678',
        ]);

        // Safety: yearly should not be paid directly
        if ($validated['billing'] === 'yearly') {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'pricing_plan' => 'Yearly plans are negotiable. Please submit a booking request instead of direct payment.',
                ]);
        }

        $subtotal = (float) $pricingPlan->monthly_price;

        abort_if($subtotal <= 0, 404);

        $taxRate = 0.15;
        $taxAmount = round($subtotal * $taxRate, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $customerPhone = $this->normalizeBdPhone($validated['customer_phone']);
        $companyPhone = $this->normalizeBdPhone($validated['company_phone']);

        $transactionId = 'TW-'.now()->format('Y').'-'.strtoupper(Str::random(6));

        $order = PricingOrder::query()->create([
            'user_id' => Auth::id(),
            'pricing_plan_id' => $pricingPlan->id,

            'order_no' => 'ORD-'.now()->format('Y').'-'.strtoupper(Str::random(5)),
            'transaction_id' => $transactionId,

            'billing_cycle' => 'monthly',

            'subtotal' => $subtotal,
            'tax_rate' => $taxRate * 100,
            'tax_amount' => $taxAmount,
            'amount' => $totalAmount,

            'currency' => 'BDT',
            'payment_status' => 'pending',

            // Personal info snapshot
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $customerPhone,

            // Company info snapshot
            'company_name' => $validated['company_name'],
            'company_email' => $validated['company_email'],
            'company_phone' => $companyPhone,
            'customer_address' => $validated['customer_address'],
        ]);

        $order->update([
            'order_no' => 'ORD-'.now()->format('Y').'-'.$order->id,
        ]);

        $postData = [
            'total_amount' => $order->amount,
            'currency' => $order->currency,
            'tran_id' => $order->transaction_id,

            'success_url' => route('sslcommerz.success'),
            'fail_url' => route('sslcommerz.fail'),
            'cancel_url' => route('sslcommerz.cancel'),
            'ipn_url' => route('sslcommerz.ipn'),

            'cus_name' => $order->customer_name,
            'cus_email' => $order->customer_email,
            'cus_add1' => $order->customer_address,
            'cus_add2' => '',
            'cus_city' => 'Dhaka',
            'cus_state' => 'Dhaka',
            'cus_postcode' => '1200',
            'cus_country' => 'Bangladesh',
            'cus_phone' => '+88'.$order->customer_phone,

            'shipping_method' => 'NO',
            'num_of_item' => 1,

            'product_name' => $pricingPlan->title,
            'product_category' => 'IT Service Plan',
            'product_profile' => 'non-physical-goods',

            'order_id' => $order->id,
            'pricing_id' => $pricingPlan->id,
            'pricing_cycle' => 'monthly',
            'user_id' => Auth::id(),
        ];

        $sslcz = new SslCommerzNotification;

        return $sslcz->makePayment($postData, 'hosted');
    }

    public function success(Request $request)
    {
        // dd($request->all());
        $transactionId = $request->input('tran_id');

        $order = PricingOrder::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $order) {
            return redirect()->route('home')->with('error', 'Order not found.');
        }

        $status = $request->input('status');

        if (in_array($status, ['VALID', 'VALIDATED']) && $order->payment_status !== 'paid') {
            $startsAt = now();

            $expiresAt = $order->billing_cycle === 'yearly'
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth();

            $order->update([
                'payment_status' => 'paid',
                'ssl_status' => $status,
                'bank_transaction_id' => $request->input('bank_tran_id'),
                'val_id' => $request->input('val_id'),
                'payment_response' => $request->all(),
                'paid_at' => now(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);

            $order->pricingPlan()->increment('purchase_count');

            $email = $order->user?->email;
            if ($email) {
                Mail::to($email)->send(new OrderInvoiceMail($order));
            }

            event(new PricingOrderCreated($order->fresh(['pricingPlan', 'user'])));
        }

        // Guard: if still not paid, something went wrong
        if ($order->fresh()->payment_status !== 'paid') {
            return redirect()->route('home')->with('error', 'Payment could not be verified.');
        }

        return redirect()
            ->route('client.checkout.success', $order->id)
            ->with('success', 'Payment completed successfully.');
    }

    public function fail(Request $request)
    {
        return $this->markFailed($request, 'failed');
    }

    public function cancel(Request $request)
    {
        return $this->markFailed($request, 'cancelled');
    }

    public function ipn(Request $request)
    {
        return $this->handleResponse($request, true);
    }

    private function handleResponse(Request $request, bool $isIpn = false)
    {
        $transactionId = $request->input('tran_id');
        $amount = $request->input('amount');
        $currency = $request->input('currency');

        $order = PricingOrder::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $order) {
            return $isIpn
                ? response('Order not found', 404)
                : redirect()->route('home')->with('error', 'Order not found.');
        }

        $sslcz = new SslCommerzNotification;

        $validation = $sslcz->orderValidate(
            $request->all(),
            $transactionId,
            $amount,
            $currency
        );

        if ($validation === true) {
            if ($order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'paid',
                    'ssl_status' => $request->input('status'),
                    'bank_transaction_id' => $request->input('bank_tran_id'),
                    'val_id' => $request->input('val_id'),
                    'payment_response' => $request->all(),
                    'paid_at' => now(),
                ]);

                $order->pricingPlan()->increment('purchase_count');
            }

            return $isIpn
                ? response('IPN received', 200)
                : redirect()->route('client.checkout.success', $order->id)
                    ->with('success', 'Payment completed successfully.');
        }

        $order->update([
            'payment_status' => 'failed',
            'ssl_status' => $request->input('status') ?: 'validation_failed',
            'payment_response' => $request->all(),
        ]);

        return $isIpn
            ? response('Payment validation failed', 400)
            : redirect()->route('client.checkout.pricing', [
                'pricingPlan' => $order->pricing_plan_id,
                'billing' => $order->billing_cycle,
            ])->with('error', 'Payment validation failed.');
    }

    private function markFailed(Request $request, string $status)
    {
        $order = PricingOrder::query()
            ->where('transaction_id', $request->input('tran_id'))
            ->first();

        if ($order) {
            $order->update([
                'payment_status' => $status,
                'ssl_status' => $request->input('status'),
                'payment_response' => $request->all(),
            ]);
        }

        return redirect()
            ->route('home')
            ->with('error', 'Payment '.$status.'.');
    }

    private function normalizeBdPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/[\s\-()]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '88')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }

    private function userHasActiveOrPendingPlan(int $userId): bool
    {
        $hasActiveOrder = PricingOrder::query()
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query
                    ->where(function ($subQuery) {
                        $subQuery
                            ->where('payment_status', 'paid')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>=', now());
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('payment_status', 'pending');
                    });
            })
            ->exists();

        $hasActiveBooking = PricingPlanBooking::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'reviewing', 'quoted', 'accepted'])
            ->exists();

        return $hasActiveOrder || $hasActiveBooking;
    }
}
