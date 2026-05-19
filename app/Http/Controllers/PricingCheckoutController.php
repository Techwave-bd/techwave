<?php

namespace App\Http\Controllers;

use App\Events\PricingPlanBookingCreated;
use App\Models\PricingOrder;
use App\Models\PricingPlan;
use App\Models\PricingPlanBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PricingCheckoutController extends Controller
{
    public function booking(Request $request, PricingPlan $pricingPlan)
    {
        if (! Auth::check()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please login first to purchase or book a plan.');
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
            'billing' => ['required', 'in:yearly'],

            // Personal info
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/',],

            // Company info
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'email', 'max:255'],
            'company_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+88|88)?01[3-9][0-9]{8}$/',],
            'customer_address' => ['required', 'string', 'max:500'],

            // Negotiation info
            'requested_price' => ['nullable', 'numeric', 'min:0'],
            'user_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'customer_phone.regex' => 'Please enter a valid Bangladeshi phone number. Example: 01712345678',
            'company_phone.regex' => 'Please enter a valid Bangladeshi company phone number. Example: 01712345678',
        ]);

        $subtotal = (float) $pricingPlan->yearly_price;

        abort_if($subtotal <= 0, 404);

        $customerPhone = $this->normalizeBdPhone($validated['customer_phone']);
        $companyPhone = $this->normalizeBdPhone($validated['company_phone']);

        $booking = PricingPlanBooking::query()->create([
            'user_id' => Auth::id(),
            'pricing_plan_id' => $pricingPlan->id,

            'booking_no' => 'BK-' . now()->format('Y') . '-' . strtoupper(Str::random(6)),
            'billing_cycle' => 'yearly',

            // Personal info snapshot
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $customerPhone,

            // Company info snapshot
            'company_name' => $validated['company_name'],
            'company_email' => $validated['company_email'],
            'company_phone' => $companyPhone,
            'customer_address' => $validated['customer_address'],

            // Price negotiation
            'plan_price' => $subtotal,
            'requested_price' => $validated['requested_price'] ?? null,
            'quoted_price' => null,

            'user_note' => $validated['user_note'] ?? null,
            'admin_note' => null,

            'status' => 'pending',
        ]);

        $booking->update([
            'booking_no' => 'BK-' . now()->format('Y') . '-' . $booking->id,
        ]);

        event(new PricingPlanBookingCreated($booking->fresh(['pricingPlan', 'user'])));

        return redirect()
            ->route('account.services', ['tab' => 'plans'])
            ->with('success', 'Your yearly plan booking request has been submitted successfully. Our team will review it and contact you soon.');
    }

    private function normalizeBdPhone(?string $phone): ?string
    {
        if (!$phone) {
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
                    // Paid and not expired
                    ->where(function ($subQuery) {
                        $subQuery
                            ->where('payment_status', 'paid')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>=', now());
                    })

                    // Payment still pending
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('payment_status', 'pending');
                    });
            })
            ->exists();

        $hasActiveBooking = PricingPlanBooking::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'reviewing', 'quoted', 'accepted',])
            ->exists();

        return $hasActiveOrder || $hasActiveBooking;
    }
}
