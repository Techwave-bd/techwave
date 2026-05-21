<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Site Settings')] class extends Component {
    use WithFileUploads;

    public SiteSetting $setting;

    public string $activeTab = 'basic';

    public array $originalState = [];

    public string $site_name = '';
    public string $email = '';
    public string $phone = '';
    public string $location = '';
    public string $map_embed_link = '';

    public string $facebook_url = '';
    public string $linkedin_url = '';
    public string $twitter_url = '';
    public string $instagram_url = '';
    public string $youtube_url = '';
    public string $github_url = '';
    public string $whatsapp_url = '';

    public string $terms_conditions = '';
    public string $privacy_policy = '';

    public $logo = null;
    public $favicon = null;

    public array $tabs = [
        'basic' => [
            'label' => 'Basic Settings',
            'icon' => 'settings',
        ],
        'legal' => [
            'label' => 'Legal Pages',
            'icon' => 'policy',
        ],
        'social' => [
            'label' => 'Social Media',
            'icon' => 'share',
        ],
        'branding' => [
            'label' => 'Branding',
            'icon' => 'imagesmode',
        ],
    ];

    public function mount(): void
    {
        $this->setting = SiteSetting::current();

        $this->site_name = $this->setting->site_name ?? '';
        $this->email = $this->setting->email ?? '';
        $this->phone = $this->setting->phone ?? '';
        $this->location = $this->setting->location ?? '';
        $this->map_embed_link = $this->setting->map_embed_link ?? '';

        $this->facebook_url = $this->setting->facebook_url ?? '';
        $this->linkedin_url = $this->setting->linkedin_url ?? '';
        $this->twitter_url = $this->setting->twitter_url ?? '';
        $this->instagram_url = $this->setting->instagram_url ?? '';
        $this->youtube_url = $this->setting->youtube_url ?? '';
        $this->github_url = $this->setting->github_url ?? '';
        $this->whatsapp_url = $this->setting->whatsapp_url ?? '';

        $this->terms_conditions = $this->setting->terms_conditions ?? '';
        $this->privacy_policy = $this->setting->privacy_policy ?? '';

        $this->captureOriginalState();
    }

    protected function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
            'map_embed_link' => ['nullable', 'string', 'max:2000'],

            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_url' => ['nullable', 'url', 'max:255'],

            'terms_conditions' => ['nullable', 'string'],
            'privacy_policy' => ['nullable', 'string'],

            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg,ico', 'max:2048'],
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

    private function captureOriginalState(): void
    {
        $this->originalState = [
            'site_name' => $this->site_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'map_embed_link' => $this->map_embed_link,

            'facebook_url' => $this->facebook_url,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'instagram_url' => $this->instagram_url,
            'youtube_url' => $this->youtube_url,
            'github_url' => $this->github_url,
            'whatsapp_url' => $this->whatsapp_url,

            'terms_conditions' => $this->terms_conditions,
            'privacy_policy' => $this->privacy_policy,

            'has_logo_upload' => false,
            'has_favicon_upload' => false,
        ];
    }

    private function hasChanges(): bool
    {
        $currentState = [
            'site_name' => $this->site_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'map_embed_link' => $this->map_embed_link,

            'facebook_url' => $this->facebook_url,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'instagram_url' => $this->instagram_url,
            'youtube_url' => $this->youtube_url,
            'github_url' => $this->github_url,
            'whatsapp_url' => $this->whatsapp_url,

            'terms_conditions' => $this->terms_conditions,
            'privacy_policy' => $this->privacy_policy,

            'has_logo_upload' => $this->logo !== null,
            'has_favicon_upload' => $this->favicon !== null,
        ];

        return $currentState !== $this->originalState;
    }

    public function save(): void
    {
        if (!$this->hasChanges()) {
            $this->dispatch('toast', message: 'Nothing to update.', type: 'warning');

            return;
        }

        $validated = $this->validate();

        $logoPath = $this->setting->logo;
        $faviconPath = $this->setting->favicon;

        if ($this->logo) {
            if ($this->setting->logo && Storage::disk('public')->exists($this->setting->logo)) {
                Storage::disk('public')->delete($this->setting->logo);
            }

            $logoPath = $this->logo->store('settings/logo', 'public');
        }

        if ($this->favicon) {
            if ($this->setting->favicon && Storage::disk('public')->exists($this->setting->favicon)) {
                Storage::disk('public')->delete($this->setting->favicon);
            }

            $faviconPath = $this->favicon->store('settings/favicon', 'public');
        }

        $this->setting->update([
            'site_name' => $validated['site_name'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'location' => $validated['location'] ?: null,
            'map_embed_link' => $validated['map_embed_link'] ?: null,

            'facebook_url' => $validated['facebook_url'] ?: null,
            'linkedin_url' => $validated['linkedin_url'] ?: null,
            'twitter_url' => $validated['twitter_url'] ?: null,
            'instagram_url' => $validated['instagram_url'] ?: null,
            'youtube_url' => $validated['youtube_url'] ?: null,
            'github_url' => $validated['github_url'] ?: null,
            'whatsapp_url' => $validated['whatsapp_url'] ?: null,

            'terms_conditions' => $validated['terms_conditions'] ?: null,
            'privacy_policy' => $validated['privacy_policy'] ?: null,

            'logo' => $logoPath,
            'favicon' => $faviconPath,
        ]);

        $this->logo = null;
        $this->favicon = null;

        $this->setting = $this->setting->fresh();

        $this->captureOriginalState();

        $this->dispatch('toast', message: 'Settings updated successfully.', type: 'success');
    }
};
?>

