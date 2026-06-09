<?php

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Booking Submitted')] class extends Component {
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $booking->loadMissing(['service', 'servicePlan']);

        abort_if($booking->status !== 'pending', 404);

        if (Auth::check() && $booking->user_id && Auth::id() !== $booking->user_id) {
            abort(403);
        }

        $this->booking = $booking;
    }

    public function addonList(): array
    {
        return $this->booking->addons ?? [];
    }
};
?>

<section class="min-h-screen px-4 py-20 text-white">
    <div class="mx-auto max-w-2xl">
        {{-- Success Header --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center backdrop-blur-xl">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>

            <h1 class="mt-6 text-3xl font-bold">Booking Submitted Successfully</h1>

            <p class="mt-3 text-blue-100/70">
                Your {{ $booking->service?->card_title ?? 'Service' }} plan booking has been received. Our team will review
                your request and contact you soon.
            </p>
        </div>

        {{-- Booking Details --}}
        <div class="mt-6 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl sm:p-8">
            <div class="mb-6 flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 to-cyan-400 text-white shadow-lg shadow-blue-500/20">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>

                <div>
                    <h2 class="text-xl font-bold">Booking Details</h2>
                    <p class="mt-1 text-sm text-blue-100/55">
                        Booking No: <span class="font-mono text-cyan-200">{{ $booking->booking_no }}</span>
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                {{-- Plan Info --}}
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <p class="text-sm text-blue-100/55">Selected Plan</p>
                    <h3 class="mt-1 text-xl font-bold">{{ $booking->plan_name ?: ($booking->servicePlan?->name ?? 'N/A') }}</h3>
                    <p class="mt-1 text-sm text-blue-100/50">{{ $booking->service?->card_title ?? '' }}</p>
                </div>

                {{-- Personal Information --}}
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <div class="mb-3 flex items-center gap-2 text-cyan-200">
                        <span class="material-symbols-outlined text-lg">person</span>
                        <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Personal Information</span>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Full Name</span>
                            <span class="font-medium text-white">{{ $booking->full_name }}</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Phone</span>
                            <span class="font-medium text-white">{{ $booking->phone }}</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Email</span>
                            <span class="font-medium text-white">{{ $booking->email ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Company Information --}}
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <div class="mb-3 flex items-center gap-2 text-blue-100">
                        <span class="material-symbols-outlined text-lg">business_center</span>
                        <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Company Information</span>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Company Name</span>
                            <span class="font-medium text-white">{{ $booking->company_name ?? 'N/A' }}</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Company Phone</span>
                            <span class="font-medium text-white">{{ $booking->company_phone ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Billing & Pricing --}}
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <div class="mb-3 flex items-center gap-2 text-amber-200">
                        <span class="material-symbols-outlined text-lg">payments</span>
                        <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Billing & Pricing</span>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Billing Cycle</span>
                            <span class="font-medium capitalize text-white">{{ $booking->billing_cycle }}</span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Plan Price</span>
                            <span class="font-medium text-white">৳{{ number_format((float) $booking->plan_price, 0) }}</span>
                        </div>

                        @if ($booking->requested_price)
                            <div class="flex justify-between gap-4">
                                <span class="text-blue-100/60">Requested Price</span>
                                <span class="font-medium text-cyan-200">৳{{ number_format((float) $booking->requested_price, 0) }}</span>
                            </div>
                        @endif

                        @if ($booking->final_price)
                            <div class="flex justify-between gap-4">
                                <span class="text-blue-100/60">Total</span>
                                <span class="font-bold text-white">৳{{ number_format((float) $booking->final_price, 0) }}</span>
                            </div>
                        @endif

                        <div class="flex justify-between gap-4">
                            <span class="text-blue-100/60">Status</span>
                            <span class="font-semibold text-amber-300 capitalize">{{ $booking->status }}</span>
                        </div>
                    </div>
                </div>

                {{-- Addons --}}
                @if ($addon = $this->addonList())
                    @if (count($addon))
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <div class="mb-3 flex items-center gap-2 text-blue-100">
                                <span class="material-symbols-outlined text-lg">extension</span>
                                <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Selected Addons</span>
                            </div>

                            <div class="space-y-2 text-sm">
                                @foreach ($addon as $item)
                                    <div class="flex justify-between gap-4">
                                        <span class="text-blue-100/60">{{ $item['name'] }}</span>
                                        <span class="font-medium text-white">৳{{ number_format((float) ($item['price'] ?? 0), 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Address --}}
                @php
                    $messageParts = explode("\n\nRequirement / Message:\n", $booking->message ?? '');
                    $address = str_replace("Company Address:\n", '', $messageParts[0] ?? '');
                @endphp

                @if ($address)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="mb-3 flex items-center gap-2 text-blue-100">
                            <span class="material-symbols-outlined text-lg">location_on</span>
                            <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Company Address</span>
                        </div>

                        <p class="text-sm leading-6 text-blue-100/80 whitespace-pre-line">{{ $address }}</p>
                    </div>
                @endif

                {{-- Message --}}
                @php
                    $userNote = $messageParts[1] ?? null;
                @endphp

                @if ($userNote)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="mb-3 flex items-center gap-2 text-blue-100">
                            <span class="material-symbols-outlined text-lg">chat</span>
                            <span class="text-sm font-semibold uppercase tracking-wider text-blue-100/50">Your Message</span>
                        </div>

                        <p class="text-sm leading-6 text-blue-100/80 whitespace-pre-line">{{ $userNote }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Info Box --}}
        <div class="mt-6 rounded-3xl border border-amber-300/15 bg-amber-300/5 p-6 backdrop-blur-xl">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-400/10 text-amber-200">
                    <span class="material-symbols-outlined">info</span>
                </div>

                <div>
                    <h3 class="font-bold text-amber-100">What happens next?</h3>
                    <ul class="mt-2 space-y-1 text-sm text-amber-100/70">
                        <li>Our team will review your booking request.</li>
                        <li>You will receive a confirmation with a final quoted price.</li>
                        <li>Once approved, your booking will be converted to an active order.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ route('account.services', ['tab' => 'plans']) }}" wire:navigate
                class="inline-flex items-center justify-center gap-2 rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3 font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:-translate-y-0.5">
                <span class="material-symbols-outlined text-lg">view_list</span>
                View My Bookings
            </a>

            <a href="{{ route('home') }}" wire:navigate
                class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 font-semibold text-white transition hover:bg-white/10">
                <span class="material-symbols-outlined text-lg">home</span>
                Back to Home
            </a>
        </div>
    </div>
</section>
