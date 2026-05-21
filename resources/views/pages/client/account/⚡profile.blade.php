<?php

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My Profile')] class extends Component {
    use WithFileUploads;

    public $avatarFile;
    public $logoFile;

    public string $name = '';
    public string $email = '';
    public ?string $phone = '';
    public ?string $designation = '';
    public ?string $type = '';
    public ?int $company_id = null;
    public bool $is_active = true;
    public ?string $avatar = null;

    public ?string $company_name = '';
    public ?string $company_phone = '';
    public ?string $company_address = '';
    public ?string $company_website = '';
    public ?string $company_logo = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
        $this->designation = $user->designation ?? '';
        $this->type = $user->type ?? 'personal';
        $this->company_id = $user->company_id;
        $this->is_active = (bool) ($user->is_active ?? true);
        $this->avatar = $user->avatar;

        $company = $user->company ?? Company::find($user->company_id);

        if ($company) {
            $this->company_name = $company->company_name ?? '';
            $this->company_phone = $company->phone ?? '';
            $this->company_address = $company->address ?? '';
            $this->company_website = $company->website ?? '';
            $this->company_logo = $company->logo ?? null;
        }
    }

    public function updatePersonalProfile(): void
    {
        $user = Auth::user();

        $this->validate(
            [
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'regex:/^(?:\+8801|8801|01)[3-9]\d{8}$/'],
                'designation' => ['nullable', 'string', 'max:120'],
                'avatarFile' => ['nullable', 'image', 'max:2048'],
            ],
            [
                'phone.regex' => 'Please enter a valid Bangladeshi phone number.',
            ],
        );

        if ($this->avatarFile) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $this->avatar = $this->avatarFile->store('users/avatars', 'public');
        }

        $user->name = $this->name;
        $user->phone = $this->phone;
        $user->designation = $this->designation ?: null;
        $user->avatar = $this->avatar;
        $user->save();

        $this->avatarFile = null;

        session()->flash('personal_success', 'Personal profile updated successfully.');
    }

    public function updateBusinessProfile(): void
    {
        $user = Auth::user();

        if (($user->type ?? 'personal') === 'personal') {
            session()->flash('business_error', 'Personal account cannot add or update business profile.');
            return;
        }

        $this->validate(
            [
                'company_name' => ['required', 'string', 'max:160'],
                'company_phone' => ['required', 'string', 'regex:/^(?:\+8801|8801|01)[3-9]\d{8}$/'],
                'company_address' => ['required', 'string', 'max:255'],
                'company_website' => ['nullable', 'url', 'max:160'],
                'logoFile' => ['nullable', 'image', 'max:2048'],
            ],
            [
                'company_phone.regex' => 'Please enter a valid Bangladeshi phone number.',
                'company_website.url' => 'Please enter a valid website URL. Example: https://example.com',
            ],
        );

        $company = $user->company ?? (Company::find($user->company_id) ?? new Company());

        if ($this->logoFile) {
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $this->company_logo = $this->logoFile->store('companies/logos', 'public');
        }

        $company->company_name = $this->company_name;
        $company->phone = $this->company_phone;
        $company->address = $this->company_address;
        $company->website = $this->company_website ?: null;
        $company->logo = $this->company_logo;
        $company->save();

        $user->company_id = $company->id;
        $user->save();

        $this->company_id = $company->id;
        $this->logoFile = null;

        session()->flash('business_success', 'Business profile updated successfully.');
    }
};
?>

