<?php

use App\Models\HomePageSetting;
use App\Models\Service;
use App\Support\FeaturedServiceGrid;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Home Page Settings')] class extends Component {
    use WithFileUploads;

    public HomePageSetting $setting;

    public string $activeTab = 'hero';

    public array $hero = [];
    public array $services = [];
    public array $about = [];
    public array $heroUploads = [];

    public array $tabs = [
        'hero' => ['label' => 'Hero Section', 'icon' => 'view_carousel'],
        'services' => ['label' => 'Featured Services', 'icon' => 'grid_view'],
        'about' => ['label' => 'Get To Know Us', 'icon' => 'business'],
    ];

    public array $serviceLayoutOptions = FeaturedServiceGrid::STYLES;

    public function mount(): void
    {
        $this->setting = HomePageSetting::current();
        $resolved = HomePageSetting::resolved();

        $this->hero = $resolved['hero'];
        $this->services = $resolved['services'];
        $this->about = $resolved['about'];

        $this->services['layout_style'] = FeaturedServiceGrid::normalizeStyle($this->services['layout_style'] ?? 'original_bento');

        $this->services['grid_count'] = FeaturedServiceGrid::countForStyle($this->services['layout_style']);
    }

    protected function rules(): array
    {
        return [
            'hero.enabled' => ['required', 'boolean'],
            'hero.title' => ['required', 'string', 'max:180'],
            'hero.highlighted_title' => ['nullable', 'string', 'max:180'],
            'hero.description' => ['required', 'string', 'max:1200'],
            'hero.primary_button_text' => ['nullable', 'string', 'max:60'],
            'hero.primary_button_url' => ['nullable', 'string', 'max:500'],
            'hero.secondary_button_text' => ['nullable', 'string', 'max:60'],
            'hero.secondary_button_url' => ['nullable', 'string', 'max:500'],
            'hero.top_left_title' => ['nullable', 'string', 'max:100'],
            'hero.top_left_description' => ['nullable', 'string', 'max:250'],
            'hero.top_right_title' => ['nullable', 'string', 'max:100'],
            'hero.top_right_description' => ['nullable', 'string', 'max:250'],
            'hero.trusted_title' => ['nullable', 'string', 'max:150'],
            'hero.show_trusted_logos' => ['required', 'boolean'],
            'heroUploads.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'services.enabled' => ['required', 'boolean'],
            'services.badge' => ['nullable', 'string', 'max:100'],
            'services.title' => ['required', 'string', 'max:180'],
            'services.highlighted_title' => ['nullable', 'string', 'max:180'],
            'services.description' => ['required', 'string', 'max:1000'],
            'services.grid_count' => ['required', 'integer', 'in:3,4,5,6,7'],
            'services.layout_style' => ['required', 'string', Rule::in(array_keys(FeaturedServiceGrid::STYLES))],
            'services.button_text' => ['nullable', 'string', 'max:60'],
            'services.button_url' => ['nullable', 'string', 'max:500'],

            'about.enabled' => ['required', 'boolean'],
            'about.badge' => ['nullable', 'string', 'max:100'],
            'about.title' => ['required', 'string', 'max:220'],
            'about.highlighted_title' => ['nullable', 'string', 'max:220'],
            'about.description' => ['required', 'string', 'max:4000'],
            'about.stats' => ['required', 'array', 'size:3'],
            'about.stats.*.value' => ['required', 'string', 'max:30'],
            'about.stats.*.label' => ['required', 'string', 'max:100'],
        ];
    }

    public function setTab(string $tab): void
    {
        if (!array_key_exists($tab, $this->tabs)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function updatedServicesLayoutStyle(string $style): void
    {
        $this->services['layout_style'] = FeaturedServiceGrid::normalizeStyle($style);
        $this->services['grid_count'] = FeaturedServiceGrid::countForStyle($style);

        unset($this->featuredServices);
    }

    #[Computed]
    public function featuredServices()
    {
        $style = FeaturedServiceGrid::normalizeStyle($this->services['layout_style'] ?? 'original_bento');

        return Service::query()->with('category')->where('is_active', true)->where('is_featured', true)->latest()->limit(FeaturedServiceGrid::countForStyle($style))->get();
    }

    public function previewImage(string $field, string $fallback): string
    {
        if (isset($this->heroUploads[$field]) && $this->heroUploads[$field]) {
            return $this->heroUploads[$field]->temporaryUrl();
        }

        $image = $this->hero[$field] ?? null;

        if (blank($image)) {
            return $fallback;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        return Storage::url($image);
    }

    public function save(): void
    {
        $this->services['layout_style'] = FeaturedServiceGrid::normalizeStyle($this->services['layout_style'] ?? 'original_bento');

        $this->services['grid_count'] = FeaturedServiceGrid::countForStyle($this->services['layout_style']);

        $this->validate();

        $imageFields = ['main_image', 'top_left_image', 'top_right_image', 'bottom_left_image', 'bottom_right_image'];

        foreach ($imageFields as $field) {
            if (!isset($this->heroUploads[$field]) || !$this->heroUploads[$field]) {
                continue;
            }

            $oldImage = $this->hero[$field] ?? null;

            if (filled($oldImage) && !Str::startsWith($oldImage, ['http://', 'https://']) && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }

            $this->hero[$field] = app(\App\Services\ImageService::class)->optimizeAndStore($this->heroUploads[$field], 'homepage/hero', maxWidth: $field === 'main_image' ? 1600 : 900, quality: 88);
        }

        $this->setting->update([
            'hero' => $this->hero,
            'services' => $this->services,
            'about' => $this->about,
        ]);

        $this->heroUploads = [];
        $this->setting = $this->setting->fresh();

        $this->dispatch('toast', message: 'Home page settings updated successfully.', type: 'success');
    }
};
?>

<div>
    <div class="mx-auto w-full max-w-7xl space-y-8">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Home Page Settings</h1>
            <p class="mt-1 text-body-md text-secondary">
                Edit homepage sections and preview your changes before publishing.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex flex-wrap gap-2">
                @foreach ($tabs as $key => $tab)
                    <button type="button" wire:click="setTab('{{ $key }}')" @class([
                        'inline-flex cursor-pointer items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                        'bg-primary text-white shadow-sm' => $activeTab === $key,
                        'text-slate-600 hover:bg-slate-50 hover:text-primary' =>
                            $activeTab !== $key,
                    ])>
                        <span class="material-symbols-outlined text-[20px]">{{ $tab['icon'] }}</span>
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <form wire:submit.prevent="save">
            <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
                <div class="space-y-6 xl:col-span-7">
                    @if ($activeTab === 'hero')
                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <div class="mb-8 flex items-start justify-between gap-5">
                                <div>
                                    <h3 class="flex items-center gap-2 text-h3 font-h2">
                                        <span class="material-symbols-outlined text-primary">view_carousel</span>
                                        Hero Section
                                    </h3>
                                    <p class="mt-2 text-body-sm text-secondary">
                                        Manage the heading, description, buttons and floating content.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="hero.enabled" class="peer sr-only">
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Main Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="hero.title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                    @error('hero.title')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Highlighted Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="hero.highlighted_title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <label class="block font-label-md text-on-surface">Description</label>
                                    <textarea wire:model.live.debounce.300ms="hero.description" rows="4"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5"></textarea>
                                    @error('hero.description')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">smart_button</span>
                                Hero Buttons
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                @foreach ([
        'primary_button_text' => 'Primary Button Text',
        'primary_button_url' => 'Primary Button URL',
        'secondary_button_text' => 'Secondary Button Text',
        'secondary_button_url' => 'Secondary Button URL',
    ] as $field => $label)
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">{{ $label }}</label>
                                        <input type="text" wire:model.live.debounce.300ms="hero.{{ $field }}"
                                            class="w-full rounded border border-outline-variant px-4 py-2.5">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">dashboard</span>
                                Floating Cards
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                @foreach ([
        'top_left_title' => 'Top Left Title',
        'top_left_description' => 'Top Left Description',
        'top_right_title' => 'Top Right Title',
        'top_right_description' => 'Top Right Description',
    ] as $field => $label)
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">{{ $label }}</label>
                                        <input type="text" wire:model.live.debounce.300ms="hero.{{ $field }}"
                                            class="w-full rounded border border-outline-variant px-4 py-2.5">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @php
                            $heroImageFields = [
                                'main_image' => 'Main Image',
                                'top_left_image' => 'Top Left Image',
                                'top_right_image' => 'Top Right Image',
                                'bottom_left_image' => 'Bottom Left Image',
                                'bottom_right_image' => 'Bottom Right Image',
                            ];

                            $heroFallbacks = [
                                'main_image' =>
                                    'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80',
                                'top_left_image' =>
                                    'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80',
                                'top_right_image' =>
                                    'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=80',
                                'bottom_left_image' =>
                                    'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80',
                                'bottom_right_image' =>
                                    'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80',
                            ];
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">imagesmode</span>
                                Hero Images
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                @foreach ($heroImageFields as $field => $label)
                                    <div wire:key="hero-image-{{ $field }}"
                                        class="{{ $field === 'main_image' ? 'md:col-span-2' : '' }}">
                                        <h4 class="mb-3 font-label-md text-on-surface">{{ $label }}</h4>
                                        <label for="hero-{{ $field }}"
                                            class="flex h-48 cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface">
                                            <img src="{{ $this->previewImage($field, $heroFallbacks[$field]) }}"
                                                alt="{{ $label }}" class="h-full w-full object-cover">
                                        </label>
                                        <input id="hero-{{ $field }}" type="file"
                                            wire:model="heroUploads.{{ $field }}"
                                            accept="image/jpeg,image/png,image/webp" class="hidden">
                                        <div wire:loading wire:target="heroUploads.{{ $field }}"
                                            class="mt-2 text-sm text-primary">Uploading image...</div>
                                        @error("heroUploads.{$field}")
                                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">handshake</span>
                                Trusted Companies
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Section Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="hero.trusted_title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>

                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="flex items-center justify-between gap-5">
                                        <div>
                                            <h4 class="text-label-md font-label-md text-on-surface">Show Trusted Logos
                                            </h4>
                                            <p class="mt-1 text-body-sm text-secondary">Display company logos below the
                                                hero.</p>
                                        </div>
                                        <label class="relative inline-flex cursor-pointer items-center">
                                            <input type="checkbox" wire:model.live="hero.show_trusted_logos"
                                                class="peer sr-only">
                                            <div
                                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($activeTab === 'services')
                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <div class="mb-8 flex items-start justify-between gap-5">
                                <div>
                                    <h3 class="flex items-center gap-2 text-h3 font-h2">
                                        <span class="material-symbols-outlined text-primary">grid_view</span>
                                        Featured Services
                                    </h3>
                                    <p class="mt-2 text-body-sm text-secondary">Edit the section content and visibility.
                                    </p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="services.enabled" class="peer sr-only">
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Badge Text</label>
                                    <input type="text" wire:model.live.debounce.300ms="services.badge"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Main Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="services.title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="block font-label-md text-on-surface">Highlighted Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="services.highlighted_title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="block font-label-md text-on-surface">Description</label>
                                    <textarea wire:model.live.debounce.300ms="services.description" rows="4"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">dashboard_customize</span>
                                Featured Service Layout
                            </h3>
                            <p class="mt-2 text-body-sm text-secondary">
                                Every layout uses the exact service count and card arrangement shown in its preview.
                            </p>

                            <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ($serviceLayoutOptions as $styleKey => $option)
                                    @php
                                        $selected = ($services['layout_style'] ?? 'original_bento') === $styleKey;
                                        $layoutCount = FeaturedServiceGrid::countForStyle($styleKey);
                                    @endphp

                                    <label wire:key="layout-{{ $styleKey }}"
                                        class="cursor-pointer rounded-xl border p-4 transition {{ $selected ? 'border-primary bg-blue-50 ring-2 ring-primary/10' : 'border-slate-200 hover:border-primary/40' }}">
                                        <input type="radio" wire:model.live="services.layout_style"
                                            value="{{ $styleKey }}" class="sr-only">

                                        <div class="rounded-lg bg-slate-950 p-3">
                                            <div
                                                class="{{ FeaturedServiceGrid::gridClass($styleKey, $layoutCount, true) }}">
                                                @for ($index = 0; $index < $layoutCount; $index++)
                                                    <span
                                                        class="{{ FeaturedServiceGrid::cardClass($styleKey, $index, $layoutCount, true) }} rounded-md border border-white/10 {{ $index === 0 ? 'bg-linear-to-br from-violet-500 to-blue-500' : 'bg-linear-to-br from-slate-700 to-slate-800' }}"></span>
                                                @endfor
                                            </div>
                                        </div>

                                        <div class="mt-4 flex items-start justify-between gap-3">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="font-label-md text-on-surface">
                                                        {{ $option['label'] }}
                                                    </h4>

                                                    <span
                                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                                        {{ $layoutCount }} services
                                                    </span>
                                                </div>

                                                <p class="mt-2 text-xs leading-5 text-secondary">
                                                    {{ $option['description'] }}
                                                </p>
                                            </div>

                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $selected ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500' }}">
                                                <span class="material-symbols-outlined text-[20px]">
                                                    {{ $option['icon'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-primary">info</span>
                                    <div>
                                        <p class="font-label-md text-on-surface">
                                            Selected layout displays
                                            {{ FeaturedServiceGrid::countForStyle($services['layout_style'] ?? 'original_bento') }}
                                            services
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-secondary">
                                            The service count changes automatically with the layout, so the admin
                                            preview and homepage always match.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            @error('services.layout_style')
                                <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">smart_button</span>
                                Section Button
                            </h3>
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Button Text</label>
                                    <input type="text" wire:model.live.debounce.300ms="services.button_text"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Button URL</label>
                                    <input type="text" wire:model.live.debounce.300ms="services.button_url"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($activeTab === 'about')
                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <div class="mb-8 flex items-start justify-between gap-5">
                                <div>
                                    <h3 class="flex items-center gap-2 text-h3 font-h2">
                                        <span class="material-symbols-outlined text-primary">business</span>
                                        Get To Know Us
                                    </h3>
                                    <p class="mt-2 text-body-sm text-secondary">Edit company introduction and
                                        statistics.</p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="about.enabled" class="peer sr-only">
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Badge Text</label>
                                    <input type="text" wire:model.live.debounce.300ms="about.badge"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2">
                                    <label class="block font-label-md text-on-surface">Main Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="about.title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="block font-label-md text-on-surface">Highlighted Title</label>
                                    <input type="text" wire:model.live.debounce.300ms="about.highlighted_title"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5">
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="block font-label-md text-on-surface">Description</label>
                                    <textarea wire:model.live.debounce.300ms="about.description" rows="8"
                                        class="w-full rounded border border-outline-variant px-4 py-2.5"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                                <span class="material-symbols-outlined text-primary">monitoring</span>
                                Statistics
                            </h3>
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                                @foreach ($about['stats'] as $index => $stat)
                                    <div wire:key="about-stat-{{ $index }}"
                                        class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="space-y-2">
                                            <label class="block font-label-md text-on-surface">Value</label>
                                            <input type="text"
                                                wire:model.live.debounce.300ms="about.stats.{{ $index }}.value"
                                                class="w-full rounded border border-outline-variant bg-white px-4 py-2.5">
                                        </div>
                                        <div class="mt-4 space-y-2">
                                            <label class="block font-label-md text-on-surface">Label</label>
                                            <input type="text"
                                                wire:model.live.debounce.300ms="about.stats.{{ $index }}.label"
                                                class="w-full rounded border border-outline-variant bg-white px-4 py-2.5">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="xl:sticky xl:top-6 xl:col-span-5">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-label-md text-on-surface">Live Preview</h3>
                                <p class="mt-1 text-xs text-secondary">Changes update automatically.</p>
                            </div>
                            <div class="flex gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            </div>
                        </div>

                        <div class="bg-slate-100 p-4">
                            @if ($activeTab === 'hero')
                                <div
                                    class="relative min-h-[540px] overflow-hidden rounded-xl bg-linear-to-br from-slate-950 via-blue-950 to-slate-950 p-6 text-white">
                                    @if (!($hero['enabled'] ?? true))
                                        <div
                                            class="absolute inset-0 z-50 flex items-center justify-center bg-slate-950/90">
                                            <div class="text-center"><span
                                                    class="material-symbols-outlined text-5xl text-white/40">visibility_off</span>
                                                <p class="mt-3 text-sm text-white/70">Hero section is disabled</p>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="grid grid-cols-[1.05fr_.95fr] items-center gap-4">
                                        <div>
                                            <h2 class="text-2xl font-extrabold leading-tight">
                                                {{ $hero['title'] ?? '' }} <span
                                                    class="block text-blue-300">{{ $hero['highlighted_title'] ?? '' }}</span>
                                            </h2>
                                            <p class="mt-4 text-xs leading-6 text-blue-100/70">
                                                {{ Str::limit($hero['description'] ?? '', 180) }}</p>
                                            <div class="mt-5 flex flex-wrap gap-2">
                                                @if (filled($hero['primary_button_text'] ?? null))
                                                    <span
                                                        class="rounded-full bg-blue-500 px-4 py-2 text-[10px] font-semibold">{{ $hero['primary_button_text'] }}</span>
                                                @endif
                                                @if (filled($hero['secondary_button_text'] ?? null))
                                                    <span
                                                        class="rounded-full border border-white/15 bg-white/10 px-4 py-2 text-[10px] font-semibold">{{ $hero['secondary_button_text'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="relative mx-auto h-[300px] w-full max-w-[250px]">
                                            {{-- Main hero image --}}
                                            <div
                                                class="absolute left-1/2 top-1/2 z-10 w-[78%] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-xl border border-white/15 bg-white/10 p-1.5 shadow-[0_12px_35px_rgba(0,0,0,0.35)]">
                                                <img src="{{ $this->previewImage('main_image', 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80') }}"
                                                    class="h-40 w-full rounded-lg object-cover"
                                                    alt="Main hero preview">
                                            </div>

                                            {{-- Top-left floating card --}}
                                            <div
                                                class="absolute left-0 top-3 z-20 w-24 overflow-hidden rounded-lg border border-white/15 bg-slate-950/75 p-1 shadow-[0_10px_25px_rgba(0,0,0,0.32)] backdrop-blur-xl">
                                                <img src="{{ $this->previewImage('top_left_image', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80') }}"
                                                    class="h-14 w-full rounded object-cover"
                                                    alt="Top-left floating preview">

                                                <div class="px-0.5 pb-0.5 pt-1">
                                                    <p class="truncate text-[7px] font-semibold text-white">
                                                        {{ $hero['top_left_title'] ?? '' }}
                                                    </p>

                                                    @if (filled($hero['top_left_description'] ?? null))
                                                        <p class="mt-0.5 truncate text-[6px] text-blue-100/60">
                                                            {{ $hero['top_left_description'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Top-right floating card --}}
                                            <div
                                                class="absolute right-0 top-0 z-20 w-24 overflow-hidden rounded-lg border border-white/15 bg-slate-950/75 p-1 shadow-[0_10px_25px_rgba(0,0,0,0.32)] backdrop-blur-xl">
                                                <img src="{{ $this->previewImage('top_right_image', 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=80') }}"
                                                    class="h-14 w-full rounded object-cover"
                                                    alt="Top-right floating preview">

                                                <div class="px-0.5 pb-0.5 pt-1">
                                                    <p class="truncate text-[7px] font-semibold text-white">
                                                        {{ $hero['top_right_title'] ?? '' }}
                                                    </p>

                                                    @if (filled($hero['top_right_description'] ?? null))
                                                        <p class="mt-0.5 truncate text-[6px] text-blue-100/60">
                                                            {{ $hero['top_right_description'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Bottom-left floating card --}}
                                            <div
                                                class="absolute bottom-1 left-1 z-20 w-24 overflow-hidden rounded-lg border border-white/15 bg-slate-950/75 p-1 shadow-[0_10px_25px_rgba(0,0,0,0.32)] backdrop-blur-xl">
                                                <img src="{{ $this->previewImage('bottom_left_image', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80') }}"
                                                    class="h-14 w-full rounded object-cover"
                                                    alt="Bottom-left floating preview">

                                                @if (filled($hero['bottom_left_title'] ?? null))
                                                    <div class="px-0.5 pb-0.5 pt-1">
                                                        <p class="truncate text-[7px] font-semibold text-white">
                                                            {{ $hero['bottom_left_title'] }}
                                                        </p>

                                                        @if (filled($hero['bottom_left_description'] ?? null))
                                                            <p class="mt-0.5 truncate text-[6px] text-blue-100/60">
                                                                {{ $hero['bottom_left_description'] }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Bottom-right floating card --}}
                                            <div
                                                class="absolute bottom-0 right-1 z-20 w-24 overflow-hidden rounded-lg border border-white/15 bg-slate-950/75 p-1 shadow-[0_10px_25px_rgba(0,0,0,0.32)] backdrop-blur-xl">
                                                <img src="{{ $this->previewImage('bottom_right_image', 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80') }}"
                                                    class="h-14 w-full rounded object-cover"
                                                    alt="Bottom-right floating preview">

                                                @if (filled($hero['bottom_right_title'] ?? null))
                                                    <div class="px-0.5 pb-0.5 pt-1">
                                                        <p class="truncate text-[7px] font-semibold text-white">
                                                            {{ $hero['bottom_right_title'] }}
                                                        </p>

                                                        @if (filled($hero['bottom_right_description'] ?? null))
                                                            <p class="mt-0.5 truncate text-[6px] text-blue-100/60">
                                                                {{ $hero['bottom_right_description'] }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Preview glows --}}
                                            <div
                                                class="pointer-events-none absolute left-1/2 top-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-500/15 blur-3xl">
                                            </div>
                                        </div>
                                    </div>
                                    @if ($hero['show_trusted_logos'] ?? true)
                                        <div class="mt-10 text-center">
                                            <p
                                                class="text-[8px] font-semibold uppercase tracking-[0.2em] text-blue-100/60">
                                                {{ $hero['trusted_title'] ?? '' }}</p>
                                            <div class="mt-4 flex justify-center gap-2">
                                                @foreach (['Company', 'Brand', 'Partner', 'Client'] as $logo)
                                                    <span
                                                        class="rounded-md border border-white/10 bg-white/8 px-3 py-2 text-[7px] text-white/55">{{ $logo }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($activeTab === 'services')
                                @php
                                    $previewStyle = FeaturedServiceGrid::normalizeStyle(
                                        $services['layout_style'] ?? 'original_bento',
                                    );
                                    $previewCount = FeaturedServiceGrid::countForStyle($previewStyle);
                                    $previewServices = $this->featuredServices;
                                    $compactPreview = FeaturedServiceGrid::isCompactStyle($previewStyle);
                                    $dashboardPreview = FeaturedServiceGrid::isDashboardStyle($previewStyle);
                                @endphp
                                <div
                                    class="relative min-h-[540px] overflow-hidden rounded-xl bg-linear-to-br from-slate-950 via-blue-950 to-slate-950 p-5 text-white {{ $previewStyle === 'stats_board' ? 'lg:min-h-[650px]' : '' }}">
                                    @if (!($services['enabled'] ?? true))
                                        <div
                                            class="absolute inset-0 z-50 flex items-center justify-center bg-slate-950/90">
                                            <div class="text-center"><span
                                                    class="material-symbols-outlined text-5xl text-white/40">visibility_off</span>
                                                <p class="mt-3 text-sm text-white/70">Services section is disabled</p>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="mb-6 text-center">
                                        <span
                                            class="inline-flex rounded-full border border-cyan-300/15 bg-cyan-300/10 px-3 py-1 text-[8px] text-cyan-100">{{ $services['badge'] ?? '' }}</span>
                                        <h2 class="mt-3 text-xl font-bold">{{ $services['title'] ?? '' }} <span
                                                class="text-cyan-300">{{ $services['highlighted_title'] ?? '' }}</span>
                                        </h2>
                                        <p class="mx-auto mt-2 max-w-sm text-[9px] leading-4 text-blue-100/60">
                                            {{ Str::limit($services['description'] ?? '', 150) }}</p>
                                    </div>
                                    <div
                                        class="{{ FeaturedServiceGrid::gridClass($previewStyle, $previewCount, true) }}">
                                        @forelse ($previewServices as $service)
                                            @php
                                                $span = FeaturedServiceGrid::cardClass(
                                                    $previewStyle,
                                                    $loop->index,
                                                    $previewCount,
                                                    true,
                                                );
                                                $large = FeaturedServiceGrid::isLarge(
                                                    $previewStyle,
                                                    $loop->index,
                                                    $previewCount,
                                                );
                                            @endphp
                                            <div
                                                class="relative overflow-hidden rounded-lg border border-white/10 {{ $span }} {{ $compactPreview ? 'bg-slate-900 shadow-[0_0_20px_rgba(34,211,238,0.10)]' : ($dashboardPreview ? 'bg-[#080b18] shadow-[0_0_24px_rgba(76,29,149,0.10)]' : '') }}">
                                                <img src="{{ $service->image ? asset('storage/' . $service->image) : 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=800&q=80' }}"
                                                    class="absolute inset-0 h-full w-full object-cover {{ $compactPreview ? 'opacity-20' : ($dashboardPreview ? 'opacity-70' : '') }}"
                                                    alt="">
                                                <div
                                                    class="absolute inset-0 {{ $compactPreview ? 'bg-linear-to-br from-cyan-500/10 via-slate-950/80 to-blue-500/10' : ($dashboardPreview ? 'bg-linear-to-t from-[#080b18] via-[#080b18]/60 to-transparent' : 'bg-linear-to-t from-slate-950 via-slate-950/55 to-transparent') }}">
                                                </div>
                                                <div class="relative z-10 flex h-full flex-col justify-between p-2">
                                                    @if ($compactPreview)
                                                        <span
                                                            class="material-symbols-outlined text-[14px] text-cyan-300">{{ $service->icon ?: 'settings' }}</span>
                                                    @endif
                                                    <p
                                                        class="truncate font-semibold text-white {{ $large ? 'text-[10px]' : 'text-[8px]' }}">
                                                        {{ $service->card_title }}</p>
                                                </div>
                                            </div>
                                        @empty
                                            <div
                                                class="col-span-full flex h-44 items-center justify-center rounded-lg border border-dashed border-white/15 bg-white/5">
                                                <p class="text-xs text-white/50">No featured services found</p>
                                            </div>
                                        @endforelse
                                    </div>
                                    @if (filled($services['button_text'] ?? null))
                                        <div class="mt-6 text-center"><span
                                                class="inline-flex rounded-full bg-blue-500 px-4 py-2 text-[9px] font-semibold">{{ $services['button_text'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($activeTab === 'about')
                                <div
                                    class="relative min-h-[540px] overflow-hidden rounded-xl bg-linear-to-br from-slate-950 via-blue-950 to-slate-950 p-7 text-white">
                                    @if (!($about['enabled'] ?? true))
                                        <div
                                            class="absolute inset-0 z-50 flex items-center justify-center bg-slate-950/90">
                                            <div class="text-center"><span
                                                    class="material-symbols-outlined text-5xl text-white/40">visibility_off</span>
                                                <p class="mt-3 text-sm text-white/70">Get To Know Us is disabled</p>
                                            </div>
                                        </div>
                                    @endif
                                    <span
                                        class="inline-flex rounded-full border border-cyan-300/15 bg-cyan-300/10 px-3 py-1 text-[8px] text-cyan-100">{{ $about['badge'] ?? '' }}</span>
                                    <h2 class="mt-4 text-2xl font-bold leading-tight">{{ $about['title'] ?? '' }}
                                        <span
                                            class="block text-cyan-300">{{ $about['highlighted_title'] ?? '' }}</span>
                                    </h2>
                                    <p class="mt-4 whitespace-pre-line text-[10px] leading-5 text-blue-100/65">
                                        {{ Str::limit($about['description'] ?? '', 500) }}</p>
                                    <div class="mt-7 grid grid-cols-3 gap-2">
                                        @foreach ($about['stats'] ?? [] as $stat)
                                            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-3">
                                                <p class="text-lg font-bold">{{ $stat['value'] ?? '' }}</p>
                                                <p class="mt-1 text-[7px] leading-3 text-blue-100/50">
                                                    {{ $stat['label'] ?? '' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div
                                        class="relative mt-8 flex min-h-48 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-white/5">
                                        <div
                                            class="flex h-20 w-20 items-center justify-center rounded-2xl bg-linear-to-br from-blue-600 to-sky-400">
                                            <span class="material-symbols-outlined text-4xl">hub</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="font-label-md text-on-surface">Save Homepage Changes</p>
                        <p class="mt-1 text-xs text-secondary">Review the preview before saving.</p>
                    </div>
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><span
                                class="material-symbols-outlined text-[19px]">save</span>Save Settings</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>Saving...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
