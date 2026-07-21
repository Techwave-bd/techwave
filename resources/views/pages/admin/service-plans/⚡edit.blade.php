<?php

use App\Models\PlanAddon;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServicePlan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin-app')] #[Title('Edit Service Plan')] class extends Component {
    public ServicePlan $servicePlan;

    public ?int $service_id = null;
    public ?int $service_option_id = null;

    public string $name = '';
    public string $slug = '';
    public string $badge = '';
    public string $description = '';

    public bool $has_one_time_price = false;
    public string $price = '';
    public string $discount_price = '';

    public bool $has_monthly_price = false;
    public string $monthly_price = '';
    public string $monthly_discount_price = '';
    public string $monthly_buy_url = '';

    public bool $has_yearly_price = false;
    public string $yearly_price = '';
    public string $yearly_discount_price = '';
    public string $yearly_buy_url = '';

    public string $buy_url = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public array $features = [];
    public string $feature = '';

    public array $selectedAddons = [];

    public function mount(ServicePlan $servicePlan): void
    {
        $this->servicePlan = $servicePlan;

        $this->fillFromModel();
    }

    private function fillFromModel(): void
    {
        $this->service_id = $this->servicePlan->service_id;
        $this->service_option_id = $this->servicePlan->service_option_id;

        $this->name = $this->servicePlan->name;
        $this->slug = $this->servicePlan->slug;
        $this->badge = $this->servicePlan->badge ?? '';
        $this->description = $this->servicePlan->description ?? '';

        $this->has_one_time_price = (bool) $this->servicePlan->has_one_time_price;
        $this->price = $this->servicePlan->price !== null ? (string) $this->servicePlan->price : '';
        $this->discount_price = $this->servicePlan->discount_price !== null ? (string) $this->servicePlan->discount_price : '';

        $this->has_monthly_price = (bool) $this->servicePlan->has_monthly_price;
        $this->monthly_price = $this->servicePlan->monthly_price !== null ? (string) $this->servicePlan->monthly_price : '';
        $this->monthly_discount_price = $this->servicePlan->monthly_discount_price !== null ? (string) $this->servicePlan->monthly_discount_price : '';
        $this->monthly_buy_url = $this->servicePlan->monthly_buy_url ?? '';

        $this->has_yearly_price = (bool) $this->servicePlan->has_yearly_price;
        $this->yearly_price = $this->servicePlan->yearly_price !== null ? (string) $this->servicePlan->yearly_price : '';
        $this->yearly_discount_price = $this->servicePlan->yearly_discount_price !== null ? (string) $this->servicePlan->yearly_discount_price : '';
        $this->yearly_buy_url = $this->servicePlan->yearly_buy_url ?? '';

        $this->buy_url = $this->servicePlan->buy_url ?? '';

        $this->fillBuyUrlsFromModel();

        $this->sort_order = (int) $this->servicePlan->sort_order;
        $this->is_active = (bool) $this->servicePlan->is_active;

        $this->features = $this->servicePlan->features ?: [];

        $this->selectedAddons = $this->servicePlan->addons
            ->map(
                fn($addon) => [
                    'addon_id' => $addon->id,
                    'price' => $addon->pivot->price !== null ? (string) $addon->pivot->price : '',
                    'monthly_price' => $addon->pivot->monthly_price !== null ? (string) $addon->pivot->monthly_price : '',
                    'yearly_price' => $addon->pivot->yearly_price !== null ? (string) $addon->pivot->yearly_price : '',
                ],
            )
            ->toArray();
    }

    private function fillBuyUrlsFromModel(): void
    {
        $service = $this->servicePlan->service;

        if (!$service) return;

        if ($this->has_one_time_price && empty($this->buy_url)) {
            $this->buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]);
        }

        if ($this->has_monthly_price && empty($this->monthly_buy_url)) {
            $this->monthly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=monthly';
        }

        if ($this->has_yearly_price && empty($this->yearly_buy_url)) {
            $this->yearly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=yearly';
        }
    }

    public function updatedServiceId(): void
    {
        $this->service_option_id = null;

        if (!$this->servicePlan?->service) return;

        $service = Service::find($this->service_id);
        if (!$service) return;

        $this->buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]);

        if ($this->has_monthly_price) {
            $this->monthly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=monthly';
        }

        if ($this->has_yearly_price) {
            $this->yearly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=yearly';
        }
    }

    public function updatedHasOneTimePrice(): void
    {
        if (!$this->has_one_time_price) {
            $this->price = '';
            $this->discount_price = '';
            $this->buy_url = '';

            $this->resetValidation(['price', 'discount_price', 'buy_url']);
        } elseif (empty($this->buy_url)) {
            $service = $this->servicePlan->service;

            if ($service) {
                $this->buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]);
            }
        }
    }

    private function fillBuyUrlsBeforeSave(): void
    {
        $service = $this->servicePlan->service;

        if (!$service) return;

        if ($this->has_one_time_price && empty($this->buy_url)) {
            $this->buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]);
        }

        if ($this->has_monthly_price && empty($this->monthly_buy_url)) {
            $this->monthly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=monthly';
        }

        if ($this->has_yearly_price && empty($this->yearly_buy_url)) {
            $this->yearly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=yearly';
        }
    }

    protected function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_option_id' => ['nullable', 'integer', 'exists:service_options,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:190', Rule::unique('service_plans', 'slug')->ignore($this->servicePlan->id)],
            'badge' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:800'],

            'has_one_time_price' => ['boolean'],
            'price' => [$this->has_one_time_price ? 'required' : 'nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],

            'has_monthly_price' => ['boolean'],
            'monthly_price' => [$this->has_monthly_price ? 'required' : 'nullable', 'numeric', 'min:0'],
            'monthly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:monthly_price'],
            'monthly_buy_url' => ['nullable', 'url', 'max:255'],

            'has_yearly_price' => ['boolean'],
            'yearly_price' => [$this->has_yearly_price ? 'required' : 'nullable', 'numeric', 'min:0'],
            'yearly_discount_price' => ['nullable', 'numeric', 'min:0', 'lt:yearly_price'],
            'yearly_buy_url' => ['nullable', 'url', 'max:255'],

            'buy_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],

            'features' => ['required', 'array'],
            'features.*' => ['required', 'string', 'max:180'],

            'selectedAddons' => ['nullable', 'array'],
            'selectedAddons.*.addon_id' => ['required', 'integer', 'exists:plan_addons,id'],
            'selectedAddons.*.price' => ['nullable', 'numeric', 'min:0'],
            'selectedAddons.*.monthly_price' => ['nullable', 'numeric', 'min:0'],
            'selectedAddons.*.yearly_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',

            'buy_url.url' => 'Please enter a valid default or one-time buy URL.',

            'price.required' => 'One-time price is required when one-time pricing is enabled.',
            'discount_price.lt' => 'Discount price must be less than regular price.',

            'monthly_price.required' => 'Monthly price is required when monthly pricing is enabled.',
            'monthly_discount_price.lt' => 'Monthly discount price must be less than monthly price.',
            'monthly_buy_url.url' => 'Please enter a valid monthly buy URL.',

            'yearly_price.required' => 'Yearly price is required when yearly pricing is enabled.',
            'yearly_discount_price.lt' => 'Yearly discount price must be less than yearly price.',
            'yearly_buy_url.url' => 'Please enter a valid yearly buy URL.',
        ];
    }

    public function services()
    {
        return Service::query()->where('is_active', true)->orderBy('card_title')->get();
    }

    public function serviceOptions()
    {
        return ServiceOption::query()
            ->where('is_active', true)
            ->orderBy('card_title')
            ->get();
    }

    public function selectedService()
    {
        if (!$this->service_id) {
            return null;
        }

        return $this->services()->firstWhere('id', (int) $this->service_id);
    }

    public function updatedName(): void
    {
        $this->slug = Str::slug($this->name);

        $this->validateOnly('name');

        if (filled($this->slug)) {
            $this->validateOnly('slug');
        }
    }

    public function updatedHasMonthlyPrice(): void
    {
        if (!$this->has_monthly_price) {
            $this->monthly_price = '';
            $this->monthly_discount_price = '';
            $this->monthly_buy_url = '';

            $this->resetValidation(['monthly_price', 'monthly_discount_price', 'monthly_buy_url']);
        } elseif (empty($this->monthly_buy_url)) {
            $service = $this->servicePlan->service;

            if ($service) {
                $this->monthly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=monthly';
            }
        }
    }

    public function updatedHasYearlyPrice(): void
    {
        if (!$this->has_yearly_price) {
            $this->yearly_price = '';
            $this->yearly_discount_price = '';
            $this->yearly_buy_url = '';

            $this->resetValidation(['yearly_price', 'yearly_discount_price', 'yearly_buy_url']);
        } elseif (empty($this->yearly_buy_url)) {
            $service = $this->servicePlan->service;

            if ($service) {
                $this->yearly_buy_url = route('client.services.checkout', ['slug' => $service->slug, 'plan' => $this->servicePlan->slug]) . '?billing=yearly';
            }
        }
    }

    public function updated($property): void
    {
        if ($property !== 'name') {
            $this->validateOnly($property);
        }
    }

    private function uniqueSlug(string $value): string
    {
        $slug = Str::slug($value ?: $this->name);
        $originalSlug = $slug;
        $counter = 1;

        while (ServicePlan::query()->where('slug', $slug)->where('id', '!=', $this->servicePlan->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function availableAddons()
    {
        return PlanAddon::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function isAddonSelected(int $addonId): bool
    {
        return collect($this->selectedAddons)->contains('addon_id', $addonId);
    }

    public function toggleAddon(int $addonId): void
    {
        $existing = collect($this->selectedAddons);

        if ($existing->contains('addon_id', $addonId)) {
            $this->selectedAddons = $existing->reject(fn($item) => $item['addon_id'] === $addonId)->values()->toArray();
        } else {
            $addon = PlanAddon::find($addonId);

            $this->selectedAddons[] = [
                'addon_id' => $addonId,
                'price' => $addon ? (string) $addon->price : '',
                'monthly_price' => $addon ? (string) $addon->monthly_price : '',
                'yearly_price' => $addon ? (string) $addon->yearly_price : '',
            ];
        }
    }

    public function removeSelectedAddon(int $index): void
    {
        unset($this->selectedAddons[$index]);

        $this->selectedAddons = array_values($this->selectedAddons);
    }

    public function addFeature(): void
    {
        $feature = trim($this->feature);

        if ($feature === '') {
            $this->dispatch('toast', message: 'Please type a feature first.', type: 'warning');

            return;
        }

        if (!in_array($feature, $this->features, true)) {
            $this->features[] = $feature;
        }

        $this->feature = '';
    }

    public function removeFeature(int $index): void
    {
        unset($this->features[$index]);

        $this->features = array_values($this->features);
    }

    public function update(): void
    {
        $this->fillBuyUrlsBeforeSave();

        $validated = $this->validate();

        $this->servicePlan->update([
            'service_id' => $validated['service_id'],
            'service_option_id' => $validated['service_option_id'] ?? null,

            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($this->slug ?: $validated['name']),

            'badge' => $validated['badge'] ?: null,
            'description' => $validated['description'] ?: null,

            'has_one_time_price' => $validated['has_one_time_price'] ?? false,
            'price' => ($validated['has_one_time_price'] ?? false) && filled($validated['price'] ?? null) ? $validated['price'] : null,
            'discount_price' => ($validated['has_one_time_price'] ?? false) && filled($validated['discount_price'] ?? null) ? $validated['discount_price'] : null,

            'has_monthly_price' => $validated['has_monthly_price'] ?? false,
            'monthly_price' => $validated['has_monthly_price'] ?? false ? $validated['monthly_price'] : null,
            'monthly_discount_price' => ($validated['has_monthly_price'] ?? false) && filled($validated['monthly_discount_price'] ?? null) ? $validated['monthly_discount_price'] : null,
            'monthly_buy_url' => $validated['has_monthly_price'] ?? false ? ($validated['monthly_buy_url'] ?: null) : null,

            'has_yearly_price' => $validated['has_yearly_price'] ?? false,
            'yearly_price' => $validated['has_yearly_price'] ?? false ? $validated['yearly_price'] : null,
            'yearly_discount_price' => ($validated['has_yearly_price'] ?? false) && filled($validated['yearly_discount_price'] ?? null) ? $validated['yearly_discount_price'] : null,
            'yearly_buy_url' => $validated['has_yearly_price'] ?? false ? ($validated['yearly_buy_url'] ?: null) : null,

            'features' => array_values(array_filter($validated['features'] ?? [])),

            'buy_url' => $validated['has_one_time_price'] ?? false ? ($validated['buy_url'] ?: null) : null,

            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        $this->syncAddons($this->servicePlan);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Service plan updated successfully.',
        ]);

        $this->redirectRoute('admin.service-plans.index', navigate: true);
    }

    private function syncAddons(ServicePlan $plan): void
    {
        $addonData = [];

        foreach ($this->selectedAddons as $index => $item) {
            $addonData[$item['addon_id']] = [
                'price' => filled($item['price'] ?? null) ? $item['price'] : null,
                'monthly_price' => filled($item['monthly_price'] ?? null) ? $item['monthly_price'] : null,
                'yearly_price' => filled($item['yearly_price'] ?? null) ? $item['yearly_price'] : null,
                'sort_order' => $index,
            ];
        }

        $plan->addons()->sync($addonData);
    }

    public function discard(): void
    {
        $this->fillFromModel();

        $this->feature = '';

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Edit Service Plan</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Update one-time, monthly, yearly, or custom service plan pricing.
            </p>
        </div>

        <a href="{{ route('admin.service-plans.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Plans
        </a>
    </div>

    <form wire:submit.prevent="update">
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">inventory_2</span>
                        Plan Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Parent Service</label>

                            <div x-data="{
                                open: false,
                                search: '',
                                selectedId: @entangle('service_id').live,
                                services: @js(
    $this->services()
        ->map(
            fn($service) => [
                'id' => $service->id,
                'title' => $service->card_title,
            ],
        )
        ->values(),
),
                            
                                get selectedService() {
                                    return this.services.find(service => service.id == this.selectedId) || null;
                                },
                            
                                get filteredServices() {
                                    if (!this.search.trim()) {
                                        return this.services;
                                    }
                            
                                    return this.services.filter(service =>
                                        service.title.toLowerCase().includes(this.search.toLowerCase())
                                    );
                                },
                            
                                selectService(service) {
                                    this.selectedId = service.id;
                                    this.search = service.title;
                                    this.open = false;
                                },
                            
                                clearService() {
                                    this.selectedId = null;
                                    this.search = '';
                                    this.open = true;
                                },
                            
                                init() {
                                    if (this.selectedService) {
                                        this.search = this.selectedService.title;
                                    }
                            
                                    this.$watch('selectedId', () => {
                                        if (this.selectedService) {
                                            this.search = this.selectedService.title;
                                        }
                                    });
                                }
                            }" class="relative" @click.outside="open = false">
                                <div class="relative">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                        @keydown.escape.window="open = false" placeholder="Search and select service..."
                                        class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 pr-20 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                    <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
                                        <button type="button" x-show="selectedId" @click="clearService()"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-red-500">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>

                                        <button type="button" @click="open = !open"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100">
                                            <span class="material-symbols-outlined text-[22px] transition"
                                                :class="open ? 'rotate-180' : ''">
                                                expand_more
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div x-show="open" x-transition
                                    class="absolute z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                                    style="display: none;">
                                    <template x-if="filteredServices.length">
                                        <div class="space-y-1">
                                            <template x-for="service in filteredServices" :key="service.id">
                                                <button type="button" @click="selectService(service)"
                                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition hover:bg-primary/5"
                                                    :class="selectedId == service.id ? 'bg-primary/10 text-primary' :
                                                        'text-slate-700'">
                                                    <span x-text="service.title" class="font-medium"></span>

                                                    <span x-show="selectedId == service.id"
                                                        class="material-symbols-outlined text-[18px] text-primary">
                                                        check_circle
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="!filteredServices.length">
                                        <div class="rounded-lg bg-slate-50 px-4 py-6 text-center">
                                            <span class="material-symbols-outlined text-3xl text-slate-300">
                                                search_off
                                            </span>
                                            <p class="mt-2 text-sm font-medium text-slate-500">No service found</p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            @error('service_id')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        @if (count($this->serviceOptions()) > 0)
                            <div class="space-y-2 md:col-span-2">
                                <label class="block font-label-md text-on-surface">Service Option</label>

                                <div x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: @entangle('service_option_id').live,
                                    parentServiceId: @entangle('service_id').live,
                                    allOptions: @js(
                                        $this->serviceOptions()->map(fn($o) => ['id' => $o->id, 'service_id' => $o->service_id, 'title' => $o->card_title])->values()
                                    ),

                                    get options() {
                                        const none = { id: null, title: 'Choose service option', disabled: true };
                                        if (!this.parentServiceId) {
                                            return [none];
                                        }
                                        const matched = this.allOptions.filter(o => o.service_id == this.parentServiceId);
                                        return [none, ...matched];
                                    },

                                    get selectedOption() {
                                        return this.options.find(o => o.id == this.selectedId) || null;
                                    },

                                    get filteredOptions() {
                                        if (!this.search.trim()) {
                                            return this.options;
                                        }

                                        return this.options.filter(o =>
                                            o.title.toLowerCase().includes(this.search.toLowerCase())
                                        );
                                    },

                                    selectOption(option) {
                                        this.selectedId = option.id;
                                        this.search = option.title;
                                        this.open = false;
                                    },

                                    clearOption() {
                                        this.selectedId = null;
                                        this.search = '';
                                        this.open = true;
                                    },

                                    init() {
                                        if (this.selectedOption) {
                                            this.search = this.selectedOption.title;
                                        }

                                        this.$watch('parentServiceId', () => {
                                            this.selectedId = null;
                                            this.search = '';
                                        });

                                        this.$watch('selectedId', () => {
                                            if (this.selectedOption) {
                                                this.search = this.selectedOption.title;
                                            } else {
                                                this.search = '';
                                            }
                                        });
                                    }
                                }" class="relative" @click.outside="open = false">
                                    <div class="relative">
                                        <input type="text" x-model="search" @focus="open=true" @input="open=true"
                                            @keydown.escape.window="open = false" placeholder="Search and select option..."
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 pr-20 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
                                            <button type="button" x-show="selectedId !== null" @click="clearOption()"
                                                class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-red-500">
                                                <span class="material-symbols-outlined text-[18px]">close</span>
                                            </button>

                                            <button type="button" @click="open = !open"
                                                class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100">
                                                <span class="material-symbols-outlined text-[22px] transition"
                                                    :class="open ? 'rotate-180' : ''">
                                                    expand_more
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <div x-show="open" x-transition
                                        class="absolute z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                                        style="display: none;">
                                        <template x-if="filteredOptions.length">
                                            <div class="space-y-1">
                                                <template x-for="option in filteredOptions" :key="option.id ?? 'none'">
                                                    <button type="button" @click="!option.disabled && selectOption(option)"
                                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition"
                                                        :class="option.disabled ? 'cursor-not-allowed text-slate-400' :
                                                            (selectedId == option.id ? 'bg-primary/10 text-primary' : 'hover:bg-primary/5 text-slate-700')">
                                                        <span x-text="option.title" class="font-medium"></span>

                                                        <span x-show="selectedId == option.id"
                                                            class="material-symbols-outlined text-[18px] text-primary">
                                                            check_circle
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>

                                        <template x-if="!filteredOptions.length">
                                            <div class="rounded-lg bg-slate-50 px-4 py-6 text-center">
                                                <span class="material-symbols-outlined text-3xl text-slate-300">
                                                    search_off
                                                </span>
                                                <p class="mt-2 text-sm font-medium text-slate-500">No option found</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <p class="text-xs text-secondary">
                                    Optionally assign this plan to a specific service option. Leave empty to keep it directly under the service.
                                </p>

                                @error('service_option_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Plan Name</label>

                            <input type="text" wire:model.live="name" placeholder="e.g., Business Hosting"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('name')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Slug:
                                <span class="font-mono text-primary">{{ $slug ?: 'plan-slug' }}</span>
                            </p>

                            @error('slug')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Badge</label>

                            <input type="text" wire:model="badge" placeholder="Popular, Best Value, Recommended"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('badge')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-semibold text-on-surface">One-time Pricing</h4>
                                    <p class="mt-1 text-xs text-secondary">
                                        Enable this if this plan has a one-time price and one-time cart URL.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="has_one_time_price"
                                        class="peer sr-only" />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            @if ($has_one_time_price)
                                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">One-time Price</label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="price" placeholder="e.g., 20000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">
                                            One-time Discount Price
                                        </label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="discount_price" placeholder="e.g., 15000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('discount_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            Leave empty if there is no one-time discount.
                                        </p>
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label class="block font-label-md text-on-surface">
                                            One-time Buy / Cart URL
                                        </label>

                                        <input type="url" wire:model="buy_url"
                                            placeholder="https://gipsyhost.com/index.php?rp=/store/shared-hosting/student"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('buy_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            User will go to this URL when one-time billing is selected.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-semibold text-on-surface">Monthly Pricing</h4>
                                    <p class="mt-1 text-xs text-secondary">
                                        Enable this if this plan has monthly billing and a monthly cart URL.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="has_monthly_price"
                                        class="peer sr-only" />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            @if ($has_monthly_price)
                                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">Monthly Price</label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="monthly_price" placeholder="e.g., 2500"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">
                                            Monthly Discount Price
                                        </label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="monthly_discount_price" placeholder="e.g., 2000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_discount_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            Leave empty if there is no monthly discount.
                                        </p>
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label class="block font-label-md text-on-surface">
                                            Monthly Buy / Cart URL
                                        </label>

                                        <input type="url" wire:model="monthly_buy_url"
                                            placeholder="https://gipsyhost.com/index.php?rp=/store/service/monthly"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('monthly_buy_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            User will go to this URL when monthly billing is selected.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-semibold text-on-surface">Yearly Pricing</h4>
                                    <p class="mt-1 text-xs text-secondary">
                                        Enable this if this plan has yearly billing and a yearly cart URL.
                                    </p>
                                </div>

                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" wire:model.live="has_yearly_price" class="peer sr-only" />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                                    </div>
                                </label>
                            </div>

                            @if ($has_yearly_price)
                                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">Yearly Price</label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="yearly_price" placeholder="e.g., 24000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block font-label-md text-on-surface">
                                            Yearly Discount Price
                                        </label>

                                        <input type="number" step="0.01" min="0"
                                            wire:model="yearly_discount_price" placeholder="e.g., 20000"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_discount_price')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            Leave empty if there is no yearly discount.
                                        </p>
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label class="block font-label-md text-on-surface">
                                            Yearly Buy / Cart URL
                                        </label>

                                        <input type="url" wire:model="yearly_buy_url"
                                            placeholder="https://gipsyhost.com/index.php?rp=/store/service/yearly"
                                            class="w-full rounded border border-outline-variant bg-white px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                                        @error('yearly_buy_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror

                                        <p class="text-xs text-secondary">
                                            User will go to this URL when yearly billing is selected.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Description</label>

                            <textarea wire:model="description" rows="3" placeholder="Short plan description..."
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"></textarea>

                            @error('description')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 space-y-6 lg:col-span-4">

                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-6 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">checklist</span>
                        Plan Features
                    </h3>

                    <div class="mb-4 flex gap-3">
                        <input wire:model.live="feature" wire:keydown.enter.prevent="addFeature"
                            class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="e.g., 10GB SSD Storage" type="text" />

                        <button type="button" wire:click="addFeature"
                            class="flex cursor-pointer items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Add
                        </button>
                    </div>

                    <div class="flex min-h-[60px] flex-wrap gap-2 rounded-lg border border-slate-100 bg-surface p-4">
                        @forelse ($features as $index => $item)
                            <div wire:key="feature-{{ $index }}"
                                class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-3 py-1.5 shadow-sm">
                                <span class="text-sm font-body-md">{{ $item }}</span>

                                <button type="button" wire:click="removeFeature({{ $index }})"
                                    class="material-symbols-outlined cursor-pointer text-sm text-outline hover:text-error">
                                    close
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-secondary">No features added yet.</p>
                        @endforelse
                    </div>

                    @error('features')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">extension</span>
                        Plan Addons
                    </h3>

                    <p class="mb-4 text-body-sm text-secondary">
                        Attach optional addons to this plan. Override prices if needed.
                    </p>

                    <div x-data="{ open: false, search: '' }" class="relative mb-4">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                            class="flex w-full items-center gap-2 rounded-lg border border-outline bg-white px-3 py-2.5 text-left text-body-md transition hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <span class="material-symbols-outlined text-[18px] text-secondary">add_circle</span>
                            <span class="flex-1 text-secondary">Select addons...</span>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                {{ count($selectedAddons) }} selected
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-secondary"
                                :class="{ 'rotate-180': open }">expand_more</span>
                        </button>

                        <div x-cloak x-show="open" x-transition
                            class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            <div class="sticky top-0 border-b border-slate-100 bg-white p-2">
                                <input x-model="search" type="text" placeholder="Search addons..."
                                    class="w-full rounded-lg border border-outline-variant bg-slate-50 px-3 py-1.5 text-body-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10" />
                            </div>

                            <div class="max-h-44 space-y-0.5 overflow-y-auto p-1">
                                @forelse ($this->availableAddons() as $addon)
                                    <button type="button" wire:click="toggleAddon({{ $addon->id }})"
                                        x-show="!search || '{{ strtolower($addon->name) }}'.includes(search.toLowerCase())"
                                        wire:key="addon-{{ $addon->id }}"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
                                        :class="{ 'bg-primary/[0.04]': $wire.isAddonSelected({{ $addon->id }}) }">
                                        <span class="material-symbols-outlined text-[16px]"
                                            :class="$wire.isAddonSelected({{ $addon->id }}) ? 'text-primary' :
                                                'text-slate-300'">
                                            {{ $this->isAddonSelected($addon->id) ? 'check_box' : 'check_box_outline_blank' }}
                                        </span>
                                        <span
                                            class="flex-1 text-label-md font-label-md text-on-surface">{{ $addon->name }}</span>
                                        <span class="text-body-sm text-secondary">
                                            @if ($addon->price || $addon->monthly_price || $addon->yearly_price)
                                                ৳{{ number_format((float) ($addon->price ?: $addon->monthly_price ?: $addon->yearly_price), 0) }}
                                            @else
                                                Custom
                                            @endif
                                        </span>
                                    </button>
                                @empty
                                    <p class="px-3 py-4 text-center text-body-sm text-secondary">
                                        No addons available.
                                        <a href="{{ route('admin.plan-addons.index') }}" wire:navigate
                                            class="text-primary hover:underline">Create addons</a>
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($selectedAddons)
                        <div class="space-y-2">
                            <p class="text-label-sm font-label-sm text-secondary">Selected Addons</p>

                            @foreach ($selectedAddons as $index => $addonItem)
                                @php
                                    $addon = \App\Models\PlanAddon::find($addonItem['addon_id']);
                                @endphp

                                @if ($addon)
                                    <div wire:key="selected-addon-{{ $index }}"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span
                                                class="flex-1 min-w-0 text-label-md font-label-md text-on-surface truncate">
                                                {{ $addon->name }}
                                            </span>

                                            <button type="button"
                                                wire:click="removeSelectedAddon({{ $index }})"
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-50 hover:text-red-500">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <label class="block text-[10px] text-secondary mb-0.5">One-time</label>
                                                <input type="number" step="1" min="0"
                                                    wire:model.live="selectedAddons.{{ $index }}.price"
                                                    placeholder="{{ $addon->price ?? 'Price' }}"
                                                    class="w-full rounded border border-outline-variant px-2 py-1 text-body-sm text-on-surface text-right outline-none focus:ring-2 focus:ring-primary/10" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-secondary mb-0.5">Monthly</label>
                                                <input type="number" step="1" min="0"
                                                    wire:model.live="selectedAddons.{{ $index }}.monthly_price"
                                                    placeholder="{{ $addon->monthly_price ?? 'Price' }}"
                                                    class="w-full rounded border border-outline-variant px-2 py-1 text-body-sm text-on-surface text-right outline-none focus:ring-2 focus:ring-primary/10" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-secondary mb-0.5">Yearly</label>
                                                <input type="number" step="1" min="0"
                                                    wire:model.live="selectedAddons.{{ $index }}.yearly_price"
                                                    placeholder="{{ $addon->yearly_price ?? 'Price' }}"
                                                    class="w-full rounded border border-outline-variant px-2 py-1 text-body-sm text-on-surface text-right outline-none focus:ring-2 focus:ring-primary/10" />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @error('selectedAddons')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>


                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Sort Order
                    </h3>

                    <input type="number" min="0" wire:model="sort_order"
                        class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                    @error('sort_order')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Plan Status
                    </h3>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface">
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="text-xs text-secondary">Show or hide this plan publicly.</span>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model="is_active" class="peer sr-only" />
                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="py-5">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <button type="button" wire:click="discard" wire:loading.attr="disabled"
                    class="cursor-pointer rounded-lg border border-outline-variant px-5 py-3 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                    Discard Changes
                </button>

                <button type="submit" wire:loading.attr="disabled" wire:target="update"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="update">Update Plan</span>

                    <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Updating...
                    </span>
                </button>
            </div>
        </div>
    </form>
</div>
