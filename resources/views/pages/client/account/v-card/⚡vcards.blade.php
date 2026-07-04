<?php

use App\Models\Vcard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Title('My vCards')] class extends Component {
    public function mount(): void
    {
        abort_if(!Auth::check(), 403);
    }

    public function formatDate($date): string
    {
        if (!$date) {
            return 'N/A';
        }

        return Carbon::parse($date)->format('d M Y');
    }

    public function timeAgo($date): string
    {
        if (!$date) {
            return 'N/A';
        }

        return Carbon::parse($date)->diffForHumans();
    }

    public function deleteVcard(int $id): void
    {
        $vcard = Vcard::query()->where('user_id', auth()->id())->findOrFail($id);
        $vcard->delete();

        $this->dispatch('toast', type: 'success', message: 'vCard deleted successfully.');
    }

    public function downloadQr(int $vcardId): \Illuminate\Http\Response
    {
        $vcard = Vcard::query()->where('user_id', auth()->id())->findOrFail($vcardId);

        $url = route('vcard.public.show', ['slug' => $vcard->slug]);
        $svg = QrCode::format('svg')->size(512)->margin(1)->errorCorrection('H')->generate($url);

        $filename = Str::slug($vcard->name ?: $vcard->full_name) ?: 'vcard';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-qr.svg"',
        ]);
    }

    public function with(): array
    {
        $userId = Auth::id();

        $vcards = Vcard::query()
            ->where('user_id', $userId)
            ->withCount('scans')
            ->latest()
            ->get()
            ->map(function (Vcard $vcard): array {
                $weeklyScans = $vcard
                    ->scans()
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count();

                $publicUrl = route('vcard.public.show', ['slug' => $vcard->slug]);
                $qrSvg = QrCode::format('svg')->size(96)->margin(1)->errorCorrection('H')->generate($publicUrl);

                $firstScan = $vcard->scans()->oldest()->first();

                return [
                    'id' => $vcard->id,
                    'name' => $vcard->name ?: $vcard->full_name,
                    'slug' => $vcard->slug,
                    'is_active' => (bool) $vcard->is_active,
                    'scans_count' => (int) ($vcard->scans_count ?? 0),
                    'weekly_scans' => $weeklyScans,
                    'created_at' => $vcard->created_at,
                    'first_scan_at' => $firstScan?->created_at,
                    'qr_svg' => $qrSvg,
                    'public_url' => $publicUrl,
                ];
            })->toArray();

        $totalVcards = count($vcards);
        $totalScans = array_sum(array_column($vcards, 'scans_count'));
        $activeCount = count(array_filter($vcards, fn($v) => $v['is_active']));

        return [
            'vcards' => $vcards,
            'totalVcards' => $totalVcards,
            'totalScans' => $totalScans,
            'activeCount' => $activeCount,
        ];
    }
};
?>

