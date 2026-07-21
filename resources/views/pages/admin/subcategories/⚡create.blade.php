<?php

use App\Models\Category;
use App\Models\Subcategory;
use App\Services\ImageService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Create Subcategory')] class extends Component {
    use WithFileUploads;

    public ?int $category_id = null;
    public string $name = '';
    public string $slug = '';
    public string $icon = '';
    public string $description = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public $image = null;

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:subcategories,slug'],
            'icon' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ];
    }

    protected function messages(): array
    {
        return [
            'category_id.required' => 'Please select a parent category.',
            'image.mimes' => 'Image must be JPG, PNG, WEBP, or SVG.',
            'slug.unique' => 'This subcategory slug already exists. Please use a different name.',
        ];
    }

    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function updatedName(): void
    {
        $this->slug = Str::slug($this->name);

        $this->validateOnly('name');

        if (filled($this->slug)) {
            $this->validateOnly('slug');
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

        while (Subcategory::query()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $imagePath = null;

        if ($this->image) {
            $imagePath = app(ImageService::class)->optimizeAndStore($this->image, 'subcategories', maxWidth: 800, quality: 85, filename: $this->name . '-' . now()->timestamp);
        }

        Subcategory::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($this->slug ?: $validated['name']),
            'icon' => $validated['icon'] ?: null,
            'image' => $imagePath,
            'description' => $validated['description'] ?: null,
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Subcategory created successfully.',
        ]);

        $this->redirectRoute('admin.subcategories.index', navigate: true);
    }

    public function discard(): void
    {
        $this->reset(['category_id', 'name', 'slug', 'icon', 'description', 'sort_order', 'image']);

        $this->is_active = true;

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>

<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Create Subcategory</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Create a subcategory under a category to organize plans and pricing.
            </p>
        </div>

        <a href="{{ route('admin.subcategories.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Subcategories
        </a>
    </div>

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-12 gap-6">
            <!-- Left Column -->
            <div class="col-span-12 space-y-6 lg:col-span-4">
                <!-- Subcategory Image -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-6 text-h3 font-h2">Subcategory Image</h3>

                    <label for="image"
                        class="flex h-64 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface transition-colors hover:bg-surface-container">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" alt="Subcategory preview"
                                class="h-full w-full object-contain p-8" />
                        @else
                            <span class="material-symbols-outlined mb-2 text-5xl text-outline">
                                add_photo_alternate
                            </span>

                            <p class="text-sm font-body-sm text-outline">
                                Click to upload subcategory image
                            </p>

                            <p class="mt-1 text-xs font-bold uppercase tracking-widest text-outline-variant">
                                PNG, JPG, WEBP, SVG up to 2MB
                            </p>
                        @endif
                    </label>

                    <input id="image" type="file" wire:model="image"
                        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" class="hidden" />

                    <div wire:loading wire:target="image" class="mt-3 text-sm text-primary">
                        Uploading image...
                    </div>

                    @error('image')
                        <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Subcategory Status
                    </h3>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center gap-3">
                            <div @class([
                                'h-2.5 w-2.5 rounded-full',
                                'bg-emerald-500' => $is_active,
                                'bg-red-500' => !$is_active,
                            ])></div>

                            <div>
                                <span class="block text-label-md font-label-md text-on-surface">
                                    {{ $is_active ? 'Active' : 'Inactive' }}
                                </span>

                                <span class="text-xs text-secondary">
                                    Show or hide this subcategory publicly.
                                </span>
                            </div>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only" />

                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100">
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Main Form -->
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">account_tree</span>
                        Subcategory Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Parent Category -->
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Parent Category
                            </label>

                            <div class="relative">
                                <select wire:model.live="category_id"
                                    class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-4 py-2.5 pr-10 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10">
                                    <option value="">Select a category</option>

                                    @foreach ($this->categories() as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>

                                <span
                                    class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    expand_more
                                </span>
                            </div>

                            @error('category_id')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Subcategory Name -->
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Subcategory Name
                            </label>

                            <input type="text" wire:model.live="name" placeholder="Example: Cloud Hosting"
                                class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10" />

                            @error('name')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Slug will be generated automatically:
                                <span class="font-mono text-primary">
                                    {{ $slug ?: 'subcategory-slug' }}
                                </span>
                            </p>

                            @error('slug')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Icon -->
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Icon
                            </label>

                            <div class="flex gap-3">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined text-[24px]">
                                        {{ $icon ?: 'account_tree' }}
                                    </span>
                                </div>

                                <input type="text" wire:model.live="icon" placeholder="Example: dns, cloud, security"
                                    class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10" />
                            </div>

                            @error('icon')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Use Material Symbol icon name.
                            </p>
                        </div>

                        <!-- Sort Order -->
                        <div class="space-y-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Sort Order
                            </label>

                            <input type="number" min="0" wire:model.live="sort_order" placeholder="0"
                                class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10" />

                            @error('sort_order')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="space-y-2 md:col-span-2">
                            <label class="text-label-sm font-label-sm uppercase tracking-wider text-secondary">
                                Description
                            </label>

                            <textarea wire:model.live="description" placeholder="Write short subcategory description..." rows="4"
                                class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-body-md font-body-md transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>

                            @error('description')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Bottom Buttons -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" wire:click="discard" wire:loading.attr="disabled"
                            class="rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            Discard Changes
                        </button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            <span wire:loading.remove wire:target="save">Save Subcategory</span>

                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