<div x-data="{ sidebarOpen: false }" class="relative min-h-screen text-white">
    <div class="mx-auto max-w-350 px-4 py-6 sm:px-6 lg:px-8">
        <div
            class="rounded-[34px] border border-white/10 bg-white/6 shadow-[0_20px_80px_rgba(0,0,0,0.22)] backdrop-blur-2xl">
            <div class="flex min-h-[calc(100vh-3rem)]">

                {{-- Mobile Overlay --}}
                <div x-show="sidebarOpen" x-transition.opacity
                    class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"
                    style="display:none;">
                </div>

                {{-- Sidebar --}}
                <livewire:shared.user-sidebar />

                {{-- Main --}}
                <div class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">

                    {{-- Header --}}
                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <button @click="sidebarOpen = true"
                                class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-white shadow-[0_10px_30px_rgba(0,0,0,0.18)] backdrop-blur-xl transition hover:bg-white/12 lg:hidden">
                                <span class="material-symbols-outlined">menu</span>
                            </button>

                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Account Settings</p>
                                <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">My Profile</h1>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 backdrop-blur-xl">
                            <span class="material-symbols-outlined text-emerald-300">verified_user</span>
                            <div>
                                <p class="text-xs text-blue-100/45">Account Status</p>
                                <p
                                    class="text-sm font-semibold {{ $is_active ? 'text-emerald-300' : 'text-rose-300' }}">
                                    {{ $is_active ? 'Active' : 'Inactive' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">

                        {{-- Left Content --}}
                        <div class="space-y-6">

                            {{-- Personal Profile Form --}}
                            <form wire:submit.prevent="updatePersonalProfile"
                                class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                <div class="mb-6 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">
                                            Personal Profile
                                        </p>
                                        <h2 class="mt-2 text-2xl font-bold text-white">Basic information</h2>
                                        <p class="mt-2 text-sm text-blue-100/55">
                                            Update your name, phone number, designation, and profile photo.
                                        </p>
                                    </div>
                                </div>

                                @if (session('personal_success'))
                                    <div
                                        class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm font-medium text-emerald-200">
                                        {{ session('personal_success') }}
                                    </div>
                                @endif

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-blue-100/70">Full Name</label>
                                        <input type="text" wire:model="name"
                                            class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                            placeholder="Enter your name">
                                        @error('name')
                                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                            Email Address
                                        </label>
                                        <div
                                            class="flex h-12 w-full items-center rounded-2xl border border-white/10 bg-white/5 px-4 text-sm text-blue-100/60">
                                            <span class="truncate">{{ $email }}</span>
                                        </div>
                                        {{-- <p class="mt-2 text-xs text-blue-100/35">
                                            This email is used for login and company communication.
                                        </p> --}}
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                            Phone Number
                                        </label>
                                        <input type="text" wire:model="phone"
                                            class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                            placeholder="Enter phone number">
                                        @error('phone')
                                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                            Designation
                                        </label>
                                        <input type="text" wire:model="designation"
                                            class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                            placeholder="Example: CEO, Manager, IT Officer">
                                        @error('designation')
                                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- <div>
                                        <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                            Account Type
                                        </label>
                                        <input type="text" value="{{ ucfirst($type) }}" disabled
                                            class="h-12 w-full cursor-not-allowed rounded-2xl border border-white/10 bg-white/5 px-4 text-sm text-blue-100/55 outline-none">
                                    </div> --}}
                                </div>

                                <div class="mt-6 flex justify-end">
                                    <button type="submit" wire:loading.attr="disabled"
                                        wire:target="updatePersonalProfile"
                                        class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-500/25 transition hover:-translate-y-0.5 hover:shadow-cyan-500/35 disabled:cursor-not-allowed disabled:opacity-60">

                                        <span wire:loading.remove wire:target="updatePersonalProfile"
                                            class="inline-flex items-center">
                                            <span class="material-symbols-outlined mr-2 text-lg">save</span>
                                            Save Personal Profile
                                        </span>

                                        <span wire:loading wire:target="updatePersonalProfile"
                                            class="inline-flex items-center">
                                            <span
                                                class="material-symbols-outlined mr-2 animate-spin text-lg">progress_activity</span>
                                            Saving...
                                        </span>
                                    </button>
                                </div>
                            </form>

                            {{-- Business Profile Form --}}
                            @if ($type !== 'personal')
                                <form wire:submit.prevent="updateBusinessProfile"
                                    class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                    <div class="mb-6">
                                        <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">
                                            Business Profile
                                        </p>
                                        <h2 class="mt-2 text-2xl font-bold text-white">Company information</h2>
                                        <p class="mt-2 text-sm text-blue-100/55">
                                            Update your company profile for invoices, proposals, service bookings, and
                                            support records.
                                        </p>
                                    </div>

                                    @if (session('business_success'))
                                        <div
                                            class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm font-medium text-emerald-200">
                                            {{ session('business_success') }}
                                        </div>
                                    @endif

                                    @if (session('business_error'))
                                        <div
                                            class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-200">
                                            {{ session('business_error') }}
                                        </div>
                                    @endif

                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                                Company Name
                                            </label>
                                            <input type="text" wire:model="company_name"
                                                class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                                placeholder="Enter company name">
                                            @error('company_name')
                                                <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                                Company Phone
                                            </label>
                                            <input type="text" wire:model="company_phone"
                                                class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                                placeholder="Enter company phone">
                                            @error('company_phone')
                                                <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                                Website
                                            </label>
                                            <input type="url" wire:model="company_website"
                                                class="h-12 w-full rounded-2xl border border-white/10 bg-white/8 px-4 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                                placeholder="https://example.com">
                                            @error('company_website')
                                                <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="mb-2 block text-sm font-medium text-blue-100/70">
                                                Company Address
                                            </label>
                                            <textarea wire:model="company_address" rows="4"
                                                class="w-full rounded-2xl border border-white/10 bg-white/8 px-4 py-3 text-sm text-white placeholder:text-blue-100/35 outline-none backdrop-blur-xl focus:border-cyan-300/40"
                                                placeholder="Enter company address"></textarea>
                                            @error('company_address')
                                                <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <button type="submit" wire:loading.attr="disabled"
                                            wire:target="updateBusinessProfile"
                                            class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-emerald-400 to-green-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 hover:shadow-emerald-500/35 disabled:cursor-not-allowed disabled:opacity-60">

                                            <span wire:loading.remove wire:target="updateBusinessProfile"
                                                class="inline-flex items-center">
                                                <span class="material-symbols-outlined mr-2 text-lg">business</span>
                                                Save Business Profile
                                            </span>

                                            <span wire:loading wire:target="updateBusinessProfile"
                                                class="inline-flex items-center">
                                                <span
                                                    class="material-symbols-outlined mr-2 animate-spin text-lg">progress_activity</span>
                                                Saving...
                                            </span>
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div
                                    class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-amber-300/20 bg-amber-400/10 text-amber-200">
                                            <span class="material-symbols-outlined">info</span>
                                        </div>

                                        <div>
                                            <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">
                                                Business Profile
                                            </p>
                                            <h2 class="mt-2 text-2xl font-bold text-white">
                                                Not available for personal account
                                            </h2>
                                            <p class="mt-3 text-sm leading-7 text-blue-100/60">
                                                Your current account type is personal. Business profile information is
                                                only available for company or business accounts.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        {{-- Right Sidebar --}}
                        <div class="space-y-6">

                            {{-- Avatar Upload --}}
                            <div
                                class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Profile Photo</p>
                                <h2 class="mt-2 text-2xl font-bold text-white">Avatar</h2>

                                <div class="mt-6 flex flex-col items-center text-center">
                                    <div
                                        class="relative h-32 w-32 overflow-hidden rounded-[32px] border border-white/10 bg-white/10 shadow-[0_15px_45px_rgba(0,0,0,0.25)]">
                                        @if ($avatarFile)
                                            <img src="{{ $avatarFile->temporaryUrl() }}"
                                                class="h-full w-full object-cover">
                                        @elseif ($avatar)
                                            <img src="{{ asset('storage/' . $avatar) }}"
                                                class="h-full w-full object-cover">
                                        @else
                                            <div
                                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-cyan-400/20 to-blue-500/20">
                                                <span
                                                    class="material-symbols-outlined text-6xl text-cyan-200">person</span>
                                            </div>
                                        @endif
                                    </div>

                                    <label
                                        class="mt-5 inline-flex cursor-pointer items-center justify-center rounded-full border border-white/10 bg-white/8 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/12">
                                        <span class="material-symbols-outlined mr-2 text-lg">upload</span>
                                        Choose Avatar
                                        <input type="file" wire:model="avatarFile" class="hidden"
                                            accept="image/*">
                                    </label>

                                    @error('avatarFile')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror

                                    <div wire:loading wire:target="avatarFile" class="mt-3 text-xs text-cyan-200">
                                        Uploading avatar...
                                    </div>

                                    <p class="mt-4 text-xs leading-6 text-blue-100/45">
                                        After choosing an avatar, click Save Personal Profile.
                                    </p>
                                </div>
                            </div>

                            {{-- Company Logo Upload --}}
                            @if ($type !== 'personal')
                                <div
                                    class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Company Logo</p>
                                    <h2 class="mt-2 text-2xl font-bold text-white">Branding</h2>

                                    <div class="mt-6 flex flex-col items-center text-center">
                                        <div
                                            class="relative h-28 w-28 overflow-hidden rounded-[28px] border border-white/10 bg-white/10 shadow-[0_15px_45px_rgba(0,0,0,0.25)]">
                                            @if ($logoFile)
                                                <img src="{{ $logoFile->temporaryUrl() }}"
                                                    class="h-full w-full object-cover">
                                            @elseif ($company_logo)
                                                <img src="{{ asset('storage/' . $company_logo) }}"
                                                    class="h-full w-full object-cover">
                                            @else
                                                <div
                                                    class="flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-400/20 to-cyan-500/20">
                                                    <span
                                                        class="material-symbols-outlined text-5xl text-cyan-200">apartment</span>
                                                </div>
                                            @endif
                                        </div>

                                        <label
                                            class="mt-5 inline-flex cursor-pointer items-center justify-center rounded-full border border-white/10 bg-white/8 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/12">
                                            <span
                                                class="material-symbols-outlined mr-2 text-lg">add_photo_alternate</span>
                                            Choose Logo
                                            <input type="file" wire:model="logoFile" class="hidden"
                                                accept="image/*">
                                        </label>

                                        @error('logoFile')
                                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                        @enderror

                                        <div wire:loading wire:target="logoFile" class="mt-3 text-xs text-cyan-200">
                                            Uploading logo...
                                        </div>

                                        <p class="mt-4 text-xs leading-6 text-blue-100/45">
                                            After choosing a logo, click Save Business Profile.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            {{-- Account Summary --}}
                            <div
                                class="rounded-[28px] border border-white/10 bg-white/8 p-6 shadow-[0_16px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100/45">Summary</p>
                                <h2 class="mt-2 text-2xl font-bold text-white">Profile overview</h2>

                                <div class="mt-6 space-y-4 text-sm">
                                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
                                        <span class="text-blue-100/55">Account Type</span>
                                        <span
                                            class="font-semibold text-white">{{ ucfirst($type ?: 'Personal') }}</span>
                                    </div>

                                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
                                        <span class="text-blue-100/55">Designation</span>
                                        <span class="max-w-[170px] truncate font-semibold text-white">
                                            {{ $designation ?: 'Not added' }}
                                        </span>
                                    </div>

                                    @if ($type !== 'personal')
                                        <div
                                            class="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
                                            <span class="text-blue-100/55">Company</span>
                                            <span class="max-w-[170px] truncate font-semibold text-white">
                                                {{ $company_name ?: 'Not added' }}
                                            </span>
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-blue-100/55">Status</span>
                                        <span
                                            class="font-semibold {{ $is_active ? 'text-emerald-300' : 'text-rose-300' }}">
                                            {{ $is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>