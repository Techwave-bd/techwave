<?php

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Blogs | Techwave')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $tag = '';

    public int $perPage = 6;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedTag(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'category',
            'tag',
        ]);

        $this->resetPage();
    }

    public function blogs()
    {
        $search = trim($this->search);

        return Blog::query()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhere('excerpt', 'like', '%' . $search . '%')
                        ->orWhere('author_name', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($this->category !== '', function ($query) {
                $query->whereHas('category', function ($categoryQuery) {
                    $categoryQuery
                        ->where('slug', $this->category)
                        ->orWhere('id', $this->category);
                });
            })
            ->when($this->tag !== '', function ($query) {
                $query->whereJsonContains('tags', $this->tag);
            })
            ->latest('published_at')
            ->latest()
            ->paginate($this->perPage, [
                'id',
                'category_id',
                'title',
                'slug',
                'thumbnail',
                'excerpt',
                'published_at',
            ]);
    }

    public function getCategoriesProperty()
    {
        return Category::query()
            ->whereHas('blogs', function ($query) {
                $query->where('is_active', true);
            })
            ->withCount([
                'blogs as active_blogs_count' => function ($query) {
                    $query->where('is_active', true);
                },
            ])
            ->orderBy('name')
            ->limit(10)
            ->get([
                'id',
                'name',
                'slug',
            ]);
    }

    public function getRecentBlogsProperty()
    {
        return Blog::query()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->latest('published_at')
            ->latest()
            ->limit(3)
            ->get([
                'id',
                'category_id',
                'title',
                'slug',
                'thumbnail',
                'published_at',
            ]);
    }

    public function getKeywordTagsProperty(): array
    {
        return Blog::query()
            ->where('is_active', true)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->take(14)
            ->values()
            ->toArray();
    }

    public function blogImage(Blog $blog): ?string
    {
        if (blank($blog->thumbnail)) {
            return null;
        }

        if (str_starts_with($blog->thumbnail, 'http://') || str_starts_with($blog->thumbnail, 'https://')) {
            return $blog->thumbnail;
        }

        return asset('storage/' . $blog->thumbnail);
    }
};
?>

