<?php

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin-app')] #[Title('Edit Blog')] class extends Component {
    use WithFileUploads;

    public Blog $blog;

    public ?int $category_id = null;

    public string $title = '';
    public string $slug = '';
    public string $author_name = '';

    public string $excerpt = '';
    public string $content = '';

    public string $published_at = '';

    public string $meta_title = '';
    public string $meta_description = '';
    public string $meta_keywords = '';

    public bool $is_active = true;
    public bool $is_featured = false;

    public $thumbnail = null;

    public array $tags = [];
    public string $tag = '';

    public function mount(Blog $blog): void
    {
        $this->blog = $blog;

        $this->category_id = $blog->category_id;

        $this->title = $blog->title;
        $this->slug = $blog->slug;
        $this->author_name = $blog->author_name ?? '';

        $this->excerpt = $blog->excerpt ?? '';
        $this->content = $blog->content ?? '';

        $this->published_at = $blog->published_at?->format('Y-m-d') ?? '';

        $this->meta_title = $blog->meta_title ?? '';
        $this->meta_description = $blog->meta_description ?? '';
        $this->meta_keywords = $blog->meta_keywords ?? '';

        $this->is_active = (bool) $blog->is_active;
        $this->is_featured = (bool) $blog->is_featured;

        $this->tags = $blog->tags ?: [];
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'title' => ['required', 'string', 'max:180'],

            'slug' => ['required', 'string', 'max:220', Rule::unique('blogs', 'slug')->ignore($this->blog->id)],

            'author_name' => ['nullable', 'string', 'max:120'],

            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],

            'published_at' => ['nullable', 'date'],

            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],

            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],

            'thumbnail' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function messages(): array
    {
        return [
            'thumbnail.mimes' => 'Thumbnail must be JPG, PNG, WEBP, or SVG.',
            'content.required' => 'Blog content is required.',
        ];
    }

    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function selectedCategory()
    {
        if (!$this->category_id) {
            return null;
        }

        return $this->categories()->firstWhere('id', (int) $this->category_id);
    }

    public function updatedTitle(): void
    {
        $this->slug = Str::slug($this->title);

        $this->validateOnly('title');

        if (filled($this->slug)) {
            $this->validateOnly('slug');
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['title', 'content'], true)) {
            return;
        }

        $this->validateOnly($property);
    }

    public function addTag(): void
    {
        $tag = trim($this->tag);

        if ($tag === '') {
            $this->dispatch('toast', message: 'Please type a tag first.', type: 'warning');

            return;
        }

        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }

        $this->tag = '';
    }

    public function removeTag(int $index): void
    {
        unset($this->tags[$index]);

        $this->tags = array_values($this->tags);
    }

    private function uniqueSlug(string $value): string
    {
        $slug = Str::slug($value ?: $this->title);
        $originalSlug = $slug;
        $counter = 1;

        while (Blog::query()->where('slug', $slug)->where('id', '!=', $this->blog->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function update(): void
    {
        $validated = $this->validate();

        $thumbnailPath = $this->blog->thumbnail;

        if ($this->thumbnail) {
            if ($this->blog->thumbnail && Storage::disk('public')->exists($this->blog->thumbnail)) {
                Storage::disk('public')->delete($this->blog->thumbnail);
            }

            $thumbnailPath = $this->thumbnail->store('blogs/thumbnails', 'public');
        }

        $this->blog->update([
            'category_id' => $validated['category_id'] ?: null,

            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($this->slug ?: $validated['title']),

            'author_name' => $validated['author_name'] ?: null,
            'thumbnail' => $thumbnailPath,

            'excerpt' => $validated['excerpt'] ?: null,
            'content' => $validated['content'],

            'tags' => array_values(array_filter($validated['tags'] ?? [])),

            'published_at' => $validated['published_at'] ?: null,

            'meta_title' => $validated['meta_title'] ?: null,
            'meta_description' => $validated['meta_description'] ?: null,
            'meta_keywords' => $validated['meta_keywords'] ?: null,

            'is_active' => $validated['is_active'],
            'is_featured' => $validated['is_featured'],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Blog updated successfully.',
        ]);

        $this->redirectRoute('admin.blogs.index', navigate: true);
    }

    public function discard(): void
    {
        $this->category_id = $this->blog->category_id;

        $this->title = $this->blog->title;
        $this->slug = $this->blog->slug;
        $this->author_name = $this->blog->author_name ?? '';

        $this->excerpt = $this->blog->excerpt ?? '';
        $this->content = $this->blog->content ?? '';

        $this->published_at = $this->blog->published_at?->format('Y-m-d') ?? '';

        $this->meta_title = $this->blog->meta_title ?? '';
        $this->meta_description = $this->blog->meta_description ?? '';
        $this->meta_keywords = $this->blog->meta_keywords ?? '';

        $this->is_active = (bool) $this->blog->is_active;
        $this->is_featured = (bool) $this->blog->is_featured;

        $this->thumbnail = null;
        $this->tags = $this->blog->tags ?: [];
        $this->tag = '';

        $this->resetValidation();

        $this->dispatch('toast', message: 'Changes discarded.', type: 'info');
    }
};
?>


<div>
    <div class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-h1 font-h1 text-on-surface">Create Blog</h1>
            <p class="mt-1 text-body-md font-body-md text-secondary">
                Write and publish blog articles, SEO content, news, and company updates.
            </p>
        </div>

        <a href="{{ route('admin.blogs.index') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
            Back to Blogs
        </a>
    </div>

    <form wire:submit.prevent="update">
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">article</span>
                        Blog Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Blog Category</label>

                            <div class="relative">
                                <select wire:model.live="category_id"
                                    class="w-full appearance-none rounded border border-outline-variant bg-white px-4 py-2.5 pr-10 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10">
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

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Blog Title</label>

                            <input type="text" wire:model.live="title"
                                placeholder="Example: Why Businesses Need Managed IT Services"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('title')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-secondary">
                                Slug:
                                <span class="font-mono text-primary">{{ $slug ?: 'blog-slug' }}</span>
                            </p>

                            @error('slug')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Author Name</label>

                            <input type="text" wire:model.live="author_name" placeholder="Example: TechWave Team"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('author_name')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Excerpt</label>

                            <textarea wire:model.live="excerpt" placeholder="Short summary for blog card..." rows="3"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"></textarea>

                            @error('excerpt')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="block font-label-md text-on-surface">Blog Content</label>

                            <div wire:ignore x-data="{
                                quill: null,
                                value: @entangle('content'),
                                isUpdatingFromQuill: false,
                            
                                init() {
                                    if (!window.Quill) {
                                        console.error('Quill is not loaded');
                                        return;
                                    }
                            
                                    if (!window.QuillTableBetter) {
                                        console.error('QuillTableBetter is not loaded');
                                        return;
                                    }
                            
                                    const colorPalette = [
                                        false,
                                        '#000000',
                                        '#ffffff',
                                        '#44546a',
                                        '#5b9bd5',
                                        '#ed7d31',
                                        '#a5a5a5',
                                        '#ffc000',
                                        '#4472c4',
                                        '#70ad47',
                            
                                        // Dark shades
                                        '#7f7f7f',
                                        '#1f4e79',
                                        '#833c0c',
                                        '#525252',
                                        '#7f6000',
                                        '#2f5597',
                                        '#375623',
                            
                                        // Light shades
                                        '#d9eaf7',
                                        '#fce4d6',
                                        '#ededed',
                                        '#fff2cc',
                                        '#d9e2f3',
                                        '#e2f0d9',
                            
                                        // Accent colors
                                        '#c00000',
                                        '#ff0000',
                                        '#ffc000',
                                        '#ffff00',
                                        '#92d050',
                                        '#00b050',
                                        '#00b0f0',
                                        '#0070c0',
                                        '#002060',
                                        '#7030a0'
                                    ];
                            
                                    const backgroundColorPalette = [
                                        false,
                                        // Basic
                                        '#ffffff',
                                        '#000000',
                            
                                        // Word-like highlight colors
                                        '#ffff00',
                                        '#00ff00',
                                        '#00ffff',
                                        '#ff00ff',
                                        '#0000ff',
                                        '#ff0000',
                                        '#000080',
                                        '#008080',
                                        '#008000',
                                        '#800080',
                                        '#800000',
                                        '#808000',
                                        '#808080',
                                        '#c0c0c0',
                            
                                        // Soft background shades
                                        '#f2f2f2',
                                        '#d9eaf7',
                                        '#fce4d6',
                                        '#fff2cc',
                                        '#e2f0d9',
                                        '#d9e2f3',
                                        '#eadcf8'
                                    ];
                            
                                    Quill.register({
                                        'modules/table-better': QuillTableBetter
                                    }, true);
                            
                                    this.quill = new Quill(this.$refs.editor, {
                                        theme: 'snow',
                                        placeholder: 'Describe the service technical architecture and business value...',
                                        modules: {
                                            toolbar: [
                                                [{ header: [2, 3, false] }],
                                                [{ font: [] }],
                                                ['bold', 'italic', 'underline', 'strike'],
                            
                                                [
                                                    { color: colorPalette },
                                                    { background: backgroundColorPalette }
                                                ],
                            
                                                [{ list: 'ordered' }, { list: 'bullet' }],
                                                [{ align: [] }],
                                                ['blockquote', 'code-block'],
                                                ['link'],
                                                ['table-better'],
                                                ['clean']
                                            ],
                            
                                            table: false,
                            
                                            'table-better': {
                                                language: 'en_US',
                                                menus: [
                                                    'column',
                                                    'row',
                                                    'merge',
                                                    'table',
                                                    'cell',
                                                    'wrap',
                                                    'copy',
                                                    'delete'
                                                ],
                                                toolbarTable: true
                                            },
                            
                                            keyboard: {
                                                bindings: QuillTableBetter.keyboardBindings
                                            }
                                        }
                                    });
                            
                                    if (this.value) {
                                        this.quill.root.innerHTML = this.value;
                                    }
                            
                                    this.fixToolbarButtons();
                            
                                    this.quill.on('text-change', () => {
                                        this.isUpdatingFromQuill = true;
                                        this.value = this.quill.root.innerHTML;
                            
                                        setTimeout(() => {
                                            this.isUpdatingFromQuill = false;
                                        }, 100);
                                    });
                            
                                    this.$watch('value', (newValue) => {
                                        if (this.isUpdatingFromQuill) {
                                            return;
                                        }
                            
                                        if (this.quill.root.innerHTML !== newValue) {
                                            let range = this.quill.getSelection();
                            
                                            this.quill.root.innerHTML = newValue || '';
                            
                                            if (range) {
                                                this.quill.setSelection(range.index, range.length);
                                            }
                                        }
                                    });
                                },
                            
                                fixToolbarButtons() {
                                    const toolbar = this.$el.querySelector('.ql-toolbar');
                            
                                    if (!toolbar) return;
                            
                                    toolbar.querySelectorAll('button').forEach((button) => {
                                        button.setAttribute('type', 'button');
                                    });
                                },
                            
                                addNoColorButton() {
                                    const toolbar = this.$el.querySelector('.ql-toolbar');
                            
                                    if (!toolbar) return;
                            
                                    if (toolbar.querySelector('.ql-custom-no-color')) return;
                            
                                    const group = document.createElement('span');
                                    group.className = 'ql-formats';
                            
                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    button.className = 'ql-custom-no-color';
                                    button.innerText = 'No Color';
                                    button.title = 'Remove text and background color';
                            
                                    button.addEventListener('mousedown', (event) => {
                                        event.preventDefault();
                                    });
                            
                                    button.addEventListener('click', () => {
                                        const range = this.quill.getSelection(true);
                            
                                        if (!range) return;
                            
                                        if (range.length > 0) {
                                            this.quill.formatText(range.index, range.length, {
                                                color: false,
                                                background: false
                                            }, Quill.sources.USER);
                                        } else {
                                            this.quill.format('color', false, Quill.sources.USER);
                                            this.quill.format('background', false, Quill.sources.USER);
                                        }
                            
                                        this.value = this.quill.root.innerHTML;
                                    });
                            
                                    group.appendChild(button);
                                    toolbar.appendChild(group);
                                }
                            }"
                                class="rich-text-editor relative overflow-visible rounded-lg border border-outline-variant bg-white">
                                <div x-ref="editor" class="quill-edit-scroll"></div>
                            </div>

                            @error('content')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="block font-label-md text-on-surface">Published Date</label>

                            <input type="date" wire:model.live="published_at"
                                class="w-full rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10" />

                            @error('published_at')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>



                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-8 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">travel_explore</span>
                        SEO Meta Information
                    </h3>

                    <div class="grid grid-cols-1 gap-6">
                        <input wire:model.live="meta_title"
                            class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Meta title" type="text" />

                        <textarea wire:model.live="meta_description"
                            class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Meta description" rows="3"></textarea>

                        <textarea wire:model.live="meta_keywords"
                            class="w-full rounded-lg border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Meta keywords" rows="2"></textarea>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" wire:click="discard" wire:loading.attr="disabled"
                            class="rounded-lg border border-outline-variant px-5 py-2 text-label-md font-label-md text-on-surface transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                            Discard Changes
                        </button>

                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-label-md font-label-md text-white shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            <span wire:loading.remove wire:target="update">Update Blog</span>

                            <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Updating...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-span-12 space-y-6 lg:col-span-4">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-6 text-h3 font-h2">Blog Thumbnail</h3>

                    <label for="thumbnail"
                        class="flex h-64 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-outline-variant bg-surface transition-colors hover:bg-surface-container">
                        @if ($thumbnail)
                            <img src="{{ $thumbnail->temporaryUrl() }}" alt="Blog preview"
                                class="h-full w-full object-cover" />
                        @elseif ($blog->thumbnail)
                            <img src="{{ Storage::url($blog->thumbnail) }}" alt="{{ $blog->title }}"
                                class="h-full w-full object-cover" />
                        @else
                            <span class="material-symbols-outlined mb-2 text-5xl text-outline">
                                add_photo_alternate
                            </span>

                            <p class="text-sm font-body-sm text-outline">
                                Click to upload blog thumbnail
                            </p>

                            <p class="mt-1 text-xs font-bold uppercase tracking-widest text-outline-variant">
                                PNG, JPG, WEBP, SVG up to 5MB
                            </p>
                        @endif
                    </label>

                    <input id="thumbnail" type="file" wire:model="thumbnail"
                        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" class="hidden" />

                    <div wire:loading wire:target="thumbnail" class="mt-3 text-sm text-primary">
                        Uploading thumbnail...
                    </div>

                    @error('thumbnail')
                        <p class="mt-3 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-label-sm font-label-sm uppercase tracking-widest text-secondary">
                        Blog Settings
                    </h3>

                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface">
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="text-xs text-secondary">Show or hide this blog publicly.</span>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only" />
                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white">
                            </div>
                        </label>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50/50 p-3">
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface">
                                Featured Blog
                            </span>
                            <span class="text-xs text-secondary">Highlight this blog on homepage.</span>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model.live="is_featured" class="peer sr-only" />
                            <div
                                class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-amber-500 peer-checked:after:translate-x-full peer-checked:after:border-white">
                            </div>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="mb-6 flex items-center gap-2 text-h3 font-h2">
                        <span class="material-symbols-outlined text-primary">sell</span>
                        Blog Tags
                    </h3>

                    <div class="mb-4 flex gap-3">
                        <input wire:model.live="tag" wire:keydown.enter.prevent="addTag"
                            class="flex-1 rounded border border-outline-variant px-4 py-2.5 font-body-md outline-none transition-all focus:ring-2 focus:ring-[#0F52BA] focus:ring-opacity-10"
                            placeholder="Laravel, Cybersecurity, Hosting" type="text" />

                        <button type="button" wire:click="addTag"
                            class="flex items-center gap-1 rounded border border-dashed border-[#0F52BA] px-4 py-2.5 text-sm font-semibold text-[#0F52BA] transition-colors hover:bg-primary/5">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Add
                        </button>
                    </div>

                    <div class="flex min-h-15 flex-wrap gap-2 rounded-lg border border-slate-100 bg-surface p-4">
                        @forelse ($tags as $index => $item)
                            <div wire:key="tag-{{ $index }}"
                                class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-3 py-1.5 shadow-sm">
                                <span class="text-sm font-body-md">{{ $item }}</span>

                                <button type="button" wire:click="removeTag({{ $index }})"
                                    class="material-symbols-outlined text-sm text-outline hover:text-error">
                                    close
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-secondary">No tags added yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-5 text-h3 font-h2">Quick Preview</h3>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            @if ($this->selectedCategory())
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    <span class="material-symbols-outlined text-[14px]">
                                        {{ $this->selectedCategory()->icon ?: 'category' }}
                                    </span>
                                    {{ $this->selectedCategory()->name }}
                                </span>
                            @endif

                            @if ($is_featured)
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <span class="material-symbols-outlined text-[14px]">stars</span>
                                    Featured
                                </span>
                            @endif

                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700' => $is_active,
                                'bg-red-100 text-red-700' => !$is_active,
                            ])>
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <h4 class="text-lg font-semibold text-on-surface">
                            {{ $title ?: 'Blog Title' }}
                        </h4>

                        <p class="mt-1 font-mono text-xs text-primary">
                            {{ $slug ?: 'blog-slug' }}
                        </p>

                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            {{ $excerpt ?: 'Blog excerpt will appear here.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse ($tags as $previewTag)
                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                    {{ $previewTag }}
                                </span>
                            @empty
                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-400 shadow-sm">
                                    No tags yet
                                </span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
