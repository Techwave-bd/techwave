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

<section class="min-h-screen px-4 py-16 text-white sm:py-20">
    <div class="mx-auto max-w-6xl px-0 sm:px-6 lg:px-8">

        {{-- Prepare Address / Message --}}
        @php
            $messageParts = explode("\n\nRequirement / Message:\n", $booking->message ?? '');
            $address = str_replace("Company Address:\n", '', $messageParts[0] ?? '');
            $userNote = $messageParts[1] ?? null;
        @endphp

        {{-- Success Header --}}
        <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.06] p-6 text-center shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-10">
            <div class="absolute -left-24 -top-24 h-56 w-56 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="absolute -right-24 -bottom-24 h-56 w-56 rounded-full bg-cyan-400/20 blur-3xl"></div>

            <div class="relative">
                {{-- <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-emerald-300/20 bg-emerald-500/15 text-emerald-300 shadow-lg shadow-emerald-500/10">
                    <span class="material-symbols-outlined text-5xl">check_circle</span>
                </div> --}}

                <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/15 bg-emerald-400/10 px-4 py-1.5 text-sm font-semibold text-emerald-200">
                    <span class="material-symbols-outlined text-base">verified</span>
                    Request Received
                </div>

                <h1 class="mt-5 text-2xl font-bold tracking-tight sm:text-4xl">
                    Booking Submitted Successfully
                </h1>

                <p class="mx-auto mt-4 max-w-2xl text-sm leading-6 text-blue-100/70 sm:text-base">
                    Your {{ $booking->service?->card_title ?? 'Service' }} plan booking has been received.
                    Our team will review your request and contact you soon.
                </p>

                <div class="mx-auto mt-6 flex max-w-xl flex-col gap-3 rounded-2xl border border-white/10 bg-black/10 p-4 text-left sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-blue-100/40">Booking No</p>
                        <p class="mt-1 font-mono text-lg font-bold text-cyan-200">{{ $booking->booking_no }}</p>
                    </div>

                    <div class="flex items-center gap-2 rounded-full border border-amber-300/20 bg-amber-300/10 px-4 py-2 text-sm font-semibold text-amber-200">
                        <span class="material-symbols-outlined text-lg">pending_actions</span>
                        <span class="capitalize">{{ $booking->status }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">

            {{-- Left Column --}}
            <div class="space-y-6">
                <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-5 shadow-xl shadow-black/10 backdrop-blur-xl sm:p-8">

                    <div class="mb-6 flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 to-cyan-400 text-white shadow-lg shadow-blue-500/20">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </div>

                        <div>
                            <h2 class="text-xl font-bold sm:text-2xl">Booking Details</h2>
                            <p class="mt-1 text-sm text-blue-100/55">
                                Review your submitted booking information.
                            </p>
                        </div>
                    </div>

                    {{-- Plan Highlight --}}
                    {{-- <div class="rounded-3xl border border-cyan-300/15 bg-linear-to-br from-cyan-400/10 via-white/[0.04] to-blue-500/10 p-5">
                        <p class="text-sm font-medium text-cyan-200/80">Selected Plan</p>

                        <h3 class="mt-2 text-2xl font-bold">
                            {{ $booking->plan_name ?: ($booking->servicePlan?->name ?? 'N/A') }}
                        </h3>

                        <p class="mt-1 text-sm text-blue-100/50">
                            {{ $booking->service?->card_title ?? '' }}
                        </p>
                    </div> --}}

                    {{-- Details Grid --}}
                    <div class="mt-6 grid gap-4 md:grid-cols-2">

                        {{-- Personal Information --}}
                        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-cyan-400/10 text-cyan-200">
                                    <span class="material-symbols-outlined text-xl">person</span>
                                </div>

                                <div>
                                    <h3 class="font-bold">Personal Information</h3>
                                    <p class="text-xs text-blue-100/45">Customer contact details</p>
                                </div>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div class="rounded-2xl bg-black/10 p-3">
                                    <p class="text-xs text-blue-100/45">Full Name</p>
                                    <p class="mt-1 break-words font-semibold text-white">{{ $booking->full_name }}</p>
                                </div>

                                <div class="rounded-2xl bg-black/10 p-3">
                                    <p class="text-xs text-blue-100/45">Phone</p>
                                    <p class="mt-1 break-words font-semibold text-white">{{ $booking->phone }}</p>
                                </div>

                                <div class="rounded-2xl bg-black/10 p-3">
                                    <p class="text-xs text-blue-100/45">Email</p>
                                    <p class="mt-1 break-words font-semibold text-white">{{ $booking->email ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Company Information --}}
                        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-400/10 text-blue-200">
                                    <span class="material-symbols-outlined text-xl">business_center</span>
                                </div>

                                <div>
                                    <h3 class="font-bold">Company Information</h3>
                                    <p class="text-xs text-blue-100/45">Business contact details</p>
                                </div>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div class="rounded-2xl bg-black/10 p-3">
                                    <p class="text-xs text-blue-100/45">Company Name</p>
                                    <p class="mt-1 break-words font-semibold text-white">{{ $booking->company_name ?? 'N/A' }}</p>
                                </div>

                                <div class="rounded-2xl bg-black/10 p-3">
                                    <p class="text-xs text-blue-100/45">Company Phone</p>
                                    <p class="mt-1 break-words font-semibold text-white">{{ $booking->company_phone ?? 'N/A' }}</p>
                                </div>

                                @if ($address)
                                    <div class="rounded-2xl bg-black/10 p-3">
                                        <p class="text-xs text-blue-100/45">Company Address</p>
                                        <p class="mt-1 whitespace-pre-line break-words font-semibold leading-6 text-white">{{ $address }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Addons --}}
                    @if ($addon = $this->addonList())
                        @if (count($addon))
                            <div class="mt-4 rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                                <div class="mb-4 flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-400/10 text-purple-200">
                                        <span class="material-symbols-outlined text-xl">extension</span>
                                    </div>

                                    <div>
                                        <h3 class="font-bold">Selected Addons</h3>
                                        <p class="text-xs text-blue-100/45">Additional services included with your request</p>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($addon as $item)
                                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-black/10 p-3 text-sm">
                                            <span class="break-words text-blue-100/75">{{ $item['name'] }}</span>

                                            <span class="shrink-0 font-semibold text-white">
                                                ৳{{ number_format((float) ($item['price'] ?? 0), 0) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Message --}}
                    @if ($userNote)
                        <div class="mt-4 rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-400/10 text-sky-200">
                                    <span class="material-symbols-outlined text-xl">chat</span>
                                </div>

                                <div>
                                    <h3 class="font-bold">Your Message</h3>
                                    <p class="text-xs text-blue-100/45">Requirement or extra note</p>
                                </div>
                            </div>

                            <p class="whitespace-pre-line rounded-2xl bg-black/10 p-4 text-sm leading-6 text-blue-100/80">{{ $userNote }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6 lg:sticky lg:top-24 lg:self-start">

                {{-- Billing & Pricing --}}
                <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-5 shadow-xl shadow-black/10 backdrop-blur-xl sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-400/10 text-amber-200">
                            <span class="material-symbols-outlined">payments</span>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold">Billing Summary</h3>
                            <p class="text-xs text-blue-100/45">Price and request status</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-black/10 p-3">
                            <span class="text-blue-100/60">Selected Plan</span>
                            <span class="font-semibold capitalize text-white">{{ $booking->plan_name ?: ($booking->servicePlan?->name ?? 'N/A') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-black/10 p-3">
                            <span class="text-blue-100/60">Billing Cycle</span>
                            <span class="font-semibold capitalize text-white">{{ $booking->billing_cycle }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-black/10 p-3">
                            <span class="text-blue-100/60">Plan Price</span>
                            <span class="font-semibold text-white">৳{{ number_format((float) $booking->plan_price, 0) }}</span>
                        </div>

                        @if ($booking->requested_price)
                            <div class="flex items-center justify-between gap-4 rounded-2xl bg-cyan-400/10 p-3">
                                <span class="text-cyan-100/70">Requested Price</span>
                                <span class="font-semibold text-cyan-200">৳{{ number_format((float) $booking->requested_price, 0) }}</span>
                            </div>
                        @endif

                        @if ($booking->final_price)
                            <div class="rounded-3xl border border-emerald-300/20 bg-emerald-400/10 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="font-semibold text-emerald-100">Total</span>
                                    <span class="text-2xl font-bold text-white">
                                        ৳{{ number_format((float) $booking->final_price, 0) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Info Box --}}
                <div class="rounded-[2rem] border border-amber-300/15 bg-amber-300/[0.07] p-5 backdrop-blur-xl sm:p-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-400/10 text-amber-200">
                            <span class="material-symbols-outlined">info</span>
                        </div>

                        <div>
                            <h3 class="font-bold text-amber-100">What happens next?</h3>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3 text-sm text-amber-100/75">
                                <div class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300/10 text-xs font-bold text-amber-200">1</span>
                                    <p>Our team will review your booking request.</p>
                                </div>

                                <div class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300/10 text-xs font-bold text-amber-200">2</span>
                                    <p>You will receive a confirmation with a final quoted price.</p>
                                </div>

                                <div class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300/10 text-xs font-bold text-amber-200">3</span>
                                    <p>Once approved, your booking will be converted to an active order.</p>
                                </div>
                            </div>
                </div>

                {{-- Actions --}}
                {{-- <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-5 backdrop-blur-xl sm:p-6">
                    <div class="space-y-3">
                        <a href="{{ route('account.services', ['tab' => 'plans']) }}" wire:navigate
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:-translate-y-0.5 hover:shadow-blue-500/30">
                            <span class="material-symbols-outlined text-lg">view_list</span>
                            View My Bookings
                        </a>

                        <a href="{{ route('home') }}" wire:navigate
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-6 py-3.5 font-semibold text-white transition hover:bg-white/10">
                            <span class="material-symbols-outlined text-lg">home</span>
                            Back to Home
                        </a>
                    </div>
                </div> --}}

            </div>
        </div>
    </div>
</section>