<div class="relative text-white">
    @push('meta')
        <meta name="title" content="Blogs | {{ $siteSetting->site_name ?: config('app.name') }}">
        <meta name="description"
            content="Explore expert articles on IT support, cybersecurity, business systems, websites, cloud tools, productivity, and digital transformation.">
    @endpush

    <!-- Hero -->
    <section class="relative overflow-hidden py-18 sm:py-24 lg:py-28">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute left-[6%] top-8 h-44 w-44 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute right-[8%] top-12 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
                <div class="max-w-3xl">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs sm:text-sm text-blue-100/85 backdrop-blur-xl">
                        <span class="h-2 w-2 rounded-full bg-cyan-300 animate-pulse"></span>
                        Blog & Insights
                    </div>

                    <h1
                        class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl lg:text-7xl">
                        Insights, guides, and
                        <span class="bg-linear-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                            practical ideas for growth
                        </span>
                    </h1>

                    <p class="mt-6 max-w-2xl text-sm leading-7 text-blue-100/72 sm:text-base sm:leading-8">
                        Explore expert articles on IT support, cybersecurity, business systems, websites, cloud tools,
                        productivity, and digital transformation.
                    </p>
                </div>

                <div class="relative">
                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/15 bg-white/8 p-3 shadow-[0_25px_80px_rgba(0,0,0,0.24)] backdrop-blur-2xl">
                        <div class="absolute left-8 top-8 h-24 w-24 rounded-full bg-cyan-400/12 blur-3xl"></div>
                        <div class="absolute bottom-8 right-8 h-32 w-32 rounded-full bg-blue-500/12 blur-3xl"></div>

                        <div class="overflow-hidden rounded-3xl border border-white/10">
                            <img
                                src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1000&q=70"
                                alt="Blog hero"
                                width="1000"
                                height="650"
                                fetchpriority="high"
                                loading="eager"
                                decoding="async"
                                class="h-80 w-full object-cover sm:h-100"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Content -->
    <section class="relative overflow-hidden pb-20 sm:pb-24">
        @php
            $blogs = $this->blogs();
            $categories = $this->categories;
            $recentBlogs = $this->recentBlogs;
            $keywordTags = $this->keywordTags;
        @endphp

        <div class="mx-auto max-w-350 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[1fr_350px] xl:grid-cols-[1fr_390px]">
                <!-- Main Grid -->
                <div>
                    @if ($search || $category || $tag)
                        <div
                            class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/6 p-4 backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm text-blue-100/70">
                                Showing results
                                @if ($search)
                                    for <span class="font-semibold text-white">“{{ $search }}”</span>
                                @endif

                                @if ($category)
                                    in selected category
                                @endif

                                @if ($tag)
                                    tagged <span class="font-semibold text-white">“{{ $tag }}”</span>
                                @endif
                            </div>

                            <button type="button" wire:click="clearFilters"
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/8 px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/12">
                                Clear Filters
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        @forelse ($blogs as $blog)
                            @php
                                $blogImage = $this->blogImage($blog);
                            @endphp

                            <article class="blog-card">
                                @if ($blogImage)
                                    <a href="{{ route('client.blogs.details', $blog->slug) }}" wire:navigate
                                        class="block overflow-hidden rounded-[24px] border border-white/10">
                                        <img
                                            src="{{ $blogImage }}"
                                            alt="{{ $blog->title }}"
                                            width="700"
                                            height="450"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-56 w-full object-cover transition duration-700 hover:scale-105"
                                        >
                                    </a>
                                @else
                                    <a href="{{ route('client.blogs.details', $blog->slug) }}" wire:navigate
                                        class="relative block h-56 overflow-hidden rounded-[24px] border border-white/10 bg-slate-950/25">
                                        <div
                                            class="absolute inset-0 bg-linear-to-br from-slate-950/85 via-blue-950/40 to-cyan-950/20">
                                        </div>
                                        <div
                                            class="absolute left-6 top-6 h-24 w-24 rounded-full bg-cyan-400/10 blur-3xl">
                                        </div>
                                        <div
                                            class="absolute bottom-6 right-6 h-28 w-28 rounded-full bg-blue-500/10 blur-3xl">
                                        </div>

                                        <div
                                            class="relative z-10 flex h-full items-center justify-center px-6 text-center">
                                            <span
                                                class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-100/45">
                                                {{ $blog->category?->name ?? 'Blog Insight' }}
                                            </span>
                                        </div>
                                    </a>
                                @endif

                                <div class="pt-5">
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-blue-100/55">
                                        <span class="blog-chip">
                                            {{ $blog->category?->name ?? 'Blog' }}
                                        </span>

                                        @if ($blog->published_at)
                                            <span>{{ $blog->published_at->format('M d, Y') }}</span>
                                        @endif
                                    </div>

                                    <a href="{{ route('client.blogs.details', $blog->slug) }}" wire:navigate>
                                        <h3 class="mt-4 text-2xl font-bold text-white transition hover:text-cyan-200">
                                            {{ $blog->title }}
                                        </h3>
                                    </a>

                                    @if ($blog->excerpt)
                                        <p class="mt-3 text-sm leading-7 text-blue-100/68">
                                            {{ Str::limit($blog->excerpt, 145) }}
                                        </p>
                                    @endif

                                    <a href="{{ route('client.blogs.details', $blog->slug) }}" wire:navigate
                                        class="blog-link mt-5">
                                        Read More
                                    </a>
                                </div>
                            </article>
                        @empty
                            <div
                                class="col-span-full rounded-[28px] border border-white/10 bg-white/6 p-10 text-center backdrop-blur-2xl">
                                <h3 class="text-2xl font-bold text-white">No blogs found</h3>
                                <p class="mt-3 text-sm text-blue-100/70">
                                    Please add active blogs from your admin panel or change your filters.
                                </p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pagination -->
                    <div class="mt-10">
                        {{ $blogs->links() }}
                    </div>
                </div>

                <!-- Sidebar -->
                <aside class="space-y-6">
                    <!-- Search -->
                    <div class="blog-sidebar-card">
                        <h3 class="text-2xl font-bold text-white">Search Blog</h3>

                        <form wire:submit.prevent="$refresh" class="mt-6">
                            <div class="relative">
                                <input type="text" wire:model.live.debounce.400ms="search"
                                    placeholder="Search articles..." class="blog-search-input pr-12" />

                                <button type="submit"
                                    class="absolute right-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/10 bg-white/8 text-blue-100/70 transition hover:bg-white/12 hover:text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Categories -->
                    @if ($categories->count())
                        <div class="blog-sidebar-card">
                            <h3 class="text-2xl font-bold text-white">Categories</h3>

                            <div class="mt-6 space-y-3">
                                @foreach ($categories as $item)
                                    <button type="button"
                                        wire:click="$set('category', '{{ $item->slug ?? $item->id }}')"
                                        @class([
                                            'blog-category-item w-full',
                                            'border-cyan-300/20 bg-cyan-400/10 text-cyan-100' =>
                                                $category === ($item->slug ?? (string) $item->id),
                                        ])>
                                        <span>{{ $item->name }}</span>
                                        <span>{{ $item->active_blogs_count }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Recent Posts -->
                    @if ($recentBlogs->count())
                        <div class="blog-sidebar-card">
                            <h3 class="text-2xl font-bold text-white">Recent Posts</h3>

                            <div class="mt-6 space-y-4">
                                @foreach ($recentBlogs as $recentBlog)
                                    @php
                                        $recentBlogImage = $this->blogImage($recentBlog);
                                    @endphp

                                    <a href="{{ route('client.blogs.details', $recentBlog->slug) }}" wire:navigate
                                        class="recent-post-item">

                                        @if ($recentBlogImage)
                                            <img
                                                src="{{ $recentBlogImage }}"
                                                alt="{{ $recentBlog->title }}"
                                                width="72"
                                                height="72"
                                                loading="lazy"
                                                decoding="async"
                                                class="h-18 w-18 rounded-2xl object-cover"
                                            >
                                        @else
                                            <div
                                                class="flex h-18 w-18 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/8 text-cyan-200">
                                                <span class="material-symbols-outlined text-[22px]">
                                                    article
                                                </span>
                                            </div>
                                        @endif

                                        <div>
                                            <h4 class="text-sm font-semibold leading-6 text-white">
                                                {{ Str::limit($recentBlog->title, 58) }}
                                            </h4>

                                            @if ($recentBlog->published_at)
                                                <p class="mt-1 text-xs text-blue-100/50">
                                                    {{ $recentBlog->published_at->format('M d, Y') }}
                                                </p>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Keyword Tags -->
                    @if (! empty($keywordTags))
                        <div class="blog-sidebar-card">
                            <h3 class="text-2xl font-bold text-white">Keyword Tags</h3>

                            <div class="mt-6 flex flex-wrap gap-2">
                                @foreach ($keywordTags as $item)
                                    <button type="button" wire:click="$set('tag', '{{ $item }}')"
                                        @class([
                                            'blog-tag',
                                            'border-cyan-300/20 bg-cyan-400/10 text-cyan-100' => $tag === $item,
                                        ])>
                                        {{ $item }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- CTA -->
                    <div class="blog-sidebar-card">
                        <h3 class="text-2xl font-bold text-white">Need help with your business IT?</h3>
                        <p class="mt-4 text-sm leading-7 text-blue-100/68">
                            Talk to our team about support, security, websites, cloud systems, and custom solutions.
                        </p>

                        <a href="{{ route('client.services') }}" wire:navigate
                            class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-sky-400 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition hover:-translate-y-0.5">
                            Explore Services
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>