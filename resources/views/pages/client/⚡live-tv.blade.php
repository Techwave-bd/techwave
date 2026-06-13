<?php

use App\Models\LiveTvChannel;
use App\Models\SiteSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live TV')] class extends Component {
    public bool $enabled = false;
    public array $channels = [];

    public function mount(): void
    {
        $setting = SiteSetting::current();
        $this->enabled = $setting->live_tv_enabled ?? false;

        if ($this->enabled) {
            $this->channels = LiveTvChannel::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($ch) => [
                    'name' => $ch->name,
                    'url' => $ch->url,
                    'cat' => $ch->category,
                ])
                ->toArray();
        }
    }
};
?>

<div class="min-h-screen text-white" x-data="liveTv()">
    <section class="relative mx-auto max-w-350 px-4 py-10 sm:px-6 lg:px-8">

        <template x-if="!enabled">
            <div class="flex flex-col items-center justify-center py-32 text-center">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-blue-100/30">
                    <span class="material-symbols-outlined text-4xl">live_tv</span>
                </div>
                <h2 class="mt-6 text-2xl font-bold text-white">Live TV is Disabled</h2>
                <p class="mt-2 max-w-md text-sm text-blue-100/50">This feature is currently turned off by the administrator. Please check back later.</p>
            </div>
        </template>

        <template x-if="enabled">
            <div>
                <section class="relative mb-10 text-center">
                    <h1 class="text-5xl font-extrabold leading-tight tracking-tight sm:text-6xl lg:text-7xl">
                        Live
                        <span class="bg-linear-to-r from-red-400 via-rose-500 to-pink-500 bg-clip-text italic text-transparent pr-2">
                            TV
                        </span>
                    </h1>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-7 text-blue-100/60 sm:text-base lg:text-lg">
                        Sports, News &amp; Entertainment
                    </p>
                    <p class="mt-2 text-xs text-blue-100/40" x-text="channels.length + ' channels'"></p>
                </section>

                {{-- Player --}}
                <template x-if="playing">
                    <div class="mb-8 overflow-hidden rounded-2xl border border-white/10 bg-black shadow-[0_24px_80px_rgba(0,0,0,0.4)]">
                        <div class="relative">
                            <div x-show="streamLoading" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-black/70">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="h-10 w-10 animate-spin rounded-full border-2 border-white/20 border-t-cyan-400"></span>
                                    <p class="text-sm text-blue-100/70" x-text="'Loading ' + activeName + '...'"></p>
                                </div>
                            </div>

                            <video id="live-tv-player" controls autoplay playsinline class="w-full max-h-[70vh] bg-black"></video>

                            <button type="button" x-on:click="stop"
                                class="absolute top-4 right-4 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white/80 backdrop-blur-sm transition hover:bg-black/80 hover:text-white">
                                <span class="material-symbols-outlined text-xl">close</span>
                            </button>

                            <div class="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/80 to-transparent px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                    <span class="text-sm font-semibold text-white" x-text="activeName"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Search --}}
                <div class="mb-8">
                    <div class="relative max-w-md mx-auto sm:mx-0">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-100/40">
                            <span class="material-symbols-outlined text-[20px]">search</span>
                        </span>
                        <input type="text" x-model="search" x-on:input.debounce="filtered = filter()" placeholder="Search..."
                            class="w-full rounded-xl border border-white/10 bg-white/[0.06] py-3 pl-12 pr-4 text-sm text-white placeholder-blue-100/40 backdrop-blur-xl transition focus:border-cyan-400/40 focus:outline-none focus:ring-1 focus:ring-cyan-400/20">
                    </div>
                </div>

                {{-- Category tabs --}}
                <div class="mb-6 flex flex-wrap gap-2">
                    <template x-for="cat in ['All', 'Bangladeshi', 'Sports', 'News', 'Entertainment']" :key="cat">
                        <button type="button"
                            x-on:click="category = cat; filtered = filter()"
                            x-text="cat"
                            :class="category === cat
                                ? 'bg-cyan-400/20 border-cyan-400/40 text-cyan-300'
                                : 'bg-white/[0.06] border-white/10 text-blue-100/60 hover:bg-white/[0.10]'"
                            class="rounded-full border px-4 py-1.5 text-xs font-semibold transition cursor-pointer">
                        </button>
                    </template>
                </div>

                {{-- Grid --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7">
                    <template x-for="ch in filtered" :key="ch.url">
                        <button type="button"
                            x-on:click="play(ch.name, ch.url)"
                            :class="activeUrl === ch.url
                                ? 'border-cyan-400/40 bg-cyan-400/10 shadow-lg shadow-cyan-500/10'
                                : 'border-white/10 bg-white/[0.055] hover:-translate-y-0.5 hover:border-cyan-300/30 hover:bg-white/[0.075]'"
                            class="group relative flex flex-col items-center justify-center rounded-xl border p-4 text-center transition-all duration-200 min-h-[100px] cursor-pointer">
                            <span :class="{
                                    'material-symbols-outlined text-2xl text-orange-300/60': ch.cat === 'Sports',
                                    'material-symbols-outlined text-2xl text-green-300/60': ch.cat === 'News',
                                    'material-symbols-outlined text-2xl text-pink-300/60': ch.cat === 'Entertainment',
                                    'material-symbols-outlined text-2xl text-blue-100/30 group-hover:text-cyan-300/60 transition': ch.cat === 'Bangladeshi'
                                }"
                                x-text="ch.cat === 'Sports' ? 'sports_esports' : (ch.cat === 'News' ? 'newspaper' : (ch.cat === 'Entertainment' ? 'theater_comedy' : 'tv'))"></span>
                            <span class="mt-2 text-xs font-semibold leading-tight text-blue-50/85 group-hover:text-white transition line-clamp-2" x-text="ch.name"></span>
                            <span class="mt-0.5 text-[9px] font-medium uppercase tracking-wider text-blue-100/30" x-text="ch.cat"></span>
                            <template x-if="activeUrl === ch.url">
                                <span class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-cyan-400">
                                    <span class="flex h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                                    Live
                                </span>
                            </template>
                        </button>
                    </template>
                </div>

                <div x-show="filtered.length === 0" class="py-20 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-white/10 bg-white/8 text-blue-100/40">
                        <span class="material-symbols-outlined text-3xl">live_tv</span>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">No channels found</h3>
                    <p class="mt-1 text-sm text-blue-100/50">Try a different search or category.</p>
                </div>
            </div>
        </template>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
        <script>
            function liveTv() {
                return {
                    enabled: {{ Js::from($this->enabled) }},
                    channels: {{ Js::from($this->channels) }},
                    search: '',
                    category: 'All',
                    playing: false,
                    streamLoading: false,
                    activeName: '',
                    activeUrl: '',
                    hlsInstance: null,
                    loadTimeout: null,

                    get filtered() {
                        let result = this.channels;
                        if (this.category !== 'All') {
                            result = result.filter(ch => ch.cat === this.category);
                        }
                        if (this.search) {
                            const q = this.search.toLowerCase();
                            result = result.filter(ch => ch.name.toLowerCase().includes(q));
                        }
                        return result;
                    },

                    play(name, url) {
                        this.streamLoading = true;
                        this.activeName = name;
                        this.activeUrl = url;
                        this.playing = true;
                        this.destroyPlayer();

                        this.$nextTick(() => {
                            const video = document.getElementById('live-tv-player');
                            if (!video) { this.streamLoading = false; return; }

                            if (url.includes('.m3u8')) {
                                if (Hls.isSupported()) {
                                    const hls = new Hls({ enableWorker: true, lowLatencyMode: true });
                                    hls.loadSource(url);
                                    hls.attachMedia(video);
                                    hls.on(Hls.Events.MANIFEST_PARSED, () => {
                                        this.streamLoading = false;
                                        video.play().catch(() => {});
                                    });
                                    hls.on(Hls.Events.ERROR, (event, data) => {
                                        if (data.fatal) this.streamLoading = false;
                                    });
                                    this.hlsInstance = hls;
                                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                    video.src = url;
                                    video.addEventListener('loadedmetadata', () => { this.streamLoading = false; });
                                    video.play().catch(() => {});
                                } else {
                                    this.streamLoading = false;
                                }
                            } else {
                                video.src = url;
                                video.addEventListener('loadedmetadata', () => { this.streamLoading = false; });
                                video.addEventListener('error', () => { this.streamLoading = false; });
                                video.play().catch(() => { this.streamLoading = false; });
                            }
                            this.loadTimeout = setTimeout(() => { this.streamLoading = false; }, 12000);
                        });
                    },

                    stop() {
                        this.playing = false;
                        this.streamLoading = false;
                        this.activeName = '';
                        this.activeUrl = '';
                        this.destroyPlayer();
                    },

                    destroyPlayer() {
                        if (this.loadTimeout) { clearTimeout(this.loadTimeout); this.loadTimeout = null; }
                        if (this.hlsInstance) { this.hlsInstance.destroy(); this.hlsInstance = null; }
                        const video = document.getElementById('live-tv-player');
                        if (video) { video.pause(); video.removeAttribute('src'); video.load(); }
                    },
                };
            }
        </script>
    @endpush
</div>