<div>
    <div class="mx-auto w-full max-w-7xl space-y-8">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Site Settings</h1>
            <p class="mt-1 text-body-md text-secondary">
                Manage website information, legal pages, social media and branding.
            </p>
        </div>

        <form wire:submit.prevent="save">
            <div class="space-y-6">
                <!-- Top Heading Tabs -->
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tabs as $key => $tab)
                            <button type="button" wire:click="setTab('{{ $key }}')"
                                @class([
                                    'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                                    'bg-primary text-white shadow-sm' => $activeTab === $key,
                                    'text-slate-600 hover:bg-slate-50 hover:text-primary' =>
                                        $activeTab !== $key,
                                ])>
                                <span class="material-symbols-outlined text-[20px]">
                                    {{ $tab['icon'] }}
                                </span>
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Basic Settings -->
                @if ($activeTab === 'basic')
                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                            <span class="material-symbols-outlined text-primary">business</span>
                            Basic Information
                        </h3>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Site Name</label>
                                <input type="text" wire:model.live="site_name"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="TechWave" />
                                @error('site_name')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Email</label>
                                <input type="email" wire:model.live="email"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="info@example.com" />
                                @error('email')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Phone / Number</label>
                                <input type="text" wire:model.live="phone"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="+880..." />
                                @error('phone')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Location</label>
                                <input type="text" wire:model.live="location"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="Dhaka, Bangladesh" />
                                @error('location')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <label class="block font-label-md text-on-surface">Map Embed / Map Link</label>
                                <textarea wire:model.live="map_embed_link" rows="3"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="Google map iframe embed code or map link"></textarea>
                                @error('map_embed_link')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Legal Pages -->
                @if ($activeTab === 'legal')
                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                            <span class="material-symbols-outlined text-primary">policy</span>
                            Legal Pages
                        </h3>

                        <div class="space-y-8">
                            <!-- Terms & Conditions -->
                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">
                                    Terms & Conditions
                                </label>

                                <div wire:ignore x-data="{
                                    quill: null,
                                    value: @entangle('terms_conditions'),
                                
                                    cleanEditorValue() {
                                        const text = this.quill.getText().trim();
                                
                                        if (!text.length) {
                                            return '';
                                        }
                                
                                        return this.quill.root.innerHTML;
                                    },
                                
                                    init() {
                                        this.quill = new Quill(this.$refs.editor, {
                                            theme: 'snow',
                                            placeholder: 'Write your terms and conditions here...',
                                            modules: {
                                                toolbar: [
                                                    [{ header: [1, 2, 3, 4, false] }],
                                                    [{ font: [] }],
                                                    ['bold', 'italic', 'underline', 'strike'],
                                                    [{ color: [] }, { background: [] }],
                                                    [{ list: 'ordered' }, { list: 'bullet' }],
                                                    [{ indent: '-1' }, { indent: '+1' }],
                                                    [{ align: [] }],
                                                    ['blockquote', 'code-block'],
                                                    ['link'],
                                                    ['clean']
                                                ]
                                            }
                                        });
                                
                                        if (this.value && this.value !== '<p><br></p>') {
                                            this.quill.clipboard.dangerouslyPasteHTML(this.value);
                                        }
                                
                                        this.quill.on('text-change', () => {
                                            this.value = this.cleanEditorValue();
                                        });
                                
                                        this.$watch('value', (newValue) => {
                                            const cleanValue = newValue === '<p><br></p>' ? '' : newValue;
                                
                                            if (this.quill.root.innerHTML !== cleanValue) {
                                                this.quill.clipboard.dangerouslyPasteHTML(cleanValue || '');
                                            }
                                        });
                                    }
                                }"
                                    class="overflow-hidden rounded-lg border border-outline-variant bg-white">
                                    <div x-ref="editor" class="legal-quill-editor"></div>
                                </div>

                                @error('terms_conditions')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Privacy Policy -->
                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">
                                    Privacy Policy
                                </label>

                                <div wire:ignore x-data="{
                                    quill: null,
                                    value: @entangle('privacy_policy'),
                                
                                    cleanEditorValue() {
                                        const text = this.quill.getText().trim();
                                
                                        if (!text.length) {
                                            return '';
                                        }
                                
                                        return this.quill.root.innerHTML;
                                    },
                                
                                    init() {
                                        this.quill = new Quill(this.$refs.editor, {
                                            theme: 'snow',
                                            placeholder: 'Write your privacy policy here...',
                                            modules: {
                                                toolbar: [
                                                    [{ header: [1, 2, 3, 4, false] }],
                                                    [{ font: [] }],
                                                    ['bold', 'italic', 'underline', 'strike'],
                                                    [{ color: [] }, { background: [] }],
                                                    [{ list: 'ordered' }, { list: 'bullet' }],
                                                    [{ indent: '-1' }, { indent: '+1' }],
                                                    [{ align: [] }],
                                                    ['blockquote', 'code-block'],
                                                    ['link'],
                                                    ['clean']
                                                ]
                                            }
                                        });
                                
                                        if (this.value && this.value !== '<p><br></p>') {
                                            this.quill.clipboard.dangerouslyPasteHTML(this.value);
                                        }
                                
                                        this.quill.on('text-change', () => {
                                            this.value = this.cleanEditorValue();
                                        });
                                
                                        this.$watch('value', (newValue) => {
                                            const cleanValue = newValue === '<p><br></p>' ? '' : newValue;
                                
                                            if (this.quill.root.innerHTML !== cleanValue) {
                                                this.quill.clipboard.dangerouslyPasteHTML(cleanValue || '');
                                            }
                                        });
                                    }
                                }"
                                    class="overflow-hidden rounded-lg border border-outline-variant bg-white">
                                    <div x-ref="editor" class="legal-quill-editor"></div>
                                </div>

                                @error('privacy_policy')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Social Media -->
                @if ($activeTab === 'social')
                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                            <span class="material-symbols-outlined text-primary">share</span>
                            Social Media
                        </h3>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Facebook URL</label>
                                <input type="url" wire:model.live="facebook_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="Facebook URL" />
                                @error('facebook_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">LinkedIn URL</label>
                                <input type="url" wire:model.live="linkedin_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="LinkedIn URL" />
                                @error('linkedin_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Twitter / X URL</label>
                                <input type="url" wire:model.live="twitter_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="Twitter / X URL" />
                                @error('twitter_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">Instagram URL</label>
                                <input type="url" wire:model.live="instagram_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="Instagram URL" />
                                @error('instagram_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">YouTube URL</label>
                                <input type="url" wire:model.live="youtube_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="YouTube URL" />
                                @error('youtube_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="block font-label-md text-on-surface">GitHub URL</label>
                                <input type="url" wire:model.live="github_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="GitHub URL" />
                                @error('github_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <label class="block font-label-md text-on-surface">WhatsApp URL</label>
                                <input type="url" wire:model.live="whatsapp_url"
                                    class="w-full rounded border border-outline-variant px-4 py-2.5"
                                    placeholder="WhatsApp URL" />
                                @error('whatsapp_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Branding -->
                @if ($activeTab === 'branding')
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="mb-6 text-h3 font-h2">Site Logo</h3>

                            <label for="logo"
                                class="flex h-48 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface">
                                @if ($logo)
                                    <img src="{{ $logo->temporaryUrl() }}"
                                        class="h-full w-full object-contain p-5" />
                                @elseif ($setting->logo)
                                    <img src="{{ Storage::url($setting->logo) }}"
                                        class="h-full w-full object-contain p-5" />
                                @else
                                    <span class="material-symbols-outlined mb-2 text-5xl text-outline">image</span>
                                    <p class="text-sm text-outline">Upload logo</p>
                                @endif
                            </label>

                            <input id="logo" type="file" wire:model="logo" accept="image/*,.svg"
                                class="hidden" />
                            @error('logo')
                                <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="mb-6 text-h3 font-h2">Favicon</h3>

                            <label for="favicon"
                                class="flex h-48 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface">
                                @if ($favicon)
                                    <img src="{{ $favicon->temporaryUrl() }}"
                                        class="h-full w-full object-contain p-5" />
                                @elseif ($setting->favicon)
                                    <img src="{{ Storage::url($setting->favicon) }}"
                                        class="h-full w-full object-contain p-5" />
                                @else
                                    <span
                                        class="material-symbols-outlined mb-2 text-5xl text-outline">add_photo_alternate</span>
                                    <p class="text-sm text-outline">Upload favicon</p>
                                @endif
                            </label>

                            <input id="favicon" type="file" wire:model="favicon" accept="image/*,.svg,.ico"
                                class="hidden" />
                            @error('favicon')
                                <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                <!-- Main Save Button -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90">
                            <span wire:loading.remove wire:target="save">Save Settings</span>
                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>