<div x-data="{ sidebarOpen: false, confirmDeleteId: null, confirmDeleteName: '' }" class="relative min-h-screen text-white">

    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div
            class="rounded-[34px] border border-white/10 bg-white/6 shadow-[0_20px_80px_rgba(0,0,0,0.22)] backdrop-blur-2xl">
            <div class="flex min-h-[calc(100vh-3rem)]">

                <div x-show="sidebarOpen" x-transition.opacity
                    class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"
                    style="display:none;">
                </div>

                <livewire:shared.user-sidebar />

                <div class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">

                    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <button @click="sidebarOpen = true"
                                class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-white shadow-[0_10px_30px_rgba(0,0,0,0.18)] backdrop-blur-xl transition hover:bg-white/12 lg:hidden">
                                <span class="material-symbols-outlined">menu</span>
                            </button>

                            <div>
                                <p class="text-[10px] uppercase tracking-[0.18em] text-blue-100/45">Client Dashboard</p>
                                <h1 class="mt-0.5 text-xl font-bold text-white">My vCards</h1>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/8 px-3 py-2 backdrop-blur-xl">
                            <span class="material-symbols-outlined text-sm text-cyan-200">qr_code_2</span>
                            <div>
                                <p class="text-[10px] text-blue-100/45">Total vCards</p>
                                <p class="text-xs font-semibold text-white">{{ $totalVcards }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Stats Cards --}}
                    <div class="mb-5 grid gap-4 sm:grid-cols-3">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/8 p-4 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-[10px] uppercase tracking-[0.18em] text-blue-100/45">Total Scans</p>
                            <h3 class="mt-2 text-2xl font-bold text-white">
                                {{ str_pad($totalScans, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-1 text-[11px] text-blue-100/60">All time vCard scans</p>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/8 p-4 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-[10px] uppercase tracking-[0.18em] text-blue-100/45">Active vCards</p>
                            <h3 class="mt-2 text-2xl font-bold text-white">
                                {{ str_pad($activeCount, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-1 text-[11px] text-blue-100/60">{{ $totalVcards - $activeCount }} inactive</p>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/8 p-4 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <p class="text-[10px] uppercase tracking-[0.18em] text-blue-100/45">Created</p>
                            <h3 class="mt-2 text-2xl font-bold text-white">
                                {{ str_pad($totalVcards, 2, '0', STR_PAD_LEFT) }}
                            </h3>
                            <p class="mt-1 text-[11px] text-blue-100/60">Total vCards created</p>
                        </div>
                    </div>

                    {{-- vCards List --}}
                    @if (count($vcards) > 0)
                        <div class="grid gap-4">
                            @foreach ($vcards as $vcard)
                                <div
                                    class="rounded-2xl border border-white/10 bg-white/8 p-4 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                                        {{-- QR Code --}}
                                        <div class="flex shrink-0 flex-col items-center gap-2">
                                            <div class="flex items-center justify-center overflow-hidden rounded-xl bg-white p-1.5 shadow-lg">
                                                <div class="flex items-center justify-center h-24 w-24">
                                                    {!! $vcard['qr_svg'] !!}
                                                </div>
                                            </div>
                                            <button type="button" wire:click="downloadQr({{ $vcard['id'] }})"
                                                class="flex items-center gap-1 rounded-lg border border-white/10 bg-white/8 px-3 py-1.5 text-[10px] font-semibold text-blue-100/70 transition hover:bg-white/12 hover:text-white">
                                                <span class="material-symbols-outlined text-xs">download</span>
                                                Download QR
                                            </button>
                                        </div>

                                        {{-- Details --}}
                                        <div class="min-w-0 flex-1 space-y-3">

                                            {{-- Header --}}
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <h2 class="text-sm font-bold text-white truncate">{{ $vcard['name'] }}</h2>
                                                    <p class="mt-0.5 truncate text-xs text-blue-100/55">
                                                        <span class="material-symbols-outlined align-middle text-[10px]">link</span>
                                                        <a href="{{ $vcard['public_url'] }}" target="_blank" rel="noopener"
                                                            class="underline decoration-white/20 underline-offset-2 hover:decoration-white/40">
                                                            {{ $vcard['public_url'] }}
                                                        </a>
                                                    </p>
                                                </div>

                                                <span @class([
                                                    'shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                                    'border-emerald-300/20 bg-emerald-400/10 text-emerald-200' => $vcard['is_active'],
                                                    'border-slate-300/20 bg-slate-400/10 text-slate-200' => !$vcard['is_active'],
                                                ])>
                                                    {{ $vcard['is_active'] ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>

                                            {{-- Stats Grid --}}
                                            <div class="grid grid-cols-4 gap-2">
                                                <div
                                                    class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-2 text-center">
                                                    <p class="text-sm font-bold text-cyan-200">{{ $vcard['scans_count'] }}</p>
                                                    <p class="mt-0.5 text-[9px] font-medium text-blue-100/55">Scans</p>
                                                </div>
                                                <div
                                                    class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-2 text-center">
                                                    <p class="text-sm font-bold text-cyan-200">{{ $vcard['weekly_scans'] }}</p>
                                                    <p class="mt-0.5 text-[9px] font-medium text-blue-100/55">Week</p>
                                                </div>
                                                <div
                                                    class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-2 text-center">
                                                    <p class="text-sm font-bold text-white">{{ $this->formatDate($vcard['created_at']) }}</p>
                                                    <p class="mt-0.5 text-[9px] font-medium text-blue-100/55">Created</p>
                                                </div>
                                                <div
                                                    class="rounded-xl border border-white/10 bg-white/5 px-2.5 py-2 text-center">
                                                    <p class="text-sm font-bold text-white">{{ $vcard['first_scan_at'] ? $this->timeAgo($vcard['first_scan_at']) : '--' }}</p>
                                                    <p class="mt-0.5 text-[9px] font-medium text-blue-100/55">First Scan</p>
                                                </div>
                                            </div>

                                            {{-- Actions --}}
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('client.tools.vcard-generator', ['editVcard' => $vcard['id']]) }}"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-linear-to-r from-blue-500 to-sky-400 px-3.5 py-2 text-xs font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:opacity-90">
                                                    <span class="material-symbols-outlined text-sm">edit</span>
                                                    Edit
                                                </a>

                                                <button type="button" @click="confirmDeleteId = {{ $vcard['id'] }}; confirmDeleteName = '{{ $vcard['name'] }}'"
                                                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-400/20 bg-red-400/10 px-3.5 py-2 text-xs font-semibold text-red-200 transition hover:bg-red-400/20">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-white/10 bg-white/8 p-8 text-center shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-blue-100/45">
                                <span class="material-symbols-outlined text-2xl">qr_code_2</span>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-white">No vCards Yet</h3>
                            <p class="mt-1 text-xs text-blue-100/55">Create your first digital business card to get started.</p>
                            <a href="{{ route('client.tools.vcard-generator') }}"
                                class="mt-4 inline-flex items-center gap-1.5 rounded-2xl bg-linear-to-r from-blue-500 to-sky-400 px-5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:opacity-90">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Create vCard
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Popup --}}
    <div x-show="confirmDeleteId"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 backdrop-blur-sm"
        style="display: none;">
        <div class="absolute inset-0" @click="confirmDeleteId = null"></div>
        <div x-show="confirmDeleteId" x-transition.scale.origin.center
            class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
            <div class="text-center">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-red-400/20 bg-red-400/10 text-red-300">
                    <span class="material-symbols-outlined text-2xl">delete</span>
                </div>
                <h3 class="mt-4 text-lg font-bold text-white">Delete vCard</h3>
                <p class="mt-2 text-sm text-blue-100/60">
                    Are you sure you want to delete <strong class="text-white" x-text="confirmDeleteName"></strong>? This cannot be undone.
                </p>
            </div>
            <div class="mt-6 flex items-center gap-3">
                <button type="button" @click="confirmDeleteId = null"
                    class="flex-1 rounded-xl border border-white/10 bg-white/8 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/12">
                    Cancel
                </button>
                <button type="button"
                    @click="$wire.deleteVcard(confirmDeleteId); confirmDeleteId = null"
                    class="flex-1 rounded-xl bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
