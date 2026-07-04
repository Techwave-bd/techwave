<?php

use App\Models\SiteSetting;
use App\Models\ToolCategory;
use App\Models\Vcard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Title('VCard Generator')] class extends Component {
    use WithFileUploads;

    private const FREE_TEMPLATE = 'modern-banner-center';
    private const FREE_WEEKLY_SCAN_LIMIT = 10;

    /*
    |--------------------------------------------------------------------------
    | Builder State
    |--------------------------------------------------------------------------
    */
    public string $openSection = 'appearance';
    public string $openAppearancePanel = 'template';
    public string $basicTab = 'about';
    public string $locationTab = 'url';
    public string $contentTab = 'companies';
    public string $openBasicPanel = 'about';
    public string $openContentPanel = 'companies';
    public bool $showReorderPanel = false;
    public array $previewSectionOrder = ['phones', 'emails', 'sites', 'location', 'companies', 'social'];

    public ?int $editingVcardId = null;
    public ?Vcard $savedVcard = null;
    public array $userVcards = [];

    /*
    |--------------------------------------------------------------------------
    | 1. Appearance
    |--------------------------------------------------------------------------
    */
    public string $template = 'modern-banner-center';
    public string $theme = 'modern-banner-center';
    public string $fontFamily = 'Poppins';
    public string $accentColor = '#06b6d4';
    public string $bgColor = '#0f172a';
    public string $textColor = '#ffffff';
    public string $cardBg = '#1e293b';
    public string $buttonTextColor = '#ffffff';
    public bool $avatarRingEnabled = true;
    public string $avatarRingColor = '#ffffff';
    public int $avatarRingWidth = 4;
    public bool $floatingButtonRingEnabled = true;
    public string $floatingButtonRingColor = '#ffffff';
    public int $floatingButtonRingWidth = 4;
    public string $floatingButtonRingShape = 'circle'; // square | rounded | circle
    public string $floatingButtonPlacement = 'bottom-right'; // top-right | top-left | bottom-right | bottom-left
    public int $floatingButtonBorderRadius = 56;
    public int $avatarBorderRadius = 56;
    public string $fieldBorderColor = '#e2e8f0';
    public int $fieldBorderRadius = 12;
    public int $fieldBorderWidth = 1;
    public string $fieldBorderStyle = 'solid';
    public string $fieldShadow = 'soft'; // none | soft | medium | glass

    /*
    |--------------------------------------------------------------------------
    | 2. Basic Information
    |--------------------------------------------------------------------------
    */
    public string $firstName = '';
    public string $lastName = '';
    public string $designation = '';
    public string $aboutMe = '';

    public $profileImage = null;
    public ?string $profilePreview = null;

    public array $phones = [['type' => 'mobile', 'label' => 'Mobile', 'value' => '', 'icon' => 'call']];

    public array $emails = [['label' => 'Email', 'value' => '', 'icon' => 'mail']];

    public array $sites = [['label' => 'Website', 'value' => '', 'icon' => 'language']];

    public string $street = '';
    public string $city = '';
    public string $state = '';
    public string $zip = '';
    public string $country = '';
    public string $locationLabel = 'Location';
    public string $locationIcon = 'location_on';
    public string $locationSearch = '';
    public string $locationUrl = '';
    public string $latitude = '';
    public string $longitude = '';

    /*
    |--------------------------------------------------------------------------
    | 3. Content
    |--------------------------------------------------------------------------
    */
    public array $companies = [['company_name' => '', 'profession' => '', 'icon' => 'business_center']];

    public string $contactButtonText = 'Save Contact';
    public string $contactButtonPosition = 'top'; // top | floating

    /*
    |--------------------------------------------------------------------------
    | 4. Social Network
    |--------------------------------------------------------------------------
    */
    public array $socialLinks = [];
    public bool $showSocialName = false;
    public bool $showSocialAsCards = false;
    public array $socialIconMode = [];
    public array $socialCustomIcons = [];

    /*
    |--------------------------------------------------------------------------
    | Images / QR / Legacy VCF
    |--------------------------------------------------------------------------
    */
    public $bannerImage = null;
    public ?string $bannerPreview = null;

    public $loadingImage = null;
    public ?string $loadingImagePreview = null;
    public bool $loadingScreenEnabled = false;
    public int $loadingTime = 2;

    public $qrLogo = null;
    public ?string $qrLogoPreview = null;
    public bool $qrHasLogo = false;
    public string $qrLogoMode = 'none'; // custom | none (free users are forced to site logo)
    public ?string $qrSvg = null; // Fast SVG preview; JavaScript converts it to PNG
    public ?string $publicUrl = null;

    public ?string $generatedVcf = null;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->loadUserVcards();

            $editId = request()->query('editVcard');

            if ($editId) {
                $this->loadVcard((int) $editId);
            }
        }
    }

    public function toggleSection(string $section): void
    {
        $this->openSection = $this->openSection === $section ? '' : $section;
    }

    public function toggleReorderPanel(): void
    {
        $this->showReorderPanel = !$this->showReorderPanel;
        $this->normalizePreviewSectionOrder();
    }

    public function getPreviewSectionLabelsProperty(): array
    {
        return [
            'phones' => ['label' => 'Phone Numbers', 'icon' => 'call'],
            'emails' => ['label' => 'Emails', 'icon' => 'mail'],
            'sites' => ['label' => 'Websites', 'icon' => 'language'],
            'location' => ['label' => 'Location', 'icon' => 'location_on'],
            'companies' => ['label' => 'Companies', 'icon' => 'business_center'],
            'social' => ['label' => 'Social Links', 'icon' => 'share'],
        ];
    }

    public array $contactIconOptions = [
        'call' => 'Phone',
        'phone_in_talk' => 'Call active',
        'call_end' => 'End call',
        'dialpad' => 'Dial pad',
        'contacts' => 'Contacts',
        'contact_phone' => 'Contact phone',
        'smartphone' => 'Mobile',
        'phone_iphone' => 'iPhone',
        'mail' => 'Email',
        'alternate_email' => 'At email',
        'mark_email_read' => 'Email read',
        'drafts' => 'Mail open',
        'inbox' => 'Inbox',
        'language' => 'Website',
        'public' => 'Global',
        'link' => 'Link',
        'captive_portal' => 'Portal',
        'travel_explore' => 'Explore web',
        'qr_code_2' => 'QR code',
        'location_on' => 'Location',
        'pin_drop' => 'Pin drop',
        'my_location' => 'My location',
        'near_me' => 'Near me',
        'map' => 'Map',
        'home_pin' => 'Address',
        'business_center' => 'Business',
        'apartment' => 'Company',
        'domain' => 'Office',
        'corporate_fare' => 'Corporate',
        'badge' => 'Work badge',
        'contact_page' => 'Contact page',
        'account_circle' => 'Account',
        'person' => 'Person',
        'support_agent' => 'Support',
        'chat' => 'Chat',
        'storefront' => 'Store',
        'work' => 'Work',
        'payments' => 'Payment',
        'calendar_month' => 'Calendar',
        'schedule' => 'Time',
        'print' => 'Print',
    ];

    public array $socialCustomIconOptions = [
        'share' => 'Share',
        'link' => 'Link',
        'public' => 'Public',
        'person_add' => 'Connect',
        'chat' => 'Chat',
        'forum' => 'Forum',
        'alternate_email' => 'At sign',
        'send' => 'Send',
        'groups' => 'Community',
        'diversity_3' => 'Network',
        'favorite' => 'Favorite',
        'star' => 'Star',
        'thumb_up' => 'Like',
        'verified' => 'Verified',
        'photo_camera' => 'Photo',
        'play_circle' => 'Video',
        'live_tv' => 'Live TV',
        'music_note' => 'Music',
        'newspaper' => 'News',
        'article' => 'Article',
        'rss_feed' => 'RSS',
        'shopping_bag' => 'Shop',
        'work' => 'Work',
        'code' => 'Code',
        'terminal' => 'Terminal',
        'rocket_launch' => 'Launch',
        'campaign' => 'Campaign',
        'language' => 'Website',
        'location_on' => 'Location',
    ];

    public function socialUsesCustomIcon(string $platform): bool
    {
        return ($this->socialIconMode[$platform] ?? 'brand') === 'custom';
    }

    public function socialCustomIcon(string $platform): string
    {
        $icon = $this->socialCustomIcons[$platform] ?? ('brand:' . $platform);

        if (str_starts_with($icon, 'brand:')) {
            return 'share';
        }

        return array_key_exists($icon, $this->socialCustomIconOptions) ? $icon : 'share';
    }

    public function socialUsesBrandIcon(string $platform): bool
    {
        return str_starts_with((string) ($this->socialCustomIcons[$platform] ?? ('brand:' . $platform)), 'brand:');
    }

    public function socialDisplayBrand(string $platform): string
    {
        $value = (string) ($this->socialCustomIcons[$platform] ?? ('brand:' . $platform));

        if (!str_starts_with($value, 'brand:')) {
            return $platform;
        }

        $brand = substr($value, 6);

        return isset($this->socialOptions[$brand]) ? $brand : $platform;
    }

    public function socialDisplayLabel(string $platform): string
    {
        if ($this->socialUsesBrandIcon($platform)) {
            $brand = $this->socialDisplayBrand($platform);

            return $this->socialOptions[$brand]['label'] ?? ucfirst($brand);
        }

        return $this->socialOptions[$platform]['label'] ?? ucfirst($platform);
    }

    public function normalizePreviewSectionOrder(): array
    {
        $allowed = array_keys($this->previewSectionLabels);
        $current = array_values(array_filter($this->previewSectionOrder, fn($section) => in_array($section, $allowed, true)));

        foreach ($allowed as $section) {
            if (!in_array($section, $current, true)) {
                $current[] = $section;
            }
        }

        $this->previewSectionOrder = $current;

        return $this->previewSectionOrder;
    }

    public function movePreviewSectionUp(string $section): void
    {
        $order = $this->normalizePreviewSectionOrder();
        $index = array_search($section, $order, true);

        if ($index === false || $index === 0) {
            return;
        }

        [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]];
        $this->previewSectionOrder = array_values($order);
    }

    public function movePreviewSectionDown(string $section): void
    {
        $order = $this->normalizePreviewSectionOrder();
        $index = array_search($section, $order, true);

        if ($index === false || $index >= count($order) - 1) {
            return;
        }

        [$order[$index + 1], $order[$index]] = [$order[$index], $order[$index + 1]];
        $this->previewSectionOrder = array_values($order);
    }

    public function updatePreviewSectionOrder(array $order): void
    {
        $allowed = array_keys($this->previewSectionLabels);
        $newOrder = array_values(array_filter($order, fn($section) => in_array($section, $allowed, true)));

        foreach ($allowed as $section) {
            if (!in_array($section, $newOrder, true)) {
                $newOrder[] = $section;
            }
        }

        $this->previewSectionOrder = $newOrder;
    }

    public function toggleAppearancePanel(string $panel): void
    {
        if (!$this->isPremium && $panel !== 'template') {
            $this->addError('appearancePremium', 'This customization is premium. Upgrade to unlock it.');

            return;
        }

        $this->resetErrorBag('appearancePremium');
        $this->openAppearancePanel = $this->openAppearancePanel === $panel ? '' : $panel;
    }

    public function toggleBasicPanel(string $panel): void
    {
        $this->openBasicPanel = $this->openBasicPanel === $panel ? '' : $panel;
        $this->basicTab = $panel;
    }

    public function toggleContentPanel(string $panel): void
    {
        $this->openContentPanel = $this->openContentPanel === $panel ? '' : $panel;
        $this->contentTab = $panel;
    }

    public function getIsPremiumProperty(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $category = ToolCategory::query()->where('slug', 'business')->first();

        if (!$category) {
            return false;
        }

        return auth()->user()?->hasActiveToolSubscription($category) ?? false;
    }

    public function getFreeWeeklyScanLimitProperty(): int
    {
        return self::FREE_WEEKLY_SCAN_LIMIT;
    }

    public function getFreeWeeklyScansRemainingProperty(): int
    {
        if (!$this->savedVcard) {
            return self::FREE_WEEKLY_SCAN_LIMIT;
        }

        $weeklyScans = $this->savedVcard
            ->scans()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return max(0, self::FREE_WEEKLY_SCAN_LIMIT - (int) $weeklyScans);
    }

    private function applyFreePlanRestrictions(): void
    {
        if ($this->isPremium) {
            return;
        }

        $freeTemplate = $this->templates[self::FREE_TEMPLATE] ?? [];

        $this->template = self::FREE_TEMPLATE;
        $this->theme = self::FREE_TEMPLATE;
        $this->accentColor = $freeTemplate['accent'] ?? '#06b6d4';
        $this->bgColor = $freeTemplate['bg'] ?? '#0f172a';
        $this->textColor = $freeTemplate['text'] ?? '#111827';
        $this->cardBg = $freeTemplate['card_bg'] ?? '#ffffff';
        $this->fontFamily = 'Poppins';
        $this->buttonTextColor = '#ffffff';
        $this->avatarRingEnabled = true;
        $this->avatarRingColor = '#ffffff';
        $this->avatarRingWidth = 4;
        $this->floatingButtonRingEnabled = true;
        $this->floatingButtonRingColor = '#ffffff';
        $this->floatingButtonRingWidth = 4;
        $this->floatingButtonRingShape = 'circle';
        $this->floatingButtonPlacement = 'bottom-right';
        $this->floatingButtonBorderRadius = 56;
        $this->avatarBorderRadius = 56;
        $this->fieldBorderColor = '#e2e8f0';
        $this->fieldBorderRadius = 12;
        $this->fieldBorderWidth = 1;
        $this->fieldBorderStyle = 'solid';
        $this->fieldShadow = 'soft';
        $this->loadingScreenEnabled = false;
        $this->loadingImage = null;
        $this->loadingImagePreview = null;
        $this->qrLogo = null;
        $this->qrLogoPreview = null;
        $this->qrHasLogo = true;
        $this->qrLogoMode = 'site';
    }

    public function getTemplatesProperty(): array
    {
        return [
            'modern-banner-center' => [
                'label' => 'Aurora Profile',
                'accent' => '#06b6d4',
                'bg' => '#0f172a',
                'text' => '#111827',
                'card_bg' => '#ffffff',
                'has_banner' => true,
                'avatar_position' => 'center-over-banner',
            ],
            'modern-banner-left' => [
                'label' => 'Nebula Side',
                'accent' => '#8b5cf6',
                'bg' => '#0f172a',
                'text' => '#111827',
                'card_bg' => '#ffffff',
                'has_banner' => true,
                'avatar_position' => 'left-over-banner',
            ],
            'clean-no-banner' => [
                'label' => 'Pearl Minimal',
                'accent' => '#2563eb',
                'bg' => '#f8fafc',
                'text' => '#111827',
                'card_bg' => '#ffffff',
                'has_banner' => false,
                'avatar_position' => 'top-center',
            ],
            'minimal-left' => [
                'label' => 'Slate Signature',
                'accent' => '#64748b',
                'bg' => '#f1f5f9',
                'text' => '#111827',
                'card_bg' => '#ffffff',
                'has_banner' => false,
                'avatar_position' => 'left-inline',
            ],
            'dark-banner' => [
                'label' => 'Midnight Luxe',
                'accent' => '#d4a762',
                'bg' => '#0c0a09',
                'text' => '#f8fafc',
                'card_bg' => '#1c1917',
                'has_banner' => true,
                'avatar_position' => 'center-over-banner',
            ],
            'user-image-banner' => [
                'label' => 'Hero Portrait',
                'accent' => '#14b8a6',
                'bg' => '#042f2e',
                'text' => '#f8fafc',
                'card_bg' => '#0f172a',
                'has_banner' => true,
                'avatar_position' => 'banner-profile-cover',
                'use_profile_as_banner' => true,
                'effect' => 'hero-portrait',
            ],
            'creative-square' => [
                'label' => 'Pixel Pop',
                'accent' => '#ec4899',
                'bg' => '#1e1b4b',
                'text' => '#111827',
                'card_bg' => '#ffffff',
                'has_banner' => false,
                'avatar_position' => 'square-top',
            ],
            'water-glass-card' => [
                'label' => 'Aqua Fade',
                'accent' => '#67e8f9',
                'bg' => '#082f49',
                'text' => '#f8fafc',
                'card_bg' => '#ffffff',
                'has_banner' => true,
                'avatar_position' => 'center-over-banner',
                'effect' => 'water-glass',
                'fade_effect' => true,
            ],
            'glassmorphism-card' => [
                'label' => 'Frosted Glow',
                'accent' => '#c084fc',
                'bg' => '#111827',
                'text' => '#f8fafc',
                'card_bg' => '#ffffff',
                'has_banner' => false,
                'avatar_position' => 'top-center',
                'effect' => 'glassmorphism',
            ],
        ];
    }

    public function getFontsProperty(): array
    {
        return [
            'Poppins' => 'Poppins',
            'Inter' => 'Inter',
            'Roboto' => 'Roboto',
            'Montserrat' => 'Montserrat',
            'Playfair Display' => 'Playfair Display',
            'Merriweather' => 'Merriweather',
            'Raleway' => 'Raleway',
            'Lora' => 'Lora',
            'Oswald' => 'Oswald',
            'Source Sans 3' => 'Source Sans 3',
            'Nunito' => 'Nunito',
            'DM Sans' => 'DM Sans',
            'Plus Jakarta Sans' => 'Plus Jakarta Sans',
            'Space Grotesk' => 'Space Grotesk',
        ];
    }

    public function getPhoneTypesProperty(): array
    {
        return [
            'mobile' => ['label' => 'Mobile Phone', 'icon' => 'phone_iphone'],
            'home' => ['label' => 'Home', 'icon' => 'home'],
            'work' => ['label' => 'Work', 'icon' => 'business_center'],
            'other' => ['label' => 'Other', 'icon' => 'call'],
        ];
    }

    public function getSocialOptionsProperty(): array
    {
        return [
            'facebook' => ['label' => 'Facebook', 'icon_slug' => 'facebook', 'color' => '#1877F2'],
            'instagram' => ['label' => 'Instagram', 'icon_slug' => 'instagram', 'color' => '#E4405F'],
            'linkedin' => ['label' => 'LinkedIn', 'icon_slug' => 'linkedin', 'color' => '#0A66C2'],
            'twitter' => ['label' => 'X / Twitter', 'icon_slug' => 'x', 'color' => '#111827'],
            'threads' => ['label' => 'Threads', 'icon_slug' => 'threads', 'color' => '#111827'],
            'youtube' => ['label' => 'YouTube', 'icon_slug' => 'youtube', 'color' => '#FF0000'],
            'tiktok' => ['label' => 'TikTok', 'icon_slug' => 'tiktok', 'color' => '#111827'],
            'snapchat' => ['label' => 'Snapchat', 'icon_slug' => 'snapchat', 'color' => '#FFFC00'],
            'pinterest' => ['label' => 'Pinterest', 'icon_slug' => 'pinterest', 'color' => '#BD081C'],
            'reddit' => ['label' => 'Reddit', 'icon_slug' => 'reddit', 'color' => '#FF4500'],
            'tumblr' => ['label' => 'Tumblr', 'icon_slug' => 'tumblr', 'color' => '#36465D'],
            'quora' => ['label' => 'Quora', 'icon_slug' => 'quora', 'color' => '#B92B27'],
            'medium' => ['label' => 'Medium', 'icon_slug' => 'medium', 'color' => '#111827'],
            'mastodon' => ['label' => 'Mastodon', 'icon_slug' => 'mastodon', 'color' => '#6364FF'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon_slug' => 'whatsapp', 'color' => '#25D366'],
            'telegram' => ['label' => 'Telegram', 'icon_slug' => 'telegram', 'color' => '#26A5E4'],
            'messenger' => ['label' => 'Messenger', 'icon_slug' => 'messenger', 'color' => '#00B2FF'],
            'discord' => ['label' => 'Discord', 'icon_slug' => 'discord', 'color' => '#5865F2'],
            'signal' => ['label' => 'Signal', 'icon_slug' => 'signal', 'color' => '#3A76F0'],
            'line' => ['label' => 'LINE', 'icon_slug' => 'line', 'color' => '#00C300'],
            'wechat' => ['label' => 'WeChat', 'icon_slug' => 'wechat', 'color' => '#07C160'],
            'skype' => ['label' => 'Skype', 'icon_slug' => 'skype', 'color' => '#00AFF0'],
            'github' => ['label' => 'GitHub', 'icon_slug' => 'github', 'color' => '#111827'],
            'gitlab' => ['label' => 'GitLab', 'icon_slug' => 'gitlab', 'color' => '#FC6D26'],
            'stackoverflow' => ['label' => 'Stack Overflow', 'icon_slug' => 'stackoverflow', 'color' => '#F58025'],
            'behance' => ['label' => 'Behance', 'icon_slug' => 'behance', 'color' => '#1769FF'],
            'dribbble' => ['label' => 'Dribbble', 'icon_slug' => 'dribbble', 'color' => '#EA4C89'],
            'twitch' => ['label' => 'Twitch', 'icon_slug' => 'twitch', 'color' => '#9146FF'],
            'spotify' => ['label' => 'Spotify', 'icon_slug' => 'spotify', 'color' => '#1DB954'],
            'soundcloud' => ['label' => 'SoundCloud', 'icon_slug' => 'soundcloud', 'color' => '#FF5500'],
            'vimeo' => ['label' => 'Vimeo', 'icon_slug' => 'vimeo', 'color' => '#1AB7EA'],
            'wordpress' => ['label' => 'WordPress', 'icon_slug' => 'wordpress', 'color' => '#21759B'],
            'blogger' => ['label' => 'Blogger', 'icon_slug' => 'blogger', 'color' => '#FF5722'],
            'slack' => ['label' => 'Slack', 'icon_slug' => 'slack', 'color' => '#4A154B'],
            'calendly' => ['label' => 'Calendly', 'icon_slug' => 'calendly', 'color' => '#006BFF'],
        ];
    }

    public function socialIconUrl(string $platform): string
    {
        $social = $this->socialOptions[$platform] ?? [];
        $slug = $social['icon_slug'] ?? $platform;
        $color = ltrim((string) ($social['color'] ?? '#111827'), '#');

        $fixedBrandIcons = [
            'linkedin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#0A66C2" d="M22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.226.792 24 1.771 24h20.451C23.2 24 24 23.226 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/><path fill="#fff" d="M3.555 9h3.564v11.452H3.555V9zm1.782-5.694a2.063 2.063 0 1 1 0 4.126 2.063 2.063 0 0 1 0-4.126zM9.351 9h3.414v1.561h.049c.476-.9 1.637-1.852 3.368-1.852 3.602 0 4.267 2.371 4.267 5.455v6.288h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9z"/></svg>',
            'skype' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10.5" fill="#00AFF0"/><circle cx="7.1" cy="6.8" r="4.4" fill="#00AFF0" opacity=".72"/><circle cx="17" cy="17.2" r="4.2" fill="#00AFF0" opacity=".72"/><path fill="#fff" d="M12.27 18.32c-3.04 0-5.03-1.45-5.03-2.83 0-.73.55-1.25 1.28-1.25 1.64 0 1.21 2.35 3.63 2.35 1.24 0 1.93-.67 1.93-1.35 0-.41-.2-.86-.98-1.05l-2.58-.64c-2.08-.52-2.46-1.65-2.46-2.71 0-2.2 2.08-3.03 4.03-3.03 1.8 0 4.39.99 4.39 2.31 0 .75-.65 1.18-1.38 1.18-1.4 0-1.14-1.93-3.15-1.93-1.11 0-1.72.5-1.72 1.22s.88.95 1.65 1.12l1.91.43c2.1.47 2.63 1.7 2.63 2.86 0 1.79-1.38 3.32-4.15 3.32z"/></svg>',
            'slack' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#36C5F0" d="M8.04 14.51a2.2 2.2 0 1 1-2.2-2.2h2.2v2.2zm1.1 0a2.2 2.2 0 1 1 4.4 0v5.5a2.2 2.2 0 1 1-4.4 0v-5.5z"/><path fill="#2EB67D" d="M9.14 8.04a2.2 2.2 0 1 1 2.2-2.2v2.2h-2.2zm0 1.1a2.2 2.2 0 1 1 0 4.4h-5.5a2.2 2.2 0 1 1 0-4.4h5.5z"/><path fill="#ECB22E" d="M15.96 9.14a2.2 2.2 0 1 1 2.2 2.2h-2.2v-2.2zm-1.1 0a2.2 2.2 0 1 1-4.4 0v-5.5a2.2 2.2 0 1 1 4.4 0v5.5z"/><path fill="#E01E5A" d="M14.86 15.96a2.2 2.2 0 1 1-2.2 2.2v-2.2h2.2zm0-1.1a2.2 2.2 0 1 1 0-4.4h5.5a2.2 2.2 0 1 1 0 4.4h-5.5z"/></svg>',
        ];

        if (isset($fixedBrandIcons[$platform])) {
            return 'data:image/svg+xml;utf8,' . rawurlencode($fixedBrandIcons[$platform]);
        }

        return 'https://cdn.simpleicons.org/' . $slug . '/' . $color;
    }

    public function selectTemplate(string $slug): void
    {
        $template = $this->templates[$slug] ?? null;

        if (!$template) {
            return;
        }

        if (!$this->isPremium && $slug !== self::FREE_TEMPLATE) {
            $this->addError('template', 'This template is premium. Upgrade to unlock it.');

            return;
        }

        $this->resetErrorBag('template');
        $this->template = $slug;
        $this->theme = $slug;
        $this->accentColor = $template['accent'];
        $this->bgColor = $template['bg'];
        $this->textColor = $template['text'];
        $this->cardBg = $template['card_bg'];
    }

    public function applyColorPreset(string $accent, string $cardBg, string $textColor): void
    {
        $this->accentColor = $accent;
        $this->cardBg = $cardBg;
        $this->textColor = $textColor;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName) ?: 'Your Name';
    }

    public function getInitials(): string
    {
        $first = strtoupper(substr($this->firstName, 0, 1) ?: '');
        $last = strtoupper(substr($this->lastName, 0, 1) ?: '');

        return $first . $last ?: '?';
    }

    public function getPrimaryPhoneProperty(): string
    {
        $phone = collect($this->phones)->first(fn($item) => filled($item['value'] ?? ''));

        return $phone['value'] ?? '';
    }

    public function getPrimaryEmailProperty(): string
    {
        $email = collect($this->emails)->first(fn($item) => filled($item['value'] ?? ''));

        return $email['value'] ?? '';
    }

    public function getPrimarySiteProperty(): string
    {
        $site = collect($this->sites)->first(fn($item) => filled($item['value'] ?? ''));

        return $site['value'] ?? '';
    }

    public function getPrimaryCompanyProperty(): array
    {
        return collect($this->companies)->first(fn($item) => filled($item['company_name'] ?? '') || filled($item['profession'] ?? '')) ?? ['company_name' => '', 'profession' => ''];
    }

    public function addPhone(): void
    {
        $this->phones[] = ['type' => 'mobile', 'label' => 'Mobile', 'value' => '', 'icon' => 'call'];
    }

    public function removePhone(int $index): void
    {
        unset($this->phones[$index]);
        $this->phones = array_values($this->phones ?: [['type' => 'mobile', 'label' => 'Mobile', 'value' => '', 'icon' => 'call']]);
    }

    public function addEmail(): void
    {
        $this->emails[] = ['label' => 'Email', 'value' => '', 'icon' => 'mail'];
    }

    public function removeEmail(int $index): void
    {
        unset($this->emails[$index]);
        $this->emails = array_values($this->emails ?: [['label' => 'Email', 'value' => '', 'icon' => 'mail']]);
    }

    public function addSite(): void
    {
        $this->sites[] = ['label' => 'Website', 'value' => '', 'icon' => 'language'];
    }

    public function removeSite(int $index): void
    {
        unset($this->sites[$index]);
        $this->sites = array_values($this->sites ?: [['label' => 'Website', 'value' => '', 'icon' => 'language']]);
    }

    public function addCompany(): void
    {
        $this->companies[] = ['company_name' => '', 'profession' => '', 'icon' => 'business_center'];
    }

    public function removeCompany(int $index): void
    {
        unset($this->companies[$index]);
        $this->companies = array_values($this->companies ?: [['company_name' => '', 'profession' => '', 'icon' => 'business_center']]);
    }

    public function addSocial(string $platform): void
    {
        if (!isset($this->socialOptions[$platform])) {
            return;
        }

        if (!array_key_exists($platform, $this->socialLinks)) {
            $this->socialLinks[$platform] = '';
        }

        $this->socialIconMode[$platform] = 'custom';
        $this->socialCustomIcons[$platform] ??= 'brand:' . $platform;
    }

    public function removeSocial(string $platform): void
    {
        unset($this->socialLinks[$platform], $this->socialIconMode[$platform], $this->socialCustomIcons[$platform]);
    }

    public function updatedProfileImage(): void
    {
        $this->validate([
            'profileImage' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $this->profilePreview = $this->profileImage->temporaryUrl();
    }

    public function updatedBannerImage(): void
    {
        $this->validate([
            'bannerImage' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $this->bannerPreview = $this->bannerImage->temporaryUrl();
    }

    public function updatedLoadingImage(): void
    {
        $this->validate([
            'loadingImage' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $this->loadingImagePreview = $this->loadingImage->temporaryUrl();
        $this->loadingScreenEnabled = true;
    }

    public function updatedQrLogo(): void
    {
        if (!$this->isPremium) {
            $this->qrLogo = null;
            $this->qrLogoPreview = null;
            $this->qrLogoMode = 'site';
            $this->qrHasLogo = true;
            $this->addError('qrLogo', 'Custom QR logo is available for premium users only.');

            return;
        }

        $this->validate([
            'qrLogo' => ['image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $this->qrLogoMode = 'custom';
        $this->qrHasLogo = true;
        $this->qrLogoPreview = $this->qrLogo->temporaryUrl();

        if ($this->savedVcard) {
            $this->qrSvg = $this->buildQrSvg($this->savedVcard);
        }
    }

    public function updatedQrLogoMode(): void
    {
        if (!$this->isPremium) {
            $this->qrLogoMode = 'site';
            $this->qrHasLogo = true;
        } else {
            if (!in_array($this->qrLogoMode, ['custom', 'none'], true)) {
                $this->qrLogoMode = 'none';
            }

            $this->qrHasLogo = $this->qrLogoMode === 'custom';
        }

        if ($this->savedVcard) {
            $this->qrSvg = $this->buildQrSvg($this->savedVcard);
        }
    }

    public function removeProfile(): void
    {
        $this->profileImage = null;
        $this->profilePreview = null;
    }

    public function removeBanner(): void
    {
        $this->bannerImage = null;
        $this->bannerPreview = null;
    }

    public function removeLoadingImage(): void
    {
        $this->loadingImage = null;
        $this->loadingImagePreview = null;
    }

    public function removeQrLogo(): void
    {
        $this->qrLogo = null;
        $this->qrLogoPreview = null;
        $this->qrLogoMode = $this->isPremium ? 'none' : 'site';
        $this->qrHasLogo = !$this->isPremium;

        if ($this->savedVcard) {
            $this->qrSvg = $this->buildQrSvg($this->savedVcard);
        }
    }

    public function loadUserVcards(): void
    {
        if (!auth()->check()) {
            $this->userVcards = [];

            return;
        }

        $query = Vcard::query()
            ->where('user_id', auth()->id())
            ->withCount('scans')
            ->latest();

        if (!$this->isPremium) {
            $query->limit(1);
        }

        $this->userVcards = $query
            ->get()
            ->map(
                fn(Vcard $vcard): array => [
                    'id' => $vcard->id,
                    'name' => $vcard->name ?: $vcard->full_name,
                    'slug' => $vcard->slug,
                    'is_active' => (bool) $vcard->is_active,
                    'scans_count' => (int) ($vcard->scans_count ?? 0),
                    'weekly_scans' => $vcard
                        ->scans()
                        ->where('created_at', '>=', now()->startOfWeek())
                        ->count(),
                ],
            )
            ->toArray();
    }

    public function saveVcard(): void
    {
        if (!auth()->check()) {
            $this->addError('vcard', 'Please login to save your vCard.');

            return;
        }

        if (!$this->isPremium) {
            $existingCount = Vcard::query()
                ->where('user_id', auth()->id())
                ->when($this->editingVcardId, fn($q) => $q->where('id', '!=', $this->editingVcardId))
                ->count();

            if ($existingCount >= 1) {
                $this->addError('vcard', 'Free users can create only one vCard.');

                return;
            }

            $this->applyFreePlanRestrictions();
        }

        $socialRules = [];
        $socialMessages = [];
        $socialAttributes = [];

        foreach ($this->socialLinks as $platform => $url) {
            $social = $this->socialOptions[$platform] ?? ['label' => ucfirst((string) $platform)];
            $label = $social['label'] ?? ucfirst((string) $platform);
            $field = 'socialLinks.' . $platform;

            $socialRules[$field] = ['required', 'url', 'max:255'];
            $socialMessages[$field . '.required'] = $label . ' URL is required. Remove this social icon if you do not want to show it.';
            $socialMessages[$field . '.url'] = 'Please enter a valid ' . $label . ' URL, including https://';
            $socialMessages[$field . '.max'] = $label . ' URL must not be longer than 255 characters.';
            $socialAttributes[$field] = $label . ' URL';
        }

        $this->validate(
            [
                'firstName' => ['nullable', 'string', 'max:100'],
                'lastName' => ['nullable', 'string', 'max:100'],
                'designation' => ['nullable', 'string', 'max:150'],
                'aboutMe' => ['nullable', 'string', 'max:1000'],
                'phones.*.type' => ['nullable', 'string', 'max:30'],
                'phones.*.label' => ['nullable', 'string', 'max:80'],
                'phones.*.value' => ['nullable', 'string', 'max:50'],
                'phones.*.icon' => ['nullable', 'string', 'max:50'],
                'emails.*.label' => ['nullable', 'string', 'max:80'],
                'emails.*.value' => ['nullable', 'email', 'max:150'],
                'emails.*.icon' => ['nullable', 'string', 'max:50'],
                'sites.*.label' => ['nullable', 'string', 'max:80'],
                'sites.*.value' => ['nullable', 'url', 'max:255'],
                'sites.*.icon' => ['nullable', 'string', 'max:50'],
                'street' => ['nullable', 'string', 'max:180'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'zip' => ['nullable', 'string', 'max:50'],
                'country' => ['nullable', 'string', 'max:100'],
                'locationLabel' => ['nullable', 'string', 'max:80'],
                'locationIcon' => ['nullable', 'string', 'max:50'],
                'locationSearch' => ['nullable', 'string', 'max:255'],
                'locationUrl' => ['nullable', 'url', 'max:500'],
                'latitude' => ['nullable', 'string', 'max:60'],
                'longitude' => ['nullable', 'string', 'max:60'],
                'companies.*.company_name' => ['nullable', 'string', 'max:160'],
                'companies.*.profession' => ['nullable', 'string', 'max:160'],
                'companies.*.icon' => ['nullable', 'string', 'max:50'],
                'socialIconMode.*' => ['nullable', 'in:brand,custom'],
                'socialCustomIcons.*' => ['nullable', 'string', 'max:50'],
                'avatarRingColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'avatarRingWidth' => ['nullable', 'integer', 'min:0', 'max:12'],
                'fieldBorderWidth' => ['nullable', 'integer', 'min:0', 'max:10'],
                'floatingButtonRingColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'floatingButtonRingWidth' => ['nullable', 'integer', 'min:0', 'max:12'],
                'floatingButtonRingShape' => ['nullable', 'in:square,rounded,circle'],
                'floatingButtonPlacement' => ['nullable', 'in:top-right,top-left,bottom-right,bottom-left'],
                'profileImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'bannerImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'loadingImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
                'loadingTime' => ['nullable', 'integer', 'min:1', 'max:10'],
                'qrLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
                ...$socialRules,
            ],
            $socialMessages,
            $socialAttributes,
        );

        if (!$this->hasMinimumVcardData()) {
            $this->addError('vcard', 'Please add at least one detail before saving your vCard.');

            return;
        }

        $vcard = $this->editingVcardId
            ? Vcard::query()
                ->where('user_id', auth()->id())
                ->findOrFail($this->editingVcardId)
            : new Vcard([
                'user_id' => auth()->id(),
                'slug' => $this->makeUniqueSlug(),
            ]);

        $profilePath = $vcard->profile_path ?? null;
        $bannerPath = $vcard->banner_path ?? null;
        $qrLogoPath = $vcard->qr_logo_path ?? null;
        $loadingPath = $vcard->loading_path ?? null;

        if ($this->profileImage) {
            $profilePath = $this->profileImage->store('vcards/profiles', 'public');
        }

        if ($this->bannerImage) {
            $bannerPath = $this->bannerImage->store('vcards/banners', 'public');
        }

        if ($this->loadingImage) {
            $loadingPath = $this->loadingImage->store('vcards/loading', 'public');
        }

        if ($this->isPremium && $this->qrLogoMode === 'custom' && $this->qrLogo) {
            $qrLogoPath = $this->qrLogo->store('vcards/qr-logos', 'public');
        }

        if ($this->isPremium && $this->qrLogoMode !== 'custom') {
            $qrLogoPath = null;
        }

        $primaryCompany = $this->primaryCompany;

        $payload = [
            'name' => $this->getFullName(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company' => $primaryCompany['company_name'] ?? '',
            'job_title' => $this->designation ?: $primaryCompany['profession'] ?? '',
            'email' => $this->primaryEmail,
            'phone_work' => collect($this->phones)->firstWhere('type', 'work')['value'] ?? '',
            'phone_mobile' => $this->primaryPhone,
            'website' => $this->primarySite,
            'note' => $this->aboutMe,
            'theme' => $this->theme,
            'font_family' => $this->fontFamily,
            'accent_color' => $this->accentColor,
            'bg_color' => $this->bgColor,
            'text_color' => $this->textColor,
            'card_bg' => $this->cardBg,
            'card_style' => $this->template,
            'profile_path' => $profilePath,
            'banner_path' => $bannerPath,
            'qr_logo_path' => $this->isPremium ? $qrLogoPath : null,
            'qr_has_logo' => $this->isPremium ? $this->qrLogoMode !== 'none' : true,
            'is_active' => true,
        ];

        $jsonPayload = [
            'template' => $this->template,
            'phones' => $this->cleanRows($this->phones, ['type', 'label', 'value', 'icon']),
            'emails' => $this->cleanRows($this->emails, ['label', 'value', 'icon']),
            'sites' => $this->cleanRows($this->sites, ['label', 'value', 'icon']),
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'location_input_type' => $this->locationTab,
            'location_label' => $this->locationLabel,
            'location_icon' => $this->locationIcon,
            'location_search' => $this->locationSearch,
            'location_url' => $this->locationUrl,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'companies' => $this->cleanRows($this->companies, ['company_name', 'profession', 'icon']),
            'social_links' => array_filter($this->socialLinks),
            'show_social_name' => $this->showSocialName,
            'show_social_as_cards' => $this->showSocialAsCards,
            'social_icon_mode' => array_filter($this->socialIconMode),
            'social_custom_icons' => array_filter($this->socialCustomIcons),
            'loading_path' => $this->isPremium ? $loadingPath : null,
            'loading_screen_enabled' => $this->isPremium ? $this->loadingScreenEnabled : false,
            'loading_time' => $this->isPremium ? $this->loadingTime : 2,
            'qr_logo_mode' => $this->isPremium ? $this->qrLogoMode : 'site',
            'preview_section_order' => $this->previewSectionOrder,
            'designation' => $this->designation,
            'about_me' => $this->aboutMe,
            'contact_button_text' => $this->contactButtonText,
            'contact_button_position' => $this->contactButtonPosition,
            'button_text_color' => $this->buttonTextColor,
            'avatar_ring_enabled' => $this->avatarRingEnabled,
            'avatar_ring_color' => $this->avatarRingColor,
            'avatar_ring_width' => $this->avatarRingWidth,
            'floating_button_ring_enabled' => $this->floatingButtonRingEnabled,
            'floating_button_ring_color' => $this->floatingButtonRingColor,
            'floating_button_ring_width' => $this->floatingButtonRingWidth,
            'floating_button_ring_shape' => $this->floatingButtonRingShape,
            'floating_button_placement' => $this->floatingButtonPlacement,
            'floating_button_border_radius' => $this->floatingButtonBorderRadius,
            'avatar_border_radius' => $this->avatarBorderRadius,
            'field_border_color' => $this->fieldBorderColor,
            'field_border_radius' => $this->fieldBorderRadius,
            'field_border_width' => $this->fieldBorderWidth,
            'field_border_style' => $this->fieldBorderStyle,
            'field_shadow' => $this->fieldShadow,
        ];

        foreach ($jsonPayload as $column => $value) {
            if (Schema::hasColumn('vcards', $column)) {
                $payload[$column] = $value;
            }
        }

        if (Schema::hasColumn('vcards', 'facebook')) {
            $payload['facebook'] = $this->socialLinks['facebook'] ?? '';
        }
        if (Schema::hasColumn('vcards', 'linkedin')) {
            $payload['linkedin'] = $this->socialLinks['linkedin'] ?? '';
        }
        if (Schema::hasColumn('vcards', 'twitter')) {
            $payload['twitter'] = $this->socialLinks['twitter'] ?? '';
        }
        if (Schema::hasColumn('vcards', 'instagram')) {
            $payload['instagram'] = $this->socialLinks['instagram'] ?? '';
        }

        try {
            $vcard->forceFill($payload);
            $vcard->save();

            $this->editingVcardId = $vcard->id;
            $this->savedVcard = $vcard->fresh();
            $this->publicUrl = $this->publicVcardUrl($this->savedVcard);
            $this->qrSvg = $this->buildQrSvg($this->savedVcard);

            $this->loadUserVcards();
            $this->resetErrorBag('vcard');

            session()->flash('vcard_saved', 'vCard saved successfully.');
        } catch (\Throwable $e) {
            $this->addError('vcard', 'Save failed: ' . $e->getMessage());
        }
    }

    public function deleteVcard(int $id): void
    {
        $vcard = Vcard::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $vcard->delete();

        $this->resetForm();
        $this->loadUserVcards();

        session()->flash('vcard_deleted', 'vCard deleted successfully.');
    }

    private function resetForm(): void
    {
        $this->editingVcardId = null;
        $this->savedVcard = null;
        $this->publicUrl = null;
        $this->qrSvg = null;
        $this->generatedVcf = null;

        $this->firstName = '';
        $this->lastName = '';
        $this->designation = '';
        $this->aboutMe = '';
        $this->profileImage = null;
        $this->profilePreview = null;
        $this->bannerImage = null;
        $this->bannerPreview = null;
        $this->loadingImage = null;
        $this->loadingImagePreview = null;
        $this->qrLogo = null;
        $this->qrLogoPreview = null;

        $this->phones = [['type' => 'mobile', 'label' => 'Mobile', 'value' => '', 'icon' => 'call']];
        $this->emails = [['label' => 'Email', 'value' => '', 'icon' => 'mail']];
        $this->sites = [['label' => 'Website', 'value' => '', 'icon' => 'language']];
        $this->companies = [['company_name' => '', 'profession' => '', 'icon' => 'business_center']];
        $this->socialLinks = [];
        $this->socialIconMode = [];
        $this->socialCustomIcons = [];

        $this->street = '';
        $this->city = '';
        $this->state = '';
        $this->zip = '';
        $this->country = '';
        $this->locationUrl = '';
        $this->latitude = '';
        $this->longitude = '';
        $this->locationSearch = '';

        $this->template = self::FREE_TEMPLATE;
        $this->theme = self::FREE_TEMPLATE;
        $this->fontFamily = 'Poppins';
        $this->accentColor = '#06b6d4';
        $this->bgColor = '#0f172a';
        $this->textColor = '#ffffff';
        $this->cardBg = '#1e293b';
        $this->buttonTextColor = '#ffffff';
        $this->contactButtonText = 'Save Contact';
        $this->contactButtonPosition = 'top';
        $this->avatarRingEnabled = true;
        $this->avatarRingColor = '#ffffff';
        $this->avatarRingWidth = 4;
        $this->floatingButtonRingEnabled = true;
        $this->floatingButtonRingColor = '#ffffff';
        $this->floatingButtonRingWidth = 4;
        $this->floatingButtonRingShape = 'circle';
        $this->floatingButtonPlacement = 'bottom-right';
        $this->floatingButtonBorderRadius = 56;
        $this->avatarBorderRadius = 56;
        $this->fieldBorderColor = '#e2e8f0';
        $this->fieldBorderRadius = 12;
        $this->fieldBorderWidth = 1;
        $this->fieldBorderStyle = 'solid';
        $this->fieldShadow = 'soft';
        $this->loadingScreenEnabled = false;
        $this->loadingTime = 2;
        $this->qrLogoMode = 'none';
        $this->qrHasLogo = false;
        $this->previewSectionOrder = ['phones', 'emails', 'sites', 'location', 'companies', 'social'];
    }

    public function loadVcard(int $id): void
    {
        $vcard = Vcard::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $this->editingVcardId = $vcard->id;
        $this->savedVcard = $vcard;

        $this->firstName = $vcard->first_name ?? '';
        $this->lastName = $vcard->last_name ?? '';
        $this->designation = $this->getStoredValue($vcard, 'designation', $vcard->job_title ?? '');
        $this->aboutMe = $this->getStoredValue($vcard, 'about_me', $vcard->note ?? '');
        $this->street = $this->getStoredValue($vcard, 'street', '');
        $this->city = $this->getStoredValue($vcard, 'city', '');
        $this->state = $this->getStoredValue($vcard, 'state', '');
        $this->zip = $this->getStoredValue($vcard, 'zip', '');
        $this->country = $this->getStoredValue($vcard, 'country', '');
        $this->locationLabel = $this->getStoredValue($vcard, 'location_label', 'Location');
        $this->locationIcon = $this->getStoredValue($vcard, 'location_icon', 'location_on');
        $this->locationSearch = $this->getStoredValue($vcard, 'location_search', '');
        $this->locationUrl = $this->getStoredValue($vcard, 'location_url', '');
        $this->latitude = $this->getStoredValue($vcard, 'latitude', '');
        $this->longitude = $this->getStoredValue($vcard, 'longitude', '');

        $this->template = $this->getStoredValue($vcard, 'template', $vcard->card_style ?? 'modern-banner-center');
        $this->theme = $vcard->theme ?? 'modern-banner-center';

        if (!isset($this->templates[$this->template])) {
            $this->template = 'modern-banner-center';
        }

        if (!isset($this->templates[$this->theme])) {
            $this->theme = $this->template;
        }
        $this->fontFamily = $vcard->font_family ?? 'Poppins';
        $this->accentColor = $vcard->accent_color ?? '#06b6d4';
        $this->bgColor = $vcard->bg_color ?? '#0f172a';
        $this->textColor = $vcard->text_color ?? '#ffffff';
        $this->cardBg = $vcard->card_bg ?? '#1e293b';
        $this->buttonTextColor = $this->getStoredValue($vcard, 'button_text_color', '#ffffff');
        $this->avatarRingEnabled = (bool) $this->getStoredValue($vcard, 'avatar_ring_enabled', true);
        $this->avatarRingColor = $this->getStoredValue($vcard, 'avatar_ring_color', '#ffffff');
        $this->avatarRingWidth = min(12, max(0, (int) $this->getStoredValue($vcard, 'avatar_ring_width', 4)));
        $this->floatingButtonRingEnabled = (bool) $this->getStoredValue($vcard, 'floating_button_ring_enabled', true);
        $this->floatingButtonRingColor = $this->getStoredValue($vcard, 'floating_button_ring_color', '#ffffff');
        $this->floatingButtonRingWidth = min(12, max(0, (int) $this->getStoredValue($vcard, 'floating_button_ring_width', 4)));
        $this->floatingButtonRingShape = $this->getStoredValue($vcard, 'floating_button_ring_shape', 'circle');
        if (!in_array($this->floatingButtonRingShape, ['square', 'rounded', 'circle'], true)) {
            $this->floatingButtonRingShape = 'circle';
        }
        $this->floatingButtonPlacement = $this->getStoredValue($vcard, 'floating_button_placement', 'bottom-right');
        if (!in_array($this->floatingButtonPlacement, ['top-right', 'top-left', 'bottom-right', 'bottom-left'], true)) {
            $this->floatingButtonPlacement = 'bottom-right';
        }
        $this->floatingButtonBorderRadius = min(56, max(0, (int) $this->getStoredValue($vcard, 'floating_button_border_radius', 56)));
        $this->avatarBorderRadius = min(56, max(0, (int) $this->getStoredValue($vcard, 'avatar_border_radius', 56)));
        $this->fieldBorderColor = $this->getStoredValue($vcard, 'field_border_color', '#e2e8f0');
        $this->fieldBorderRadius = (int) $this->getStoredValue($vcard, 'field_border_radius', 12);
        $this->fieldBorderWidth = min(10, max(0, (int) $this->getStoredValue($vcard, 'field_border_width', 1)));
        $this->fieldBorderStyle = $this->getStoredValue($vcard, 'field_border_style', 'solid');
        $this->fieldShadow = $this->getStoredValue($vcard, 'field_shadow', 'soft');

        $this->phones = $this->getStoredArray($vcard, 'phones', [['type' => 'mobile', 'label' => 'Mobile', 'value' => $vcard->phone_mobile ?? '', 'icon' => 'call'], ['type' => 'work', 'label' => 'Work', 'value' => $vcard->phone_work ?? '', 'icon' => 'work']]);

        $this->emails = $this->getStoredArray($vcard, 'emails', [['label' => 'Email', 'value' => $vcard->email ?? '', 'icon' => 'mail']]);

        $this->sites = $this->getStoredArray($vcard, 'sites', [['label' => 'Website', 'value' => $vcard->website ?? '', 'icon' => 'language']]);

        $this->companies = $this->getStoredArray($vcard, 'companies', [['company_name' => $vcard->company ?? '', 'profession' => $vcard->job_title ?? '', 'icon' => 'business_center']]);

        $this->socialLinks = $this->getStoredArray(
            $vcard,
            'social_links',
            array_filter([
                'facebook' => $vcard->facebook ?? '',
                'linkedin' => $vcard->linkedin ?? '',
                'twitter' => $vcard->twitter ?? '',
                'instagram' => $vcard->instagram ?? '',
            ]),
        );
        $this->showSocialName = (bool) $this->getStoredValue($vcard, 'show_social_name', false);
        $this->showSocialAsCards = (bool) $this->getStoredValue($vcard, 'show_social_as_cards', false);
        $this->socialIconMode = $this->getStoredArray($vcard, 'social_icon_mode', []);
        $this->socialCustomIcons = $this->getStoredArray($vcard, 'social_custom_icons', []);
        foreach (array_keys($this->socialLinks) as $platform) {
            $this->socialIconMode[$platform] = 'custom';
            $this->socialCustomIcons[$platform] ??= 'brand:' . $platform;
        }
        $this->previewSectionOrder = $this->getStoredArray($vcard, 'preview_section_order', $this->previewSectionOrder);
        $this->normalizePreviewSectionOrder();

        $this->contactButtonText = $this->getStoredValue($vcard, 'contact_button_text', 'Save Contact');
        $this->contactButtonPosition = $this->getStoredValue($vcard, 'contact_button_position', 'top');

        $this->profilePreview = $vcard->profile_path ? Storage::url($vcard->profile_path) : null;
        $this->bannerPreview = $vcard->banner_path ? Storage::url($vcard->banner_path) : null;
        $this->loadingImagePreview = $vcard->loading_path ? Storage::url($vcard->loading_path) : null;
        $this->loadingScreenEnabled = (bool) $this->getStoredValue($vcard, 'loading_screen_enabled', false);
        $this->loadingTime = min(10, max(1, (int) $this->getStoredValue($vcard, 'loading_time', 2)));
        $this->qrLogoPreview = $vcard->qr_logo_path ? Storage::url($vcard->qr_logo_path) : null;

        $storedQrLogoMode = $this->getStoredValue(
            $vcard,
            'qr_logo_mode',
            $vcard->qr_logo_path ? 'custom' : 'none'
        );

        $this->qrLogoMode = $this->isPremium && $storedQrLogoMode === 'custom' && $vcard->qr_logo_path
            ? 'custom'
            : ($this->isPremium ? 'none' : 'site');

        $this->qrHasLogo = $this->isPremium ? $this->qrLogoMode === 'custom' : true;

        if (!$this->isPremium) {
            $this->applyFreePlanRestrictions();
        }

        $this->publicUrl = $this->publicVcardUrl($vcard);
        $this->qrSvg = $this->buildQrSvg($vcard);
    }

    private function makeUniqueSlug(): string
    {
        do {
            $slug = Str::slug($this->getFullName());

            if (!$slug || $slug === 'your-name') {
                $slug = 'vcard';
            }

            $slug .= '-' . Str::lower(Str::random(7));
        } while (Vcard::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function resolveQrLogoPath(Vcard $vcard): ?string
    {
        if (!$this->isPremium) {
            // Free users always get the site logo inside the QR code.
            return $this->getSiteLogoPath();
        }

        $mode = $this->qrLogoMode ?: $this->getStoredValue(
            $vcard,
            'qr_logo_mode',
            $vcard->qr_logo_path ? 'custom' : 'none'
        );

        if ($mode !== 'custom' || !$this->qrHasLogo) {
            return null;
        }

        if ($mode === 'custom') {
            if ($this->qrLogo && method_exists($this->qrLogo, 'getRealPath') && file_exists($this->qrLogo->getRealPath())) {
                return $this->qrLogo->getRealPath();
            }

            if ($vcard->qr_logo_path) {
                $path = storage_path('app/public/' . $vcard->qr_logo_path);

                return file_exists($path) ? $path : null;
            }

            return null;
        }

        return $this->getSiteLogoPath();
    }

    private function buildQrSvg(Vcard $vcard): string
    {
        $url = $this->publicVcardUrl($vcard);
        $logoPath = $this->resolveQrLogoPath($vcard);

        $qr = QrCode::format('svg')
            ->size(320)
            ->margin(1)
            ->errorCorrection('H');

        // Logos are rendered as a centered HTML overlay in the preview and
        // drawn directly onto the JavaScript PNG canvas. This keeps free site
        // logos and premium custom logos consistent across preview/download.
        if ($logoPath) {
            return $qr->generate($url);
        }

        if ($logoPath && file_exists($logoPath)) {
            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

            if (in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                try {
                    return $qr->merge($logoPath, 0.22, true)->generate($url);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $qr->generate($url);
    }

    private function publicVcardUrl(Vcard $vcard): string
    {
        return Route::has('vcard.public.show') ? route('vcard.public.show', ['slug' => $vcard->slug]) : url('/v/' . $vcard->slug);
    }

    private function getSiteLogoPath(): ?string
    {
        $setting = SiteSetting::query()->first();
        $logo = $setting?->logo ?? ($setting?->site_logo ?? null);

        if (!$logo) {
            return null;
        }

        $logo = ltrim($logo, '/');
        $paths = [storage_path('app/public/' . $logo), public_path($logo), public_path('storage/' . $logo)];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function getQrDisplayLogoUrlProperty(): ?string
    {
        if (!$this->isPremium) {
            $setting = SiteSetting::query()->first();
            $logo = $setting?->logo ?? ($setting?->site_logo ?? null);

            if (!$logo) {
                return null;
            }

            if (filter_var($logo, FILTER_VALIDATE_URL)) {
                return $logo;
            }

            $logo = ltrim($logo, '/');

            if (Storage::disk('public')->exists($logo)) {
                return Storage::url($logo);
            }

            if (file_exists(public_path($logo))) {
                return asset($logo);
            }

            if (file_exists(public_path('storage/' . $logo))) {
                return asset('storage/' . $logo);
            }

            return null;
        }

        return $this->qrLogoMode === 'custom' ? $this->qrLogoPreview : null;
    }

    private function cleanRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->map(function ($row) use ($keys) {
                return collect($keys)->mapWithKeys(fn($key) => [$key => trim((string) ($row[$key] ?? ''))])->toArray();
            })
            ->filter(fn($row) => collect($row)->filter()->isNotEmpty())
            ->values()
            ->toArray();
    }

    private function getStoredValue(Vcard $vcard, string $column, mixed $default = null): mixed
    {
        return Schema::hasColumn('vcards', $column) && isset($vcard->{$column}) ? $vcard->{$column} : $default;
    }

    private function getStoredArray(Vcard $vcard, string $column, array $default = []): array
    {
        if (!Schema::hasColumn('vcards', $column) || empty($vcard->{$column})) {
            return $default;
        }

        if (is_array($vcard->{$column})) {
            return $vcard->{$column};
        }

        $decoded = json_decode((string) $vcard->{$column}, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function escapeVcf(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);

        return str_replace([';', ',', '\\'], ['\\;', '\\,', '\\\\'], $value);
    }

    private function hasMinimumVcardData(): bool
    {
        $hasName = filled(trim($this->firstName . ' ' . $this->lastName));
        $hasPhone = filled($this->primaryPhone);
        $hasEmail = filled($this->primaryEmail);
        $hasSite = filled($this->primarySite);
        $hasAbout = filled($this->aboutMe);
        $hasCompany = collect($this->companies)->contains(fn($item) => filled($item['company_name'] ?? '') || filled($item['profession'] ?? ''));
        $hasSocial = count(array_filter($this->socialLinks)) > 0;
        $hasLocation = filled($this->locationUrl) || (filled($this->latitude) && filled($this->longitude)) || filled($this->locationSearch) || filled(trim(implode(' ', array_filter([$this->street, $this->city, $this->state, $this->zip, $this->country]))));

        return $hasName || $hasPhone || $hasEmail || $hasSite || $hasAbout || $hasCompany || $hasSocial || $hasLocation;
    }

    public function generateVcf()
    {
        if (!$this->hasMinimumVcardData()) {
            $this->addError('vcf', 'Please add at least one detail before downloading VCF.');

            return null;
        }

        $vcf = $this->buildVcf();
        $this->generatedVcf = $vcf;
        $this->resetErrorBag('vcf');

        $filename = Str::slug($this->getFullName()) ?: 'contact';

        return response()->streamDownload(fn() => print $vcf, $filename . '.vcf', ['Content-Type' => 'text/vcard; charset=utf-8']);
    }

    public function downloadVcf()
    {
        if (!$this->generatedVcf) {
            return $this->generateVcf();
        }

        $filename = Str::slug($this->getFullName()) ?: 'contact';

        return response()->streamDownload(fn() => print $this->generatedVcf, $filename . '.vcf', ['Content-Type' => 'text/vcard; charset=utf-8']);
    }

    private function buildVcf(): string
    {
        $fullName = $this->escapeVcf($this->getFullName());
        $firstName = $this->escapeVcf($this->firstName);
        $lastName = $this->escapeVcf($this->lastName);
        $primaryCompany = $this->primaryCompany;

        $vcf = "BEGIN:VCARD\r\n";
        $vcf .= "VERSION:3.0\r\n";
        $vcf .= "FN:{$fullName}\r\n";
        $vcf .= "N:{$lastName};{$firstName};;;\r\n";

        if (filled($primaryCompany['company_name'] ?? '')) {
            $vcf .= 'ORG:' . $this->escapeVcf($primaryCompany['company_name']) . "\r\n";
        }

        if (filled($this->designation)) {
            $vcf .= 'TITLE:' . $this->escapeVcf($this->designation) . "\r\n";
        }

        foreach ($this->emails as $email) {
            if (filled($email['value'] ?? '')) {
                $vcf .= 'EMAIL;TYPE=' . strtoupper($this->escapeVcf($email['label'] ?? 'Email')) . ':' . $this->escapeVcf($email['value']) . "\r\n";
            }
        }

        foreach ($this->phones as $phone) {
            if (filled($phone['value'] ?? '')) {
                $type = strtoupper($phone['type'] ?? 'other');
                $vcf .= "TEL;TYPE={$type}:" . $this->escapeVcf($phone['value']) . "\r\n";
            }
        }

        foreach ($this->sites as $site) {
            if (filled($site['value'] ?? '')) {
                $vcf .= 'URL:' . $this->escapeVcf($site['value']) . "\r\n";
            }
        }

        if (filled($this->aboutMe)) {
            $vcf .= 'NOTE:' . $this->escapeVcf($this->aboutMe) . "\r\n";
        }

        $vcf .= "END:VCARD\r\n";

        return $vcf;
    }

    public function updated(mixed $property): void
    {
        if (!in_array($property, ['profileImage', 'bannerImage', 'loadingImage', 'qrLogo'], true)) {
            $this->generatedVcf = null;
            $this->resetErrorBag('vcf');
        }
    }
};
?>

@push('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,100..1000&family=Inter:opsz,wght@14..32,100..1000&family=Lora:wght@400..700&family=Merriweather:wght@300..900&family=Montserrat:wght@100..900&family=Nunito:wght@200..1000&family=Oswald:wght@200..700&family=Playfair+Display:wght@400..900&family=Plus+Jakarta+Sans:wght@200..800&family=Poppins:wght@100..900&family=Raleway:wght@100..900&family=Roboto:wght@100..900&family=Source+Sans+3:wght@200..900&family=Space+Grotesk:wght@300..700&display=swap"
        rel="stylesheet">
@endpush

<style>
    .vcard-builder select {
        background-color: #020617 !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.10) !important;
        color-scheme: dark;
    }

    .vcard-builder select option,
    .vcard-builder select optgroup {
        background-color: #020617 !important;
        color: #ffffff !important;
    }

    .vcard-builder select option:checked {
        background-color: #0e7490 !important;
        color: #ffffff !important;
    }

    .vcard-builder select option:hover,
    .vcard-builder select option:focus {
        background-color: #164e63 !important;
        color: #ffffff !important;
    }

    .vcard-builder select:focus {
        border-color: rgba(103, 232, 249, 0.45) !important;
        box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.10);
    }

    @keyframes vcardLoadingBar {
        from {
            width: 0%;
        }

        to {
            width: 100%;
        }
    }

    @keyframes vcardLoadingFloat {

        0%,
        100% {
            transform: translateY(0) scale(1);
        }

        50% {
            transform: translateY(-6px) scale(1.02);
        }
    }

    @keyframes vcardLoadingShine {
        0% {
            transform: translateX(-120%);
        }

        100% {
            transform: translateX(120%);
        }
    }

    @keyframes vcardLoadingPulse {

        0%,
        100% {
            opacity: .45;
            transform: scale(.92);
        }

        50% {
            opacity: 1;
            transform: scale(1.04);
        }
    }

    @keyframes vcardLoadingSpin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div class="vcard-builder min-h-screen text-white" x-data="{
    iconPicker: { open: false, path: '', type: 'contact', title: 'Choose icon', selected: '' },
    openIconPicker(path, type, title, selected) {
        this.iconPicker = { open: true, path, type, title, selected };
        document.body.classList.add('overflow-hidden');
    },
    closeIconPicker() {
        this.iconPicker.open = false;
        document.body.classList.remove('overflow-hidden');
    },
    async loadQrDownloadImage(source) {
        if (!source) return null;

        return await new Promise((resolve, reject) => {
            const image = new Image();
            image.decoding = 'async';
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Could not load QR image asset.'));
            image.src = source;
        });
    },
    drawRoundedRect(context, x, y, width, height, radius) {
        const safeRadius = Math.min(radius, width / 2, height / 2);
        context.beginPath();
        context.moveTo(x + safeRadius, y);
        context.lineTo(x + width - safeRadius, y);
        context.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
        context.lineTo(x + width, y + height - safeRadius);
        context.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
        context.lineTo(x + safeRadius, y + height);
        context.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
        context.lineTo(x, y + safeRadius);
        context.quadraticCurveTo(x, y, x + safeRadius, y);
        context.closePath();
    },
    async downloadQrPng(filename = 'vcard-qr') {
        const wrapper = document.querySelector('#vcard-qr-svg');
        const svg = wrapper?.querySelector('svg');

        if (!svg) {
            console.error('QR SVG was not found.');
            return;
        }

        const clonedSvg = svg.cloneNode(true);

        // Remove the SVG logo image because browsers may skip it while converting
        // a Blob-based SVG to canvas. The logo is drawn separately below.
        clonedSvg.querySelectorAll('image').forEach((node) => node.remove());
        clonedSvg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clonedSvg.setAttribute('width', '1024');
        clonedSvg.setAttribute('height', '1024');

        const serializer = new XMLSerializer();
        const svgText = serializer.serializeToString(clonedSvg);
        const svgBlob = new Blob([svgText], { type: 'image/svg+xml;charset=utf-8' });
        const svgUrl = URL.createObjectURL(svgBlob);

        try {
            const qrImage = await this.loadQrDownloadImage(svgUrl);
            const canvas = document.createElement('canvas');
            canvas.width = 1024;
            canvas.height = 1024;

            const context = canvas.getContext('2d');
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(qrImage, 0, 0, canvas.width, canvas.height);

            const logoUrl = wrapper.dataset.logoUrl || '';

            if (logoUrl) {
                try {
                    const logoImage = await this.loadQrDownloadImage(logoUrl);
                    const logoBox = 230;
                    const logoPadding = 24;
                    const logoRadius = 34;
                    const logoX = (canvas.width - logoBox) / 2;
                    const logoY = (canvas.height - logoBox) / 2;

                    context.save();
                    this.drawRoundedRect(context, logoX, logoY, logoBox, logoBox, logoRadius);
                    context.fillStyle = '#ffffff';
                    context.fill();
                    context.restore();

                    const available = logoBox - logoPadding * 2;
                    const scale = Math.min(available / logoImage.naturalWidth, available / logoImage.naturalHeight);
                    const width = logoImage.naturalWidth * scale;
                    const height = logoImage.naturalHeight * scale;
                    const x = (canvas.width - width) / 2;
                    const y = (canvas.height - height) / 2;

                    context.save();
                    this.drawRoundedRect(context, logoX, logoY, logoBox, logoBox, logoRadius);
                    context.clip();
                    context.drawImage(logoImage, x, y, width, height);
                    context.restore();
                } catch (logoError) {
                    console.error('Could not add the custom logo to the downloaded QR.', logoError);
                }
            }

            canvas.toBlob((pngBlob) => {
                if (!pngBlob) {
                    console.error('Could not create the PNG file.');
                    return;
                }

                const downloadUrl = URL.createObjectURL(pngBlob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `${filename}.png`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
            }, 'image/png');
        } catch (error) {
            console.error('Could not convert the QR code to PNG.', error);
        } finally {
            URL.revokeObjectURL(svgUrl);
        }
    },
    chooseIcon(icon) {
        this.$wire.set(this.iconPicker.path, icon);
        this.iconPicker.selected = icon;
        this.closeIconPicker();
    }
}" @keydown.escape.window="closeIconPicker()"
    x-cloak>
    <section class="mx-auto max-w-350 px-4 py-10 sm:px-6 lg:px-8">
        <div class="relative mb-6 text-center sm:mb-10">
            <p class="text-xs font-black uppercase tracking-[0.28em] text-cyan-300 sm:text-sm sm:tracking-[0.35em]">
                Business Tools</p>
            <h1 class="mt-2 text-xl font-black tracking-tight sm:mt-4 sm:text-5xl">VCard Generator</h1>
            <p class="mx-auto mt-2 max-w-2xl text-xs leading-5 text-blue-100/65 sm:mt-4 sm:text-sm sm:leading-6">
                Create a premium digital vCard with templates, contact buttons, multiple contacts, social networks, QR
                code and scan rules.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_430px] lg:items-start">
            <div class="min-w-0 space-y-5">
                {{-- 1. Appearance --}}
                <div
                    class="overflow-hidden rounded-3xl border border-white/10 bg-white/6 shadow-xl shadow-cyan-950/10 backdrop-blur-xl">
                    <button type="button" wire:click="toggleSection('appearance')"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 p-4 text-left sm:p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-cyan-400/15 text-cyan-200">
                                <span class="material-symbols-outlined">palette</span>
                            </div>
                            <div>
                                <h2 class="text-base font-black sm:text-lg">Appearance</h2>
                                <p class="text-xs text-blue-100/45">Customize the style, template, colors, fonts and
                                    navbar.</p>
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined transition {{ $this->openSection === 'appearance' ? 'rotate-180' : '' }}">expand_more</span>
                    </button>

                    @if ($this->openSection === 'appearance')
                        <div class="border-t border-white/10 p-4 sm:p-5">
                            {{-- Select Template --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleAppearancePanel('template')"
                                    class="flex w-full cursor-pointer items-center gap-3 py-4 text-left">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openAppearancePanel === 'template' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span class="text-sm font-black text-white">Select Template</span>
                                </button>

                                @if ($this->openAppearancePanel === 'template')
                                    <div class="pb-6 pl-7" x-data="{
                                        isDragging: false,
                                        dragMoved: false,
                                        startX: 0,
                                        scrollLeft: 0,
                                        slideTemplate(direction) {
                                            this.$refs.templateScroller.scrollBy({
                                                left: direction * 260,
                                                behavior: 'smooth'
                                            });
                                        },
                                        startDrag(event) {
                                            this.isDragging = true;
                                            this.dragMoved = false;
                                            this.startX = event.pageX - this.$refs.templateScroller.offsetLeft;
                                            this.scrollLeft = this.$refs.templateScroller.scrollLeft;
                                        },
                                        moveDrag(event) {
                                            if (!this.isDragging) return;
                                            const x = event.pageX - this.$refs.templateScroller.offsetLeft;
                                            const walk = (x - this.startX) * 1.35;
                                            if (Math.abs(walk) > 6) this.dragMoved = true;
                                            this.$refs.templateScroller.scrollLeft = this.scrollLeft - walk;
                                        },
                                        stopDrag() {
                                            this.isDragging = false;
                                            setTimeout(() => this.dragMoved = false, 80);
                                        }
                                    }">
                                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                {{-- <p class="text-sm font-black text-white">Template slider</p>
                                                <p class="mt-1 text-xs text-blue-100/45">Grab and drag, or use the arrow
                                                    buttons.</p> --}}
                                            </div>

                                            <div class="flex shrink-0 items-center gap-2">
                                                {{-- <span
                                                    class="hidden rounded-full bg-cyan-400/10 px-3 py-1 text-[10px] font-black text-cyan-100 sm:inline-flex">
                                                    {{ $this->templates[$this->template]['label'] ?? 'Selected' }}
                                                </span> --}}

                                                <button type="button" x-on:click="slideTemplate(-1)"
                                                    class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/8 text-cyan-100 shadow-lg transition hover:bg-white/14"
                                                    title="Previous templates" aria-label="Previous templates">
                                                    <span class="material-symbols-outlined text-lg">chevron_left</span>
                                                </button>

                                                <button type="button" x-on:click="slideTemplate(1)"
                                                    class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/8 text-cyan-100 shadow-lg transition hover:bg-white/14"
                                                    title="Next templates" aria-label="Next templates">
                                                    <span class="material-symbols-outlined text-lg">chevron_right</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="w-full min-w-0 overflow-hidden">
                                            <div x-ref="templateScroller" x-on:mousedown="startDrag($event)"
                                                x-on:mousemove.prevent="moveDrag($event)" x-on:mouseup="stopDrag()"
                                                x-on:mouseleave="stopDrag()"
                                                x-on:click.capture="if (dragMoved) { $event.preventDefault(); $event.stopPropagation(); }"
                                                class="flex cursor-grab select-none gap-4 overflow-x-auto pb-4 pr-2 active:cursor-grabbing [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                                @foreach ($this->templates as $slug => $template)
                                                    @php
                                                        $tplHasBanner = (bool) ($template['has_banner'] ?? true);
                                                        $tplAvatarPosition =
                                                            $template['avatar_position'] ?? 'center-over-banner';
                                                        $tplAccent = $template['accent'] ?? '#06b6d4';
                                                        $tplBg = $template['bg'] ?? '#0f172a';
                                                        $tplText = $template['text'] ?? '#111827';
                                                        $tplCardBg = $template['card_bg'] ?? '#ffffff';
                                                        $tplEffect = $template['effect'] ?? 'normal';
                                                        $tplAvatarRadiusValue = min(
                                                            56,
                                                            max(0, (int) $this->avatarBorderRadius),
                                                        );
                                                        $tplAvatarRadius =
                                                            $tplAvatarRadiusValue >= 56
                                                            ? '999px'
                                                            : $tplAvatarRadiusValue . 'px';
                                                        $isSelectedTemplate = $this->template === $slug;
                                                        $templateLocked =
                                                            !$this->isPremium && $slug !== 'modern-banner-center';
                                                    @endphp

                                                    <button type="button"
                                                        @if (!$templateLocked) wire:click="selectTemplate('{{ $slug }}')" @endif
                                                        class="relative w-[185px] shrink-0 overflow-hidden rounded-2xl border-2 bg-white p-2 text-center transition {{ $templateLocked ? '' : 'hover:-translate-y-1 hover:shadow-xl' }} {{ $isSelectedTemplate ? 'border-cyan-400 ring-4 ring-cyan-400/15' : 'border-slate-200 hover:border-cyan-300' }}">
                                                        @if ($templateLocked)
                                                            <span class="absolute right-3 top-3 z-20">
                                                                <span
                                                                    class="material-symbols-outlined text-sm text-amber-400">crown</span>
                                                            </span>
                                                        @endif

                                                        <div class="relative mx-auto h-44 overflow-hidden rounded-xl border border-slate-100 shadow-sm"
                                                            style="background: {{ $tplBg }}; font-family: '{{ $this->fontFamily }}', sans-serif;">

                                                            <div class="absolute inset-2 overflow-hidden rounded-xl {{ in_array($tplEffect, ['glass', 'water-glass', 'glassmorphism'], true) ? 'backdrop-blur-xl ring-1 ring-white/30' : '' }}"
                                                                style="background: {{ in_array($tplEffect, ['glass', 'water-glass', 'glassmorphism'], true) ? 'rgba(255,255,255,0.16)' : $tplCardBg }};">

                                                                @if ($tplHasBanner)
                                                                    <div class="relative h-[72px] overflow-hidden"
                                                                        style="background: linear-gradient(135deg, {{ $tplAccent }}, {{ $tplBg }});">
                                                                        <div class="absolute inset-0 opacity-25"
                                                                            style="background-image: radial-gradient(circle at 22% 20%, #ffffff 0 2px, transparent 2px), radial-gradient(circle at 76% 48%, #ffffff 0 2px, transparent 2px); background-size: 22px 22px;">
                                                                        </div>
                                                                        <div
                                                                            class="absolute inset-0 bg-linear-to-b from-transparent to-white/90">
                                                                        </div>

                                                                        @if ($tplAvatarPosition === 'center-over-banner')
                                                                            <div
                                                                                class="absolute inset-x-0 bottom-1 flex justify-center">
                                                                                <div class="grid h-10 w-10 place-items-center border-[3px] border-white text-[10px] font-black text-white shadow"
                                                                                    style="background: {{ $tplAccent }}; border-radius: {{ $tplAvatarRadius }};">
                                                                                    {{ $this->getInitials() }}
                                                                                </div>
                                                                            </div>
                                                                        @elseif ($tplAvatarPosition === 'left-over-banner')
                                                                            <div class="absolute bottom-1 left-3">
                                                                                <div class="grid h-10 w-10 place-items-center border-[3px] border-white text-[10px] font-black text-white shadow"
                                                                                    style="background: {{ $tplAccent }}; border-radius: {{ $tplAvatarRadius }};">
                                                                                    {{ $this->getInitials() }}
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    </div>

                                                                    <div
                                                                        class="{{ $tplAvatarPosition === 'left-over-banner' ? 'px-3 pt-2 text-left' : 'px-3 pt-2 text-center' }}">
                                                                        <div class="{{ $tplAvatarPosition === 'left-over-banner' ? 'h-2 w-16' : 'mx-auto h-2 w-16' }} rounded-full"
                                                                            style="background: {{ $tplText }};">
                                                                        </div>
                                                                        <div class="{{ $tplAvatarPosition === 'left-over-banner' ? 'mt-1 h-1.5 w-12' : 'mx-auto mt-1 h-1.5 w-12' }} rounded-full"
                                                                            style="background: {{ $tplText }}55;">
                                                                        </div>
                                                                    </div>
                                                                @else
                                                                    <div class="px-3 pt-4">
                                                                        @if ($tplAvatarPosition === 'left-inline')
                                                                            <div class="flex items-center gap-2">
                                                                                <div class="grid h-11 w-11 shrink-0 place-items-center text-[10px] font-black text-white shadow"
                                                                                    style="background: {{ $tplAccent }}; border-radius: {{ $tplAvatarRadius }};">
                                                                                    {{ $this->getInitials() }}
                                                                                </div>
                                                                                <div class="min-w-0 flex-1">
                                                                                    <div class="h-2 w-16 rounded-full"
                                                                                        style="background: {{ $tplText }};">
                                                                                    </div>
                                                                                    <div class="mt-1 h-1.5 w-10 rounded-full"
                                                                                        style="background: {{ $tplText }}55;">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="flex justify-center">
                                                                                <div class="grid h-12 w-12 place-items-center text-[11px] font-black text-white shadow"
                                                                                    style="background: {{ $tplAccent }}; border-radius: {{ $tplAvatarRadius }};">
                                                                                    {{ $this->getInitials() }}
                                                                                </div>
                                                                            </div>
                                                                            <div class="mt-3 text-center">
                                                                                <div class="mx-auto h-2 w-16 rounded-full"
                                                                                    style="background: {{ $tplText }};">
                                                                                </div>
                                                                                <div class="mx-auto mt-1 h-1.5 w-12 rounded-full"
                                                                                    style="background: {{ $tplText }}55;">
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <div class="mx-3 mt-3 h-5 rounded-full"
                                                                    style="background: {{ $tplAccent }};"></div>

                                                                <div class="mx-3 mt-2 space-y-1.5">
                                                                    @for ($i = 0; $i < 3; $i++)
                                                                        <div class="flex items-center gap-1.5 border px-1.5 py-1 shadow-sm {{ in_array($tplEffect, ['glass', 'water-glass', 'glassmorphism'], true) ? 'backdrop-blur-xl' : 'bg-white' }}"
                                                                            style="border-color: {{ in_array($tplEffect, ['glass', 'water-glass', 'glassmorphism'], true) ? 'rgba(255,255,255,0.30)' : ($this->fieldBorderStyle === 'none' ? 'transparent' : $this->fieldBorderColor) }}; border-style: {{ $this->fieldBorderStyle }}; border-width: {{ $this->fieldBorderStyle === 'none' ? 0 : (int) $this->fieldBorderWidth }}px; border-radius: {{ (int) $this->fieldBorderRadius }}px; background: {{ in_array($tplEffect, ['glass', 'water-glass', 'glassmorphism'], true) ? 'rgba(255,255,255,0.16)' : '#ffffff' }};">
                                                                            <span class="h-4 w-4 rounded"
                                                                                style="background: {{ $tplAccent }}22;"></span>
                                                                            <span class="h-1.5 flex-1 rounded-full"
                                                                                style="background: {{ $tplText }}22;"></span>
                                                                        </div>
                                                                    @endfor
                                                                </div>

                                                                @if ($this->contactButtonPosition === 'floating')
                                                                    <div class="absolute bottom-2 right-2 grid h-8 w-8 place-items-center text-white shadow-lg ring-2 ring-white"
                                                                        style="background: {{ $tplAccent }}; border-radius: {{ $this->floatingButtonBorderRadius >= 56 ? '999px' : $this->floatingButtonBorderRadius . 'px' }};">
                                                                        <span
                                                                            class="material-symbols-outlined text-[16px]">person_add</span>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            @if ($isSelectedTemplate)
                                                                <div
                                                                    class="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-cyan-400 text-white shadow-lg">
                                                                    <span
                                                                        class="material-symbols-outlined text-[16px]">check</span>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <div class="mt-3 flex items-center justify-center gap-1.5">
                                                            <span class="h-2 w-2 rounded-full"
                                                                style="background: {{ $tplAccent }};"></span>
                                                            <p class="truncate text-xs font-black text-slate-700">
                                                                {{ $template['label'] }}</p>
                                                        </div>
                                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">
                                                            {{ $template['use_profile_as_banner'] ?? false ? 'Profile hero' : ($tplHasBanner ? 'Banner' : 'No banner') }}
                                                            ·
                                                            {{ str_replace('-', ' ', $tplAvatarPosition) }}
                                                        </p>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Contact Setting --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleAppearancePanel('contact-setting')"
                                    class="flex w-full items-center justify-between gap-3 py-4 text-left {{ !$this->isPremium ? 'opacity-55' : 'cursor-pointer' }}">
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openAppearancePanel === 'contact-setting' ? 'rotate-90' : '' }}">chevron_right</span>
                                        <span class="text-sm font-black text-white">Contact Setting</span>
                                    </span>
                                    @if (!$this->isPremium)
                                        <span
                                            class="inline-flex items-center justify-center rounded-full border border-amber-300/20 bg-amber-400/10 p-1.5">
                                            <span class="material-symbols-outlined text-sm text-amber-400">crown</span>
                                        </span>
                                    @endif
                                </button>

                                @if ($this->openAppearancePanel === 'contact-setting')
                                    <div class="pb-6 pl-7">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Button
                                                    text</label>
                                                <input wire:model.live="contactButtonText" type="text"
                                                    placeholder="Save Contact"
                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Button
                                                    text color</label>
                                                <input wire:model.live="buttonTextColor" type="color"
                                                    class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Button
                                                    position</label>
                                                <select wire:model.live="contactButtonPosition"
                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none focus:border-cyan-300/40 !bg-slate-950 !text-white">
                                                    <option value="top"
                                                        style="background-color: #020617; color: #ffffff;">Top button
                                                    </option>
                                                    <option value="floating"
                                                        style="background-color: #020617; color: #ffffff;">Floating
                                                        button</option>
                                                </select>
                                            </div>

                                            <div
                                                class="rounded-2xl border border-cyan-300/15 bg-cyan-400/10 p-4 text-xs leading-5 text-cyan-100">
                                                Top button shows under the profile details. Floating button can be
                                                placed on any card corner.
                                            </div>

                                            @if ($this->contactButtonPosition === 'floating')
                                                <div
                                                    class="sm:col-span-2 rounded-2xl border border-white/10 bg-black/20 p-4">
                                                    <label
                                                        class="mb-3 block text-xs font-bold text-blue-100/55">Floating
                                                        button placement</label>
                                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                        @foreach ([
                                                                'top-left' => 'Top left',
                                                                'top-right' => 'Top right',
                                                                'bottom-left' => 'Bottom left',
                                                                'bottom-right' => 'Bottom right',
                                                            ] as $placementValue => $placementLabel)
                                                                    <button type="button"
                                                                        wire:click="$set('floatingButtonPlacement', '{{ $placementValue }}')"
                                                                        class="rounded-2xl border px-3 py-3 text-xs font-black transition {{ $this->floatingButtonPlacement === $placementValue ? 'border-cyan-300 bg-cyan-400/15 text-cyan-100' : 'border-white/10 bg-white/5 text-blue-100/65 hover:bg-white/10' }}">
                                                                        {{ $placementLabel }}
                                                                    </button>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div
                                                    class="sm:col-span-2 rounded-2xl border border-white/10 bg-black/20 p-4">
                                                    <label
                                                        class="mb-1 flex items-center justify-between text-xs font-bold text-blue-100/55">
                                                        <span>Floating button radius</span>
                                                        <span>{{ $this->floatingButtonBorderRadius >= 56 ? 'Circle' : $this->floatingButtonBorderRadius . 'px' }}</span>
                                                    </label>
                                                    <input wire:model.live="floatingButtonBorderRadius" type="range"
                                                        min="0" max="56" step="1"
                                                        class="w-full accent-cyan-400">
                                                    <div class="mt-3 grid grid-cols-3 gap-2">
                                                        <button type="button"
                                                            wire:click="$set('floatingButtonBorderRadius', 0)"
                                                            title="Square"
                                                            class="grid h-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10">
                                                            <span
                                                                class="h-4 w-4 rounded-none border-2 border-current"></span>
                                                        </button>
                                                        <button type="button"
                                                            wire:click="$set('floatingButtonBorderRadius', 18)"
                                                            title="Rounded"
                                                            class="grid h-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10">
                                                            <span
                                                                class="h-4 w-4 rounded-md border-2 border-current"></span>
                                                        </button>
                                                        <button type="button"
                                                            wire:click="$set('floatingButtonBorderRadius', 56)"
                                                            title="Circle"
                                                            class="grid h-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10">
                                                            <span
                                                                class="h-4 w-4 rounded-full border-2 border-current"></span>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div
                                                    class="sm:col-span-2 rounded-2xl border border-white/10 bg-black/20 p-4">
                                                    <label
                                                        class="flex items-center justify-between gap-3 text-xs font-bold text-blue-100/70">
                                                        <span>Floating button ring</span>
                                                        <input wire:model.live="floatingButtonRingEnabled"
                                                            type="checkbox" class="accent-cyan-400">
                                                    </label>
                                                    @if ($this->floatingButtonRingEnabled)
                                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                            <div>
                                                                <label
                                                                    class="block text-[10px] font-bold uppercase tracking-wider text-blue-100/45">Ring
                                                                    color</label>
                                                                <input wire:model.live="floatingButtonRingColor"
                                                                    type="color"
                                                                    class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-blue-100/45">
                                                                    <span>Ring width</span>
                                                                    <span>{{ $this->floatingButtonRingWidth }}px</span>
                                                                </label>
                                                                <input wire:model.live="floatingButtonRingWidth"
                                                                    type="range" min="0" max="12"
                                                                    step="1" class="w-full accent-cyan-400">
                                                            </div>
                                                        </div>

                                                        <div class="mt-3">
                                                            <label
                                                                class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-blue-100/45">Ring
                                                                shape</label>
                                                            <div class="grid grid-cols-3 gap-2">
                                                                <button type="button"
                                                                    wire:click="$set('floatingButtonRingShape', 'square')"
                                                                    class="grid h-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10 {{ $this->floatingButtonRingShape === 'square' ? 'border-cyan-300 bg-cyan-400/15 text-cyan-100' : '' }}"
                                                                    title="Square ring">
                                                                    <span
                                                                        class="h-4 w-4 rounded-none border-2 border-current"></span>
                                                                </button>
                                                                <button type="button"
                                                                    wire:click="$set('floatingButtonRingShape', 'rounded')"
                                                                    class="grid h-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10 {{ $this->floatingButtonRingShape === 'rounded' ? 'border-cyan-300 bg-cyan-400/15 text-cyan-100' : '' }}"
                                                                    title="Rounded ring">
                                                                    <span
                                                                        class="h-4 w-4 rounded-md border-2 border-current"></span>
                                                                </button>
                                                                <button type="button"
                                                                    wire:click="$set('floatingButtonRingShape', 'circle')"
                                                                    class="grid h-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10 {{ $this->floatingButtonRingShape === 'circle' ? 'border-cyan-300 bg-cyan-400/15 text-cyan-100' : '' }}"
                                                                    title="Circle ring">
                                                                    <span
                                                                        class="h-4 w-4 rounded-full border-2 border-current"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Design --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleAppearancePanel('design')"
                                    class="flex w-full items-center justify-between gap-3 py-4 text-left {{ !$this->isPremium ? 'opacity-55' : 'cursor-pointer' }}">
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openAppearancePanel === 'design' ? 'rotate-90' : '' }}">chevron_right</span>
                                        <span class="text-sm font-black text-white">Design</span>
                                    </span>
                                    @if (!$this->isPremium)
                                        <span
                                            class="inline-flex items-center justify-center rounded-full border border-amber-300/20 bg-amber-400/10 p-1.5">
                                            <span class="material-symbols-outlined text-sm text-amber-400">crown</span>
                                        </span>
                                    @endif
                                </button>

                                @if ($this->openAppearancePanel === 'design')
                                    <div class="pb-6 pl-7">
                                        <label
                                            class="mb-3 block text-xs font-black uppercase tracking-wider text-blue-100/55">Color
                                            palette</label>
                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                                            @foreach ([['#06b6d4', '#1e293b', '#ffffff'], ['#2563eb', '#ffffff', '#111827'], ['#d4a762', '#1c1917', '#fafaf9'], ['#ec4899', '#312e81', '#ffffff'], ['#22c55e', '#14532d', '#ffffff'], ['#f97316', '#1c1917', '#ffffff'], ['#8b5cf6', '#111827', '#ffffff'], ['#64748b', '#f8fafc', '#334155']] as $preset)
                                                <button type="button"
                                                    wire:click="applyColorPreset('{{ $preset[0] }}', '{{ $preset[1] }}', '{{ $preset[2] }}')"
                                                    class="overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-1 transition hover:-translate-y-0.5 hover:bg-white/10">
                                                    <div class="flex h-12 overflow-hidden rounded-xl">
                                                        <span class="w-1/3"
                                                            style="background: {{ $preset[0] }}"></span>
                                                        <span class="w-1/3"
                                                            style="background: {{ $preset[1] }}"></span>
                                                        <span class="w-1/3"
                                                            style="background: {{ $preset[2] }}"></span>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>

                                        <div class="mt-5 grid gap-4 grid-cols-2 lg:grid-cols-4">
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Accent
                                                    color</label>
                                                <input wire:model.live="accentColor" type="color"
                                                    class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Card
                                                    background</label>
                                                <input wire:model.live="cardBg" type="color"
                                                    class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Page
                                                    background</label>
                                                <input wire:model.live="bgColor" type="color"
                                                    class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                            </div>

                                        </div>

                                        <div class="mt-5 rounded-2xl border border-white/10 bg-black/20 p-4 sm:p-5">
                                            <div
                                                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h4 class="text-sm font-black text-white">Avatar & info card style
                                                    </h4>
                                                    <p class="mt-1 text-xs leading-5 text-blue-100/45">Grouped controls
                                                        for profile image, avatar ring and contact info cards.</p>
                                                </div>
                                                <span
                                                    class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-cyan-100/80">Design
                                                    controls</span>
                                            </div>

                                            <div class="space-y-4">
                                                <div class="grid gap-4 lg:grid-cols-2">
                                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                                        <div class="mb-3 flex items-center justify-between gap-3">
                                                            <div>
                                                                <p
                                                                    class="text-xs font-black uppercase tracking-[0.18em] text-cyan-100/60">
                                                                    Avatar</p>
                                                                <p class="mt-1 text-sm font-bold text-white">Image
                                                                    radius</p>
                                                            </div>
                                                            <div class="grid h-12 w-12 place-items-center border text-white shadow-lg"
                                                                style="background: {{ $this->accentColor }}; border-color: {{ $this->avatarRingEnabled ? $this->avatarRingColor : 'rgba(255,255,255,0.15)' }}; border-width: {{ $this->avatarRingEnabled ? (int) $this->avatarRingWidth : 1 }}px; border-radius: {{ $this->avatarBorderRadius >= 56 ? 999 : $this->avatarBorderRadius }}px;">
                                                                <span
                                                                    class="text-xs font-black">{{ strtoupper(substr($this->getInitials(), 0, 2)) }}</span>
                                                            </div>
                                                        </div>

                                                        <label
                                                            class="mb-2 flex items-center justify-between text-xs font-bold text-blue-100/55">
                                                            <span>User image radius</span>
                                                            <span>{{ $this->avatarBorderRadius >= 56 ? 'Circle' : $this->avatarBorderRadius . 'px' }}</span>
                                                        </label>
                                                        <input wire:model.live="avatarBorderRadius" type="range"
                                                            min="0" max="56" step="1"
                                                            class="w-full accent-cyan-400">

                                                        <div class="mt-3 grid grid-cols-3 gap-2">
                                                            <button type="button"
                                                                wire:click="$set('avatarBorderRadius', 0)"
                                                                title="Square"
                                                                class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-[11px] font-semibold text-blue-100/75 transition hover:bg-white/10">
                                                                <span
                                                                    class="h-4 w-4 rounded-none border-2 border-current"></span>
                                                                Flat
                                                            </button>
                                                            <button type="button"
                                                                wire:click="$set('avatarBorderRadius', 20)"
                                                                title="Rounded"
                                                                class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-[11px] font-semibold text-blue-100/75 transition hover:bg-white/10">
                                                                <span
                                                                    class="h-4 w-4 rounded-md border-2 border-current"></span>
                                                                Soft
                                                            </button>
                                                            <button type="button"
                                                                wire:click="$set('avatarBorderRadius', 56)"
                                                                title="Circle"
                                                                class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-[11px] font-semibold text-blue-100/75 transition hover:bg-white/10">
                                                                <span
                                                                    class="h-4 w-4 rounded-full border-2 border-current"></span>
                                                                Round
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                                        <div class="mb-3 flex items-center justify-between gap-3">
                                                            <div>
                                                                <p
                                                                    class="text-xs font-black uppercase tracking-[0.18em] text-cyan-100/60">
                                                                    Avatar ring</p>
                                                                <p class="mt-1 text-sm font-bold text-white">Border
                                                                    around profile image</p>
                                                            </div>
                                                            <label
                                                                class="inline-flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/60 px-3 py-2">
                                                                <span
                                                                    class="text-[11px] font-black text-blue-100/70">{{ $this->avatarRingEnabled ? 'On' : 'Off' }}</span>
                                                                <input wire:model.live="avatarRingEnabled"
                                                                    type="checkbox" class="peer sr-only">
                                                                <span
                                                                    class="relative h-5 w-9 rounded-full bg-white/10 transition peer-checked:bg-cyan-400/80 after:absolute after:left-1 after:top-1 after:h-3 after:w-3 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-4"></span>
                                                            </label>
                                                        </div>

                                                        @if ($this->avatarRingEnabled)
                                                            <div class="grid gap-3 sm:grid-cols-2">
                                                                <div
                                                                    class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                                    <label
                                                                        class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-blue-100/45">Ring
                                                                        color</label>
                                                                    <input wire:model.live="avatarRingColor"
                                                                        type="color"
                                                                        class="h-11 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                                                </div>
                                                                <div
                                                                    class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                                    <label
                                                                        class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-blue-100/45">
                                                                        <span>Ring width</span>
                                                                        <span>{{ $this->avatarRingWidth }}px</span>
                                                                    </label>
                                                                    <input wire:model.live="avatarRingWidth"
                                                                        type="range" min="0" max="12"
                                                                        step="1" class="w-full accent-cyan-400">
                                                                </div>
                                                            </div>
                                                        @else
                                                            <p
                                                                class="rounded-2xl border border-white/10 bg-black/20 px-3 py-3 text-xs leading-5 text-blue-100/45">
                                                                Avatar ring is hidden. Turn it on to customize color and
                                                                width.</p>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                                    <div
                                                        class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <p
                                                                class="text-xs font-black uppercase tracking-[0.18em] text-cyan-100/60">
                                                                Info cards</p>
                                                            <p class="mt-1 text-sm font-bold text-white">Phone, email,
                                                                website, location, company and social cards</p>
                                                        </div>
                                                        <div class="rounded-2xl px-3 py-2 text-[11px] font-semibold text-blue-100/70"
                                                            style="border: {{ (int) $this->fieldBorderWidth }}px {{ $this->fieldBorderStyle === 'none' ? 'solid' : $this->fieldBorderStyle }} {{ $this->fieldBorderStyle === 'none' || (int) $this->fieldBorderWidth === 0 ? 'transparent' : $this->fieldBorderColor }}; border-radius: {{ (int) $this->fieldBorderRadius }}px; background: rgba(15, 23, 42, 0.55);">
                                                            Live border preview
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div
                                                            class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                            <label
                                                                class="mb-2 block text-xs font-bold text-blue-100/55">Border
                                                                color</label>
                                                            <input wire:model.live="fieldBorderColor" type="color"
                                                                class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                                        </div>

                                                        <div
                                                            class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                            <label
                                                                class="mb-2 flex items-center justify-between text-xs font-bold text-blue-100/55">
                                                                <span>Border width</span>
                                                                <span>{{ $this->fieldBorderWidth }}px</span>
                                                            </label>
                                                            <input wire:model.live="fieldBorderWidth" type="range"
                                                                min="0" max="10" step="1"
                                                                class="w-full accent-cyan-400">
                                                        </div>

                                                        <div
                                                            class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                            <label
                                                                class="mb-2 flex items-center justify-between text-xs font-bold text-blue-100/55">
                                                                <span>Field radius</span>
                                                                <span>{{ $this->fieldBorderRadius }}px</span>
                                                            </label>
                                                            <input wire:model.live="fieldBorderRadius" type="range"
                                                                min="0" max="32" step="1"
                                                                class="w-full accent-cyan-400">
                                                        </div>

                                                        <div
                                                            class="rounded-2xl border border-white/10 bg-black/20 p-3">
                                                            <label
                                                                class="mb-2 block text-xs font-bold text-blue-100/55">Border
                                                                style</label>
                                                            <select wire:model.live="fieldBorderStyle"
                                                                class="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-xs text-white outline-none !bg-slate-950 !text-white">
                                                                <option value="solid"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Solid</option>
                                                                <option value="dashed"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Dashed</option>
                                                                <option value="dotted"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Dotted</option>
                                                                <option value="none"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    No border</option>
                                                            </select>
                                                        </div>

                                                        <div
                                                            class="rounded-2xl border border-white/10 bg-black/20 p-3 md:col-span-2">
                                                            <label
                                                                class="mb-2 block text-xs font-bold text-blue-100/55">Shadow
                                                                effect</label>
                                                            <select wire:model.live="fieldShadow"
                                                                class="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-xs text-white outline-none !bg-slate-950 !text-white">
                                                                <option value="none"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    None</option>
                                                                <option value="soft"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Soft</option>
                                                                <option value="medium"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Medium</option>
                                                                <option value="glass"
                                                                    style="background-color: #020617; color: #ffffff;">
                                                                    Glass</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Fonts --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleAppearancePanel('fonts')"
                                    class="flex w-full items-center justify-between gap-3 py-4 text-left {{ !$this->isPremium ? 'opacity-55' : 'cursor-pointer' }}">
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openAppearancePanel === 'fonts' ? 'rotate-90' : '' }}">chevron_right</span>
                                        <span class="text-sm font-black text-white">Fonts</span>
                                    </span>
                                    @if (!$this->isPremium)
                                        <span
                                            class="inline-flex items-center justify-center rounded-full border border-amber-300/20 bg-amber-400/10 p-1.5">
                                            <span class="material-symbols-outlined text-sm text-amber-400">crown</span>
                                        </span>
                                    @endif
                                </button>

                                @if ($this->openAppearancePanel === 'fonts')
                                    <div class="pb-6 pl-7">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Font
                                                    family</label>
                                                <select wire:model.live="fontFamily"
                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none focus:border-cyan-300/40 !bg-slate-950 !text-white"
                                                    style="font-family: '{{ $this->fontFamily }}', sans-serif">
                                                    @foreach ($this->fonts as $value => $label)
                                                        <option value="{{ $value }}"
                                                            style="background-color: #020617; color: #ffffff;">
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Font
                                                    color</label>
                                                <input wire:model.live="textColor" type="color"
                                                    class="h-12 w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-1">
                                            </div>
                                        </div>
                                        <div class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4">
                                            <p class="text-xs font-bold text-blue-100/55">Preview</p>
                                            <p class="mt-2 text-lg"
                                                style="font-family: '{{ $this->fontFamily }}', sans-serif; color: {{ $this->textColor }};">
                                                {{ $this->getFullName() }} — Digital vCard preview
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                    @endif
                </div>

                {{-- 2. Basic Information --}}
                <div
                    class="overflow-hidden rounded-3xl border border-white/10 bg-white/6 shadow-xl shadow-cyan-950/10 backdrop-blur-xl">
                    <button type="button" wire:click="toggleSection('basic')"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 p-4 text-left sm:p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-cyan-400/15 text-cyan-200">
                                <span class="material-symbols-outlined">badge</span>
                            </div>
                            <div>
                                <h2 class="text-base font-black sm:text-lg">2. Basic Information</h2>
                                <p class="text-xs text-blue-100/45">About me, contact information and location.</p>
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined transition {{ $this->openSection === 'basic' ? 'rotate-180' : '' }}">expand_more</span>
                    </button>

                    @if ($this->openSection === 'basic')
                        <div class="border-t border-white/10 p-4 sm:p-5">
                            {{-- About Me --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleBasicPanel('about')"
                                    class="flex w-full items-center gap-3 py-4 text-left cursor-pointer">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openBasicPanel === 'about' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span>
                                        <span class="block text-sm font-black text-white">About me</span>
                                    </span>
                                </button>

                                @if ($this->openBasicPanel === 'about')
                                    <div class="pb-6 pl-7">
                                        <div class="grid gap-5 lg:grid-cols-[240px_1fr]">
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-bold text-blue-100/55">Card
                                                        banner image</label>
                                                    <label
                                                        class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-white/4 px-4 py-5 text-center transition hover:bg-white/8">
                                                        @if ($this->bannerPreview)
                                                            <img src="{{ $this->bannerPreview }}"
                                                                alt="Banner preview"
                                                                class="mb-2 h-24 w-full rounded-xl object-cover shadow-lg">
                                                            <p class="text-xs text-blue-100/55">Click to change banner
                                                            </p>
                                                        @else
                                                            <span
                                                                class="material-symbols-outlined text-4xl text-blue-100/35">panorama</span>
                                                            <p class="mt-2 text-xs font-bold text-blue-100/70">Upload
                                                                banner</p>
                                                            <p class="mt-1 text-[10px] text-blue-100/35">JPG, PNG,
                                                                WebP. Max 5MB</p>
                                                        @endif
                                                        <input wire:model="bannerImage" type="file"
                                                            accept="image/*" class="hidden">
                                                    </label>
                                                    @if ($this->bannerPreview)
                                                        <button type="button" wire:click="removeBanner"
                                                            class="mt-2 w-full rounded-xl border border-white/10 bg-white/4 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-white/8">Remove
                                                            banner</button>
                                                    @endif
                                                    @error('bannerImage')
                                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <label class="mb-1 block text-xs font-bold text-blue-100/55">User
                                                        image</label>
                                                    <label
                                                        class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-white/4 px-4 py-6 text-center transition hover:bg-white/8">
                                                        @if ($this->profilePreview)
                                                            <img src="{{ $this->profilePreview }}"
                                                                alt="Profile preview"
                                                                class="mb-2 h-24 w-24 border-2 border-white/20 object-cover shadow-lg"
                                                                style="border-radius: {{ min(999, max(0, (int) $this->avatarBorderRadius)) }}px;">
                                                            <p class="text-xs text-blue-100/55">Click to change</p>
                                                        @else
                                                            <span
                                                                class="material-symbols-outlined text-4xl text-blue-100/35">person</span>
                                                            <p class="mt-2 text-xs font-bold text-blue-100/70">Upload
                                                                image</p>
                                                            <p class="mt-1 text-[10px] text-blue-100/35">JPG, PNG,
                                                                WebP. Max 2MB</p>
                                                        @endif
                                                        <input wire:model="profileImage" type="file"
                                                            accept="image/*" class="hidden">
                                                    </label>
                                                    @if ($this->profilePreview)
                                                        <button type="button" wire:click="removeProfile"
                                                            class="mt-2 w-full rounded-xl border border-white/10 bg-white/4 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-white/8">Remove
                                                            image</button>
                                                    @endif
                                                    @error('profileImage')
                                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-xs font-bold text-blue-100/55">First
                                                        name</label>
                                                    <input wire:model.live="firstName" type="text"
                                                        placeholder="John"
                                                        class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-bold text-blue-100/55">Last
                                                        name</label>
                                                    <input wire:model.live="lastName" type="text"
                                                        placeholder="Doe"
                                                        class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label
                                                        class="mb-1 block text-xs font-bold text-blue-100/55">Designation</label>
                                                    <input wire:model.live="designation" type="text"
                                                        placeholder="Founder / Software Engineer"
                                                        class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="mb-1 block text-xs font-bold text-blue-100/55">About
                                                        me</label>
                                                    <textarea wire:model.live="aboutMe" rows="3" placeholder="Short intro about you..."
                                                        class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Contact Info --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleBasicPanel('contact')"
                                    class="flex w-full items-center gap-3 py-4 text-left cursor-pointer">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openBasicPanel === 'contact' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span>
                                        <span class="block text-sm font-black text-white">Contact info</span>
                                    </span>
                                </button>

                                @if ($this->openBasicPanel === 'contact')
                                    <div class="pb-6 pl-7">
                                        <div class="space-y-6">
                                            <div>
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <h3 class="text-sm font-black">Phone numbers</h3>
                                                    <button type="button" wire:click="addPhone"
                                                        class="rounded-xl bg-cyan-400/10 px-3 py-2 text-xs font-black text-cyan-100 hover:bg-cyan-400/15">+
                                                        Add phone</button>
                                                </div>
                                                <div class="space-y-3">
                                                    @foreach ($this->phones as $index => $phone)
                                                        <div
                                                            class="grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-3 sm:grid-cols-[130px_minmax(160px,220px)_1fr_1fr_auto]">
                                                            <select wire:model.live="phones.{{ $index }}.type"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none !bg-slate-950 !text-white">
                                                                @foreach ($this->phoneTypes as $key => $type)
                                                                    <option value="{{ $key }}"
                                                                        style="background-color: #020617; color: #ffffff;">
                                                                        {{ $type['label'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <div
                                                                class="rounded-xl">
                                                                <button type="button"
                                                                    @click="openIconPicker('phones.{{ $index }}.icon', 'contact', 'Choose phone icon', '{{ $phone['icon'] ?? $phoneType['icon'] }}')"
                                                                    class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                                    <span class="flex items-center gap-2">
                                                                        <span
                                                                            class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100 ring-1 ring-cyan-300/20">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $phone['icon'] ?? $phoneType['icon'] }}</span>
                                                                        </span>
                                                                        <span>Select icon</span>
                                                                    </span>
                                                                </button>
                                                            </div>
                                                            <input wire:model.live="phones.{{ $index }}.label"
                                                                type="text" placeholder="Label"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <input wire:model.live="phones.{{ $index }}.value"
                                                                type="tel" placeholder="Phone number"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <button type="button"
                                                                wire:click="removePhone({{ $index }})"
                                                                class="rounded-xl border border-red-300/20 bg-red-400/10 px-3 py-2 text-xs font-bold text-red-200">Remove</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div>
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <h3 class="text-sm font-black">Emails</h3>
                                                    <button type="button" wire:click="addEmail"
                                                        class="rounded-xl bg-cyan-400/10 px-3 py-2 text-xs font-black text-cyan-100 hover:bg-cyan-400/15">+
                                                        Add email</button>
                                                </div>
                                                <div class="space-y-3">
                                                    @foreach ($this->emails as $index => $email)
                                                        <div
                                                            class="grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-3 sm:grid-cols-[minmax(160px,220px)_1fr_1fr_auto]">
                                                            <div
                                                                class="rounded-xl">
                                                                <button type="button"
                                                                    @click="openIconPicker('emails.{{ $index }}.icon', 'contact', 'Choose email icon', '{{ $email['icon'] ?? 'mail' }}')"
                                                                    class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                                    <span class="flex items-center gap-2">
                                                                        <span
                                                                            class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100 ring-1 ring-cyan-300/20">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $email['icon'] ?? 'mail' }}</span>
                                                                        </span>
                                                                        <span>Select icon</span>
                                                                    </span>
                                                                </button>
                                                            </div>
                                                            <input wire:model.live="emails.{{ $index }}.label"
                                                                type="text" placeholder="Label"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <input wire:model.live="emails.{{ $index }}.value"
                                                                type="email" placeholder="email@example.com"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <button type="button"
                                                                wire:click="removeEmail({{ $index }})"
                                                                class="rounded-xl border border-red-300/20 bg-red-400/10 px-3 py-2 text-xs font-bold text-red-200">Remove</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div>
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <h3 class="text-sm font-black">Personal sites</h3>
                                                    <button type="button" wire:click="addSite"
                                                        class="rounded-xl bg-cyan-400/10 px-3 py-2 text-xs font-black text-cyan-100 hover:bg-cyan-400/15">+
                                                        Add site</button>
                                                </div>
                                                <div class="space-y-3">
                                                    @foreach ($this->sites as $index => $site)
                                                        <div
                                                            class="grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-3 sm:grid-cols-[minmax(160px,220px)_1fr_1fr_auto]">
                                                            <div
                                                                class="rounded-xl">
                                                                <button type="button"
                                                                    @click="openIconPicker('sites.{{ $index }}.icon', 'contact', 'Choose website icon', '{{ $site['icon'] ?? 'language' }}')"
                                                                    class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                                    <span class="flex items-center gap-2">
                                                                        <span
                                                                            class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100 ring-1 ring-cyan-300/20">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $site['icon'] ?? 'language' }}</span>
                                                                        </span>
                                                                        <span>Select icon</span>
                                                                    </span>
                                                                </button>
                                                            </div>
                                                            <input wire:model.live="sites.{{ $index }}.label"
                                                                type="text" placeholder="Label"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <input wire:model.live="sites.{{ $index }}.value"
                                                                type="url" placeholder="https://example.com"
                                                                class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                            <button type="button"
                                                                wire:click="removeSite({{ $index }})"
                                                                class="rounded-xl border border-red-300/20 bg-red-400/10 px-3 py-2 text-xs font-bold text-red-200">Remove</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Location --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleBasicPanel('location')"
                                    class="flex w-full items-center gap-3 py-4 text-left cursor-pointer">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openBasicPanel === 'location' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span>
                                        <span class="block text-sm font-black text-white">Location</span>
                                    </span>
                                </button>

                                @if ($this->openBasicPanel === 'location')
                                    <div class="pb-6 pl-7">
                                        <div
                                            class="mb-4 grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-4 sm:grid-cols-[180px_1fr]">
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Location
                                                    icon</label>
                                                <div class="rounded-xl">
                                                    <button type="button"
                                                        @click="openIconPicker('locationIcon', 'contact', 'Choose location icon', '{{ $this->locationIcon ?: 'location_on' }}')"
                                                        class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                        <span class="flex items-center gap-2">
                                                            <span
                                                                class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100 ring-1 ring-cyan-300/20">
                                                                <span
                                                                    class="material-symbols-outlined text-xl">{{ $this->locationIcon ?: 'location_on' }}</span>
                                                            </span>
                                                            <span>Select icon</span>
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Location
                                                    label</label>
                                                <input wire:model.live="locationLabel" type="text"
                                                    placeholder="Office / Shop / Location"
                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
                                            {{-- URL --}}
                                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                                <button type="button" wire:click="$set('locationTab', 'url')"
                                                    class="flex w-full items-center gap-3 py-4 text-left">
                                                    <span
                                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->locationTab === 'url' ? 'rotate-90' : '' }}">chevron_right</span>
                                                    <span class="text-sm font-black text-white">Map URL</span>
                                                </button>
                                                @if ($this->locationTab === 'url')
                                                    <div class="pb-5 pl-7">
                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <div class="sm:col-span-2">
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Google
                                                                    Maps / location URL</label>
                                                                <input wire:model.live="locationUrl" type="url"
                                                                    placeholder="https://maps.google.com/..."
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                                @error('locationUrl')
                                                                    <p class="mt-1 text-xs text-red-300">
                                                                        {{ $message }}</p>
                                                                @enderror
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Display/search
                                                                    name</label>
                                                                <input wire:model.live="locationSearch" type="text"
                                                                    placeholder="A.Z Apparels, Dhaka"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Coordinate --}}
                                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                                <button type="button" wire:click="$set('locationTab', 'coordinate')"
                                                    class="flex w-full items-center gap-3 py-4 text-left">
                                                    <span
                                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->locationTab === 'coordinate' ? 'rotate-90' : '' }}">chevron_right</span>
                                                    <span class="text-sm font-black text-white">Coordinate</span>
                                                </button>
                                                @if ($this->locationTab === 'coordinate')
                                                    <div class="pb-5 pl-7">
                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Latitude</label>
                                                                <input wire:model.live="latitude" type="text"
                                                                    placeholder="23.8103"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Longitude</label>
                                                                <input wire:model.live="longitude" type="text"
                                                                    placeholder="90.4125"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Display/search
                                                                    name</label>
                                                                <input wire:model.live="locationSearch" type="text"
                                                                    placeholder="Main branch / warehouse / office"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Manual Address --}}
                                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                                <button type="button" wire:click="$set('locationTab', 'manual')"
                                                    class="flex w-full items-center gap-3 py-4 text-left">
                                                    <span
                                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->locationTab === 'manual' ? 'rotate-90' : '' }}">chevron_right</span>
                                                    <span class="text-sm font-black text-white">Manual address</span>
                                                </button>
                                                @if ($this->locationTab === 'manual')
                                                    <div class="pb-5 pl-7">
                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <div class="sm:col-span-2">
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Street
                                                                    address</label>
                                                                <input wire:model.live="street" type="text"
                                                                    placeholder="House, road, area"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">City</label>
                                                                <input wire:model.live="city" type="text"
                                                                    placeholder="Dhaka"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">State</label>
                                                                <input wire:model.live="state" type="text"
                                                                    placeholder="Dhaka"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">ZIP</label>
                                                                <input wire:model.live="zip" type="text"
                                                                    placeholder="1207"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1 block text-xs font-bold text-blue-100/55">Country</label>
                                                                <input wire:model.live="country" type="text"
                                                                    placeholder="Bangladesh"
                                                                    class="w-full rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- 3. Content --}}
                <div
                    class="overflow-hidden rounded-3xl border border-white/10 bg-white/6 shadow-xl shadow-cyan-950/10 backdrop-blur-xl">
                    <button type="button" wire:click="toggleSection('content')"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 p-4 text-left sm:p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-cyan-400/15 text-cyan-200">
                                <span class="material-symbols-outlined">business_center</span>
                            </div>
                            <div>
                                <h2 class="text-base font-black sm:text-lg">3. Content</h2>
                                <p class="text-xs text-blue-100/45">Companies and professional roles.</p>
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined transition {{ $this->openSection === 'content' ? 'rotate-180' : '' }}">expand_more</span>
                    </button>

                    @if ($this->openSection === 'content')
                        <div class="border-t border-white/10 p-4 sm:p-5">
                            {{-- Companies --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleContentPanel('companies')"
                                    class="flex w-full items-center gap-3 py-4 text-left cursor-pointer">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openContentPanel === 'companies' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span>
                                        <span class="block text-sm font-black text-white">Companies</span>
                                    </span>
                                </button>

                                @if ($this->openContentPanel === 'companies')
                                    <div class="pb-6 pl-7">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <h3 class="text-sm font-black"></h3>
                                            <button type="button" wire:click="addCompany"
                                                class="rounded-xl bg-cyan-400/10 px-3 py-2 text-xs font-black text-cyan-100 hover:bg-cyan-400/15">+
                                                Add company</button>
                                        </div>
                                        <div class="space-y-3">
                                            @foreach ($this->companies as $index => $company)
                                                <div
                                                    class="grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-3 sm:grid-cols-[minmax(160px,220px)_1fr_1fr_auto]">
                                                    <div class="rounded-xl">
                                                        <button type="button"
                                                            @click="openIconPicker('companies.{{ $index }}.icon', 'contact', 'Choose company icon', '{{ $company['icon'] ?? 'business_center' }}')"
                                                            class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                            <span class="flex items-center gap-2">
                                                                <span
                                                                    class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100 ring-1 ring-cyan-300/20">
                                                                    <span
                                                                        class="material-symbols-outlined text-xl">{{ $company['icon'] ?? 'business_center' }}</span>
                                                                </span>
                                                                <span>Select icon</span>
                                                            </span>
                                                        </button>
                                                    </div>
                                                    <input
                                                        wire:model.live="companies.{{ $index }}.company_name"
                                                        type="text" placeholder="Company name"
                                                        class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                    <input wire:model.live="companies.{{ $index }}.profession"
                                                        type="text" placeholder="Profession / role"
                                                        class="rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs text-white outline-none placeholder:text-blue-100/25">
                                                    <button type="button"
                                                        wire:click="removeCompany({{ $index }})"
                                                        title="Remove company" aria-label="Remove company"
                                                        class="grid h-10 w-10 place-items-center rounded-xl border border-red-300/20 bg-red-400/10 text-red-200 transition hover:bg-red-400/15">
                                                        <span class="material-symbols-outlined text-lg">delete</span>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Loading Screen --}}
                            <div class="overflow-hidden border-b border-white/10 last:border-b-0">
                                <button type="button" wire:click="toggleContentPanel('loading')"
                                    class="flex w-full items-center gap-3 py-4 text-left cursor-pointer">
                                    <span
                                        class="material-symbols-outlined text-base text-cyan-200 transition {{ $this->openContentPanel === 'loading' ? 'rotate-90' : '' }}">chevron_right</span>
                                    <span>
                                        <span class="block text-sm font-black text-white">Loading screen</span>
                                        <span class="mt-0.5 block text-[11px] font-semibold text-blue-100/40">Add a
                                            loading image and set loading time before the card opens.</span>
                                    </span>
                                </button>

                                @if ($this->openContentPanel === 'loading')
                                    <div class="pb-6 pl-7">
                                        <div class="grid gap-4 lg:grid-cols-[240px_1fr]">
                                            <div>
                                                <label class="mb-1 block text-xs font-bold text-blue-100/55">Loading
                                                    image</label>
                                                <label
                                                    class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-white/4 px-4 py-6 text-center transition hover:bg-white/8">
                                                    @if ($this->loadingImagePreview)
                                                        <img src="{{ $this->loadingImagePreview }}"
                                                            alt="Loading image preview"
                                                            class="mb-2 h-24 w-24 rounded-2xl object-cover shadow-lg">
                                                        <p class="text-xs text-blue-100/55">Click to change loading
                                                            image</p>
                                                    @else
                                                        <span
                                                            class="material-symbols-outlined text-4xl text-blue-100/35">hourglass_top</span>
                                                        <p class="mt-2 text-xs font-bold text-blue-100/70">Upload
                                                            loading image</p>
                                                        <p class="mt-1 text-[10px] text-blue-100/35">JPG, PNG, WebP or
                                                            animated GIF. Max 2MB</p>
                                                    @endif
                                                    <input wire:model="loadingImage" type="file"
                                                        accept="image/*,.gif,image/gif" class="hidden">
                                                </label>
                                                @if ($this->loadingImagePreview)
                                                    <button type="button" wire:click="removeLoadingImage"
                                                        class="mt-2 w-full rounded-xl border border-white/10 bg-white/4 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-white/8">Remove
                                                        loading image</button>
                                                @endif
                                                @error('loadingImage')
                                                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="space-y-4 rounded-2xl border border-white/10 bg-black/20 p-4">
                                                <label
                                                    class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3">
                                                    <span>
                                                        <span class="block text-xs font-black text-blue-100/70">Enable
                                                            loading screen</span>
                                                        <span class="mt-0.5 block text-[10px] text-blue-100/35">Shows
                                                            before the vCard preview/card opens.</span>
                                                    </span>
                                                    <input wire:model.live="loadingScreenEnabled" type="checkbox"
                                                        class="peer sr-only">
                                                    <span
                                                        class="relative h-6 w-11 rounded-full bg-white/10 transition peer-checked:bg-cyan-400/80 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
                                                </label>

                                                <div>
                                                    <label
                                                        class="mb-1 flex items-center justify-between text-xs font-bold text-blue-100/55">
                                                        <span>Loading time</span>
                                                        <span>{{ $this->loadingTime }}s</span>
                                                    </label>
                                                    <input wire:model.live="loadingTime" type="range"
                                                        min="1" max="10" step="1"
                                                        class="w-full accent-cyan-400">
                                                    @error('loadingTime')
                                                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div
                                                    class="rounded-2xl border border-cyan-300/15 bg-cyan-400/10 p-4 text-xs leading-5 text-cyan-100">
                                                    The preview will replay the loading screen with a live countdown.
                                                    Animated GIF loading images are supported.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                    @endif
                </div>


                {{-- 4. Social Network --}}
                <div
                    class="overflow-hidden rounded-3xl border border-white/10 bg-white/6 shadow-xl shadow-cyan-950/10 backdrop-blur-xl">
                    <button type="button" wire:click="toggleSection('social')"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 p-4 text-left sm:p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-cyan-400/15 text-cyan-200">
                                <span class="material-symbols-outlined">share</span>
                            </div>
                            <div>
                                <h2 class="text-base font-black sm:text-lg">4. Social Network</h2>
                                <p class="text-xs text-blue-100/45">Click a social button, then add the profile URL.
                                </p>
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined transition {{ $this->openSection === 'social' ? 'rotate-180' : '' }}">expand_more</span>
                    </button>

                    @if ($this->openSection === 'social')
                        <div class="border-t border-white/10 p-4 sm:p-5">
                            <div class="mb-5 rounded-3xl border border-white/10 bg-black/20 p-4">
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-wider text-blue-100/60">Social
                                            preview style</p>
                                        <p class="mt-1 text-[11px] text-blue-100/35">Choose icon-only socials, icon
                                            with names, or full contact-card style like phone/email.</p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label
                                            class="inline-flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3">
                                            <span>
                                                <span class="block text-xs font-black text-blue-100/70">Show
                                                    names</span>
                                                <span class="mt-0.5 block text-[10px] text-blue-100/35">Icon + social
                                                    name pill</span>
                                            </span>
                                            <input wire:model.live="showSocialName" type="checkbox"
                                                class="peer sr-only">
                                            <span
                                                class="relative h-6 w-11 rounded-full bg-white/10 transition peer-checked:bg-cyan-400/80 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
                                        </label>

                                        <label
                                            class="inline-flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3">
                                            <span>
                                                <span class="block text-xs font-black text-blue-100/70">Contact card
                                                    style</span>
                                                <span class="mt-0.5 block text-[10px] text-blue-100/35">Show socials
                                                    like phone/email cards</span>
                                            </span>
                                            <input wire:model.live="showSocialAsCards" type="checkbox"
                                                class="peer sr-only">
                                            <span
                                                class="relative h-6 w-11 rounded-full bg-white/10 transition peer-checked:bg-cyan-400/80 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            @if (count($this->socialLinks))
                                <div class="mb-5 space-y-3 rounded-3xl border border-white/10 bg-black/20 p-3">
                                    <div class="flex items-center justify-between gap-3 px-1">
                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wider text-blue-100/60">
                                                Added social links</p>
                                            <p class="mt-1 text-[11px] text-blue-100/35">Paste profile URLs here. If a
                                                social icon is added, its URL is required.</p>
                                        </div>
                                    </div>

                                    @foreach ($this->socialLinks as $platform => $url)
                                        @php
                                            $social = $this->socialOptions[$platform] ?? [
                                                'label' => ucfirst($platform),
                                                'icon_slug' => 'linktree',
                                                'color' => '#ffffff',
                                            ];
                                        @endphp
                                        <div
                                            class="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/60 p-3 sm:grid-cols-[minmax(160px,220px)_1fr_auto]">
                                            <button type="button"
                                                @click="openIconPicker('socialCustomIcons.{{ $platform }}', 'social', 'Choose {{ $social['label'] }} icon', '{{ $this->socialCustomIcons[$platform] ?? ('brand:' . $platform) }}')"
                                                class="flex h-11 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-xs font-bold text-white transition hover:border-cyan-300/40 hover:bg-cyan-400/10">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white shadow-sm">
                                                        @if ($this->socialUsesBrandIcon($platform))
                                                            @php $selectedBrand = $this->socialDisplayBrand($platform); @endphp
                                                            <img src="{{ $this->socialIconUrl($selectedBrand) }}"
                                                                alt="{{ $this->socialOptions[$selectedBrand]['label'] ?? ucfirst($selectedBrand) }} icon"
                                                                class="h-5 w-5 object-contain" loading="lazy">
                                                        @else
                                                            <span class="material-symbols-outlined text-xl" style="color: {{ $this->accentColor }};">{{ $this->socialCustomIcon($platform) }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="truncate">{{ $this->socialUsesBrandIcon($platform) ? $this->socialDisplayLabel($platform) : 'Custom icon' }}</span>
                                                </span>
                                            </button>
                                            <div class="min-w-0">
                                                <input wire:model.live="socialLinks.{{ $platform }}"
                                                    type="url"
                                                    placeholder="Paste {{ $social['label'] }} profile URL"
                                                    aria-label="{{ $social['label'] }} profile URL"
                                                    class="w-full rounded-xl border border-white/10 bg-black/35 px-3 py-2.5 text-xs text-white outline-none placeholder:text-blue-100/25 focus:border-cyan-300/40 @error('socialLinks.' . $platform) border-red-300/60 bg-red-950/20 focus:border-red-300/70 @enderror">
                                                @error('socialLinks.' . $platform)
                                                    <p class="mt-1 text-[11px] font-semibold text-red-300">
                                                        {{ $message }}</p>
                                                @enderror
                                            </div>
                                            <button type="button" wire:click="removeSocial('{{ $platform }}')"
                                                title="Remove {{ $social['label'] }}"
                                                aria-label="Remove {{ $social['label'] }}"
                                                class="grid h-11 w-11 place-items-center rounded-xl border border-red-300/20 bg-red-400/10 text-red-200 transition hover:bg-red-400/15">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <label
                                class="mb-3 block text-xs font-black uppercase tracking-wider text-blue-100/55">Choose
                                social network</label>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8">
                                @foreach ($this->socialOptions as $platform => $social)
                                    <button type="button" wire:click="addSocial('{{ $platform }}')"
                                        title="{{ $social['label'] }}" aria-label="Add {{ $social['label'] }}"
                                        class="group grid h-16 place-items-center rounded-2xl border p-2 transition hover:scale-[1.03] {{ array_key_exists($platform, $this->socialLinks) ? 'border-cyan-400 bg-cyan-400/10 text-cyan-100' : 'border-white/10 bg-white/4 text-blue-100/60 hover:bg-white/8' }}">
                                        <span
                                            class="grid h-10 w-10 place-items-center rounded-2xl bg-white shadow-sm ring-1 ring-white/20 transition group-hover:scale-105">
                                            <img src="{{ $this->socialIconUrl($platform) }}"
                                                alt="{{ $social['label'] }} icon" class="h-5 w-5 object-contain"
                                                loading="lazy">
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Preview and QR --}}
            <div class="min-w-0 lg:sticky lg:top-24 lg:self-start">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/6 shadow-2xl shadow-cyan-950/20 backdrop-blur-xl">
                    <div class="border-b border-white/10 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-black sm:text-lg">Live preview</h2>
                                <p class="mt-1 text-xs text-blue-100/45">Mobile digital card preview.</p>
                            </div>
                            <span
                                class="rounded-full {{ $this->isPremium ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }} px-3 py-1.5 text-xs font-black">
                                {{ $this->isPremium ? 'Premium' : 'Free' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5">
                        <div
                            class="grid min-h-[660px] items-start justify-items-center rounded-3xl bg-slate-950/50 p-3 sm:p-5">
                            @php
                                $activeTemplate =
                                    $this->templates[$this->template] ?? $this->templates['modern-banner-center'];
                                $templateSupportsBanner = (bool) ($activeTemplate['has_banner'] ?? true);
                                $templateUsesProfileAsBanner =
                                    (bool) ($activeTemplate['use_profile_as_banner'] ?? false);
                                $templateHasBanner = $templateSupportsBanner;
                                $avatarPosition = $activeTemplate['avatar_position'] ?? 'center-over-banner';
                                $templateEffect = $activeTemplate['effect'] ?? 'normal';
                                $isWaterGlassPreview = $templateEffect === 'water-glass';
                                $isGlassmorphismPreview = $templateEffect === 'glassmorphism';
                                $isGlassPreview =
                                    $this->fieldShadow === 'glass' ||
                                    in_array($templateEffect, ['glass', 'water-glass', 'glassmorphism'], true);
                                $normalizeHexColor = function (string $color): string {
                                    $color = trim($color);
                                    if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) {
                                        return '#ffffff';
                                    }

                                    return str_starts_with($color, '#') ? $color : '#' . $color;
                                };
                                $isDarkHexColor = function (string $color) use ($normalizeHexColor): bool {
                                    $hex = ltrim($normalizeHexColor($color), '#');
                                    $r = hexdec(substr($hex, 0, 2));
                                    $g = hexdec(substr($hex, 2, 2));
                                    $b = hexdec(substr($hex, 4, 2));

                                    return ($r * 299 + $g * 587 + $b * 114) / 1000 < 145;
                                };
                                $previewCardBg = $normalizeHexColor($this->cardBg);
                                $previewIsDarkCard = $isGlassPreview || $isDarkHexColor($previewCardBg);
                                $previewText = $isGlassPreview ? '#ffffff' : $normalizeHexColor($this->textColor);
                                $previewMuted = $previewIsDarkCard ? 'rgba(255,255,255,0.72)' : '#64748b';
                                $previewFieldBg = $previewIsDarkCard ? 'rgba(255,255,255,0.07)' : '#ffffff';
                                $previewFieldBorderColor = $previewIsDarkCard
                                    ? 'rgba(255,255,255,0.14)'
                                    : $this->fieldBorderColor;
                                $previewTopButtonBg = $previewIsDarkCard ? 'rgba(255,255,255,0.10)' : '#ffffff';
                                $previewTopButtonRing = $previewIsDarkCard ? 'rgba(255,255,255,0.16)' : '#e2e8f0';
                                $avatarRadiusValue = min(56, max(0, (int) $this->avatarBorderRadius));
                                $avatarRadius = $avatarRadiusValue >= 56 ? '999px' : $avatarRadiusValue . 'px';
                                $floatingButtonRadiusValue = min(56, max(0, (int) $this->floatingButtonBorderRadius));
                                $floatingButtonRadius =
                                    $floatingButtonRadiusValue >= 56 ? '999px' : $floatingButtonRadiusValue . 'px';
                                $avatarRingRadius = $avatarRadius;
                                $avatarRingStyle = $this->avatarRingEnabled
                                    ? 'border: ' .
                                    (int) $this->avatarRingWidth .
                                    'px solid ' .
                                    $this->avatarRingColor .
                                    '; border-radius: ' .
                                    $avatarRingRadius .
                                    '; box-shadow: 0 18px 40px rgba(15,23,42,0.22);'
                                    : 'border: 0 solid transparent; box-shadow: 0 18px 40px rgba(15,23,42,0.18);';
                                $floatingButtonPlacementClass = match ($this->floatingButtonPlacement) {
                                    'top-left' => 'top-6 left-6',
                                    'top-right' => 'top-6 right-6',
                                    'bottom-left' => 'bottom-6 left-6',
                                    default => 'bottom-6 right-6',
                                };
                                $floatingButtonRingRadius = match ($this->floatingButtonRingShape) {
                                    'square' => '0px',
                                    'rounded' => '18px',
                                    default => '999px',
                                };
                                $floatingButtonRingStyle = $this->floatingButtonRingEnabled
                                    ? 'box-shadow: 0 24px 60px rgba(15,23,42,0.30), 0 0 0 ' .
                                    (int) $this->floatingButtonRingWidth .
                                    'px ' .
                                    $this->floatingButtonRingColor .
                                    ';'
                                    : 'box-shadow: 0 24px 60px rgba(15,23,42,0.30);';
                                $dragLabelStyle = $isGlassPreview
                                    ? 'background: rgba(34,211,238,0.18); color: #cffafe; border: 1px solid rgba(103,232,249,0.28);'
                                    : 'background: rgba(14,116,144,0.12); color: #0e7490; border: 1px solid rgba(14,116,144,0.22);';
                                $fieldCardClass = 'flex items-center gap-3 border px-3 py-3 transition';
                                $fieldCardShadowClass = match ($this->fieldShadow) {
                                    'none' => '',
                                    'medium' => 'shadow-lg shadow-slate-900/10',
                                    'glass' => 'backdrop-blur-2xl shadow-[0_18px_45px_rgba(15,23,42,0.22)]',
                                    default => 'shadow-sm',
                                };
                                if ($isGlassPreview && $this->fieldShadow !== 'glass') {
                                    $fieldCardShadowClass =
                                        'backdrop-blur-2xl shadow-[0_18px_45px_rgba(15,23,42,0.22)]';
                                }
                                $fieldCardClass .= ' ' . $fieldCardShadowClass;
                                $fieldCardStyle = $isGlassPreview
                                    ? 'border-color: ' .
                                    ($this->fieldBorderStyle === 'none'
                                        ? 'transparent'
                                        : ($isWaterGlassPreview
                                            ? 'rgba(255,255,255,0.42)'
                                            : 'rgba(255,255,255,0.34)')) .
                                    '; border-style: ' .
                                    $this->fieldBorderStyle .
                                    '; border-width: ' .
                                    ($this->fieldBorderStyle === 'none' ? 0 : (int) $this->fieldBorderWidth) .
                                    'px; border-radius: ' .
                                    (int) $this->fieldBorderRadius .
                                    'px;' .
                                    ' background: ' .
                                    ($isWaterGlassPreview
                                        ? 'linear-gradient(135deg, rgba(255,255,255,0.36), rgba(255,255,255,0.12) 45%, rgba(103,232,249,0.10))'
                                        : 'linear-gradient(135deg, rgba(255,255,255,0.24), rgba(255,255,255,0.09))') .
                                    ';' .
                                    ' box-shadow: inset 0 1px 0 rgba(255,255,255,0.42), inset 0 -1px 0 rgba(255,255,255,0.10), 0 18px 45px rgba(15,23,42,0.22);' .
                                    ' -webkit-backdrop-filter: blur(24px) saturate(160%); backdrop-filter: blur(24px) saturate(160%);'
                                    : 'border-color: ' .
                                    ($this->fieldBorderStyle === 'none'
                                        ? 'transparent'
                                        : $previewFieldBorderColor) .
                                    '; border-style: ' .
                                    $this->fieldBorderStyle .
                                    '; border-width: ' .
                                    ($this->fieldBorderStyle === 'none' ? 0 : (int) $this->fieldBorderWidth) .
                                    'px; border-radius: ' .
                                    (int) $this->fieldBorderRadius .
                                    'px; background: ' .
                                    $previewFieldBg .
                                    ';';
                                $glassIconStyle = $isGlassPreview
                                    ? 'background: rgba(255,255,255,0.18); color: #ffffff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.24);'
                                    : 'background: ' .
                                    ($previewIsDarkCard ? 'rgba(255,255,255,0.10)' : $this->accentColor . '18') .
                                    '; color: ' .
                                    $this->accentColor;

                                $bannerImage = $this->bannerPreview;
                                $userImage = $this->profilePreview;
                                $previewBannerImage =
                                    $templateUsesProfileAsBanner && filled($userImage) ? $userImage : $bannerImage;
                                $templateHasBanner = $templateSupportsBanner;
                                $showHeaderAvatar = !($templateUsesProfileAsBanner && filled($userImage));
                                $previewPhones = collect($this->phones)
                                    ->filter(fn($item) => filled($item['value'] ?? ''))
                                    ->values();
                                $previewEmails = collect($this->emails)
                                    ->filter(fn($item) => filled($item['value'] ?? ''))
                                    ->values();
                                $previewSites = collect($this->sites)
                                    ->filter(fn($item) => filled($item['value'] ?? ''))
                                    ->values();
                                $previewCompanies = collect($this->companies)
                                    ->filter(
                                        fn($item) => filled($item['company_name'] ?? '') ||
                                        filled($item['profession'] ?? ''),
                                    )
                                    ->values();
                                $firstPhone = $previewPhones->first();
                                $firstEmail = $previewEmails->first();
                                $locationAddressLine = trim(
                                    implode(
                                        ', ',
                                        array_filter([
                                            $this->street,
                                            $this->city,
                                            $this->state,
                                            $this->zip,
                                            $this->country,
                                        ]),
                                    ),
                                );
                                $coordinatesLine = trim(
                                    $this->latitude .
                                    ($this->latitude && $this->longitude ? ', ' : '') .
                                    $this->longitude,
                                );
                                $activeLocationTab = in_array($this->locationTab, ['url', 'coordinate', 'manual'], true)
                                    ? $this->locationTab
                                    : 'url';

                                if ($activeLocationTab === 'url') {
                                    $locationHref =
                                        $this->locationUrl ?:
                                        ($this->locationSearch
                                            ? 'https://www.google.com/maps/search/?api=1&query=' .
                                            urlencode($this->locationSearch)
                                            : '');
                                    $locationDisplay = $this->locationUrl ? 'Show on map' : $this->locationSearch;
                                    $locationSubText = $this->locationSearch;
                                } elseif ($activeLocationTab === 'coordinate') {
                                    $locationHref = $coordinatesLine
                                        ? 'https://www.google.com/maps/search/?api=1&query=' .
                                        urlencode($coordinatesLine)
                                        : '';
                                    $locationDisplay = $coordinatesLine ? 'Show on map' : $this->locationSearch;
                                    $locationSubText = $this->locationSearch;
                                } else {
                                    $locationHref = $locationAddressLine
                                        ? 'https://www.google.com/maps/search/?api=1&query=' .
                                        urlencode($locationAddressLine)
                                        : '';
                                    $locationDisplay = $locationAddressLine;
                                    $locationSubText = '';
                                }

                                $locationDisplay = trim((string) $locationDisplay);
                                $locationSubText = trim((string) $locationSubText);
                                $hasPreviewContactCards =
                                    $previewPhones->isNotEmpty() ||
                                    $previewEmails->isNotEmpty() ||
                                    $previewSites->isNotEmpty();
                                $hasPreviewLocationCard = filled($locationDisplay);
                                $hasPreviewCompanyCards = $previewCompanies->isNotEmpty();
                                $hasPreviewSocialCards = count(array_filter($this->socialLinks)) > 0;
                                $hasPreviewInfoCards =
                                    $hasPreviewContactCards ||
                                    $hasPreviewLocationCard ||
                                    $hasPreviewCompanyCards ||
                                    $hasPreviewSocialCards;
                                $previewContactSectionOrder = $this->normalizePreviewSectionOrder();
                                $previewOrderMap = array_flip($previewContactSectionOrder);
                                $previewSectionStyle = fn(string $section) => 'order: ' .
                                    (($previewOrderMap[$section] ?? 99) + 1) .
                                    ';';
                            @endphp

                            <div class="w-full max-w-[350px]">
                                <div
                                    class="relative mx-auto rounded-[2.35rem] border border-white/15 bg-white/10 p-2 shadow-2xl shadow-cyan-950/20 ring-1 ring-white/15 backdrop-blur-2xl">

                                    <div wire:key="preview-loading-{{ $this->loadingScreenEnabled ? 'on' : 'off' }}-{{ $this->loadingTime }}-{{ md5((string) $this->loadingImagePreview) }}"
                                        x-data="{
                                            showLoader: @js($this->loadingScreenEnabled),
                                            countDown: {{ max(1, min(10, (int) $this->loadingTime)) }},
                                            totalTime: {{ max(1, min(10, (int) $this->loadingTime)) }},
                                            countTimer: null,
                                            closeTimer: null,
                                            init() {
                                                this.restartLoader();
                                            },
                                            restartLoader() {
                                                clearInterval(this.countTimer);
                                                clearTimeout(this.closeTimer);
                                                this.countDown = this.totalTime;
                                        
                                                if (!this.showLoader) return;
                                        
                                                this.countTimer = setInterval(() => {
                                                    if (this.countDown > 1) {
                                                        this.countDown--;
                                                    }
                                                }, 1000);
                                        
                                                this.closeTimer = setTimeout(() => {
                                                    this.showLoader = false;
                                                    clearInterval(this.countTimer);
                                                    this.countDown = 0;
                                                }, this.totalTime * 1000);
                                            }
                                        }"
                                        class="relative max-h-[620px] overflow-y-auto rounded-[1.9rem] {{ $isGlassPreview ? 'bg-white/10 ring-1 ring-white/25 backdrop-blur-2xl' : '' }} [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                                        style="font-family: '{{ $this->fontFamily }}', sans-serif; {{ $isWaterGlassPreview ? 'background: radial-gradient(circle at 18% 0%, rgba(125,211,252,0.50), transparent 34%), radial-gradient(circle at 90% 16%, rgba(34,211,238,0.30), transparent 30%), linear-gradient(160deg, rgba(8,47,73,0.96), rgba(15,23,42,0.90));' : ($isGlassPreview ? 'background: radial-gradient(circle at 20% 0%, rgba(56,189,248,0.30), transparent 34%), radial-gradient(circle at 90% 12%, rgba(167,139,250,0.32), transparent 30%), linear-gradient(160deg, rgba(15,23,42,0.96), rgba(30,41,59,0.90));' : 'background: ' . $previewCardBg . ';') }}">
                                        @if ($this->loadingScreenEnabled)
                                            <div x-show="showLoader" x-transition.opacity.duration.300ms
                                                class="absolute inset-0 z-[60] grid place-items-center overflow-hidden rounded-[1.9rem] px-8 text-center backdrop-blur-xl"
                                                style="background: linear-gradient(160deg, rgba(2,6,23,0.88), rgba(15,23,42,0.82));">
                                                <div class="relative w-full max-w-[230px]">
                                                    @if ($this->loadingImagePreview)
                                                        <img src="{{ $this->loadingImagePreview }}"
                                                            alt="Loading image"
                                                            class="mx-auto h-24 w-24 rounded-3xl object-cover shadow-2xl shadow-cyan-950/35 ring-1 ring-white/15"
                                                            style="animation: vcardLoadingPulse 1.8s ease-in-out infinite;">
                                                    @else
                                                        <div class="mx-auto h-16 w-16 rounded-full border-4 border-white/15 border-t-cyan-200"
                                                            style="animation: vcardLoadingSpin .9s linear infinite;">
                                                        </div>
                                                    @endif

                                                    <div
                                                        class="mt-6 text-4xl font-black leading-none text-white drop-shadow-lg">
                                                        <span x-text="countDown"></span>
                                                    </div>

                                                    <div
                                                        class="mx-auto mt-5 h-2 w-full overflow-hidden rounded-full bg-white/15 ring-1 ring-white/10">
                                                        <div class="h-full rounded-full bg-linear-to-r from-cyan-300 via-blue-300 to-fuchsia-300 shadow-[0_0_22px_rgba(103,232,249,0.45)]"
                                                            style="animation: vcardLoadingBar {{ max(1, min(10, (int) $this->loadingTime)) }}s linear forwards;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($isGlassPreview)
                                            <div
                                                class="pointer-events-none absolute -left-16 top-28 h-40 w-40 rounded-full bg-cyan-300/20 blur-3xl">
                                            </div>
                                            <div
                                                class="pointer-events-none absolute -right-20 top-64 h-48 w-48 rounded-full bg-fuchsia-300/20 blur-3xl">
                                            </div>
                                            <div
                                                class="pointer-events-none absolute inset-0 rounded-[1.9rem] ring-1 ring-white/10">
                                            </div>

                                            @if ($isWaterGlassPreview)
                                                <div class="pointer-events-none absolute inset-0 z-[1] rounded-[1.9rem]"
                                                    style="background: linear-gradient(180deg, rgba(255,255,255,0.30) 0%, rgba(255,255,255,0.12) 22%, rgba(255,255,255,0.04) 46%, rgba(8,47,73,0.22) 74%, rgba(15,23,42,0.62) 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.42), inset 0 -80px 120px rgba(15,23,42,0.36);">
                                                </div>
                                                <div
                                                    class="pointer-events-none absolute left-6 right-6 top-4 z-[2] h-24 rounded-full bg-white/18 blur-2xl">
                                                </div>
                                            @endif
                                        @endif
                                        {{-- Template Header / Banner / User Image --}}
                                        @if ($templateHasBanner)
                                            <div class="relative h-52 overflow-hidden rounded-t-[1.9rem]"
                                                style="background: linear-gradient(135deg, {{ $this->accentColor }}, #f8fafc);">

                                                @if ($previewBannerImage)
                                                    <img src="{{ $previewBannerImage }}" alt="Card banner"
                                                        class="absolute inset-0 h-full w-full object-cover">
                                                @endif

                                                <div
                                                    class="absolute inset-0 {{ $isWaterGlassPreview ? 'bg-linear-to-b from-white/10 via-cyan-100/5 to-slate-950/80' : ($isGlassPreview ? 'bg-linear-to-b from-black/10 via-transparent to-slate-950/70' : 'bg-linear-to-b from-black/5 via-transparent to-white') }}">
                                                </div>

                                                @if ($showHeaderAvatar && $avatarPosition === 'center-over-banner')
                                                    <div class="absolute inset-x-0 bottom-5 z-10 flex justify-center">
                                                        @if ($userImage)
                                                            <img src="{{ $userImage }}" alt="User image"
                                                                class="h-24 w-24 border-4 border-white object-cover shadow-xl"
                                                                style="{{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                        @else
                                                            <div class="grid h-24 w-24 place-items-center border-4 border-white text-2xl font-black text-white shadow-xl"
                                                                style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                                {{ $this->getInitials() }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif ($showHeaderAvatar && $avatarPosition === 'left-over-banner')
                                                    <div class="absolute bottom-5 left-5 z-10">
                                                        @if ($userImage)
                                                            <img src="{{ $userImage }}" alt="User image"
                                                                class="h-24 w-24 border-4 border-white object-cover shadow-xl"
                                                                style="{{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                        @else
                                                            <div class="grid h-24 w-24 place-items-center border-4 border-white text-2xl font-black text-white shadow-xl"
                                                                style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                                {{ $this->getInitials() }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif ($showHeaderAvatar && $avatarPosition === 'banner-profile-cover')
                                                    <div
                                                        class="absolute inset-0 z-10 flex items-center justify-center">
                                                        <div class="grid h-28 w-28 place-items-center border-4 border-white/90 text-3xl font-black text-white shadow-2xl ring-8 ring-white/10"
                                                            style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                            {{ $this->getInitials() }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="relative rounded-t-[1.9rem] px-5 pt-6 {{ $isGlassPreview ? 'bg-transparent' : '' }}"
                                                style="{{ $isGlassPreview ? '' : 'background: ' . $previewCardBg . ';' }}">
                                                @if ($avatarPosition === 'top-center')
                                                    <div class="flex justify-center">
                                                        @if ($userImage)
                                                            <img src="{{ $userImage }}" alt="User image"
                                                                class="h-28 w-28 border-4 border-white object-cover shadow-xl ring-1 ring-slate-200"
                                                                style="{{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                        @else
                                                            <div class="grid h-28 w-28 place-items-center border-4 border-white text-3xl font-black text-white shadow-xl ring-1 ring-slate-200"
                                                                style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                                {{ $this->getInitials() }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif ($avatarPosition === 'left-inline')
                                                    <div class="flex items-center gap-4">
                                                        @if ($userImage)
                                                            <img src="{{ $userImage }}" alt="User image"
                                                                class="h-20 w-20 object-cover shadow-lg ring-1 ring-slate-200"
                                                                style="{{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                        @else
                                                            <div class="grid h-20 w-20 place-items-center text-2xl font-black text-white shadow-lg ring-1 ring-slate-200"
                                                                style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                                {{ $this->getInitials() }}
                                                            </div>
                                                        @endif

                                                        <div class="min-w-0">
                                                            <p
                                                                class="text-xs font-black uppercase tracking-wider text-slate-400">
                                                                Digital Card</p>
                                                            <p class="truncate text-sm font-bold text-slate-700">
                                                                {{ $this->designation ?: 'Your designation' }}</p>
                                                        </div>
                                                    </div>
                                                @elseif ($avatarPosition === 'square-top')
                                                    <div class="flex justify-center">
                                                        @if ($userImage)
                                                            <img src="{{ $userImage }}" alt="User image"
                                                                class="h-28 w-28 object-cover shadow-xl ring-1 ring-slate-200"
                                                                style="{{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                        @else
                                                            <div class="grid h-28 w-28 place-items-center text-3xl font-black text-white shadow-xl ring-1 ring-slate-200"
                                                                style="background: {{ $this->accentColor }}; {{ $avatarRingStyle }} border-radius: {{ $avatarRadius }};">
                                                                {{ $this->getInitials() }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div
                                            class="relative z-20 flex flex-col px-5 pb-12 {{ $templateHasBanner ? 'pt-4' : 'pt-5' }}">
                                            <div>
                                                <h3 class="text-[22px] font-black leading-tight"
                                                    style="color: {{ $previewText }}">
                                                    {{ $this->getFullName() }}
                                                </h3>

                                                @if ($this->designation)
                                                    <p class="mt-1 text-xs font-semibold"
                                                        style="color: {{ $previewMuted }}">{{ $this->designation }}
                                                    </p>
                                                @endif

                                                @if ($this->aboutMe)
                                                    <p class="mt-4 text-sm leading-6"
                                                        style="color: {{ $previewMuted }}">{{ $this->aboutMe }}</p>
                                                @else
                                                    <p class="mt-4 text-sm leading-6"
                                                        style="color: {{ $previewMuted }}">Add a short introduction
                                                        to make your digital business card feel complete.</p>
                                                @endif
                                            </div>

                                            @if ($this->contactButtonPosition === 'top')
                                                <div class="mt-5 flex items-center gap-3">
                                                    <button type="button"
                                                        class="rounded-full px-5 py-2.5 text-sm font-black shadow-lg"
                                                        style="background: {{ $this->accentColor }}; color: {{ $this->buttonTextColor }}">
                                                        {{ $this->contactButtonText ?: 'add contact' }}
                                                    </button>

                                                    @if ($firstPhone)
                                                        <a href="tel:{{ $firstPhone['value'] }}"
                                                            class="grid h-10 w-10 place-items-center rounded-full text-sm shadow-sm ring-1"
                                                            style="background: {{ $previewTopButtonBg }}; color: {{ $this->accentColor }}; --tw-ring-color: {{ $previewTopButtonRing }};">
                                                            <span class="material-symbols-outlined text-xl">call</span>
                                                        </a>
                                                    @endif

                                                    @if ($firstEmail)
                                                        <a href="mailto:{{ $firstEmail['value'] }}"
                                                            class="grid h-10 w-10 place-items-center rounded-full text-sm shadow-sm ring-1"
                                                            style="background: {{ $previewTopButtonBg }}; color: {{ $this->accentColor }}; --tw-ring-color: {{ $previewTopButtonRing }};">
                                                            <span
                                                                class="material-symbols-outlined text-xl">{{ $email['icon'] ?? 'mail' }}</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif

                                            @if ($hasPreviewContactCards || $hasPreviewLocationCard || $hasPreviewCompanyCards || $hasPreviewSocialCards)
                                                <div class="mt-6 flex flex-col gap-2.5" x-data="{
                                                    dragKey: null,
                                                    order: @js($previewContactSectionOrder),
                                                    reorder(from, to) {
                                                        if (!from || !to || from === to) return;
                                                        const next = [...this.order];
                                                        const fromIndex = next.indexOf(from);
                                                        const toIndex = next.indexOf(to);
                                                        if (fromIndex === -1 || toIndex === -1) return;
                                                        const moved = next.splice(fromIndex, 1)[0];
                                                        next.splice(toIndex, 0, moved);
                                                        this.order = next;
                                                        this.dragKey = null;
                                                        $wire.updatePreviewSectionOrder(next);
                                                    }
                                                }">
                                                    @if ($previewPhones->isNotEmpty())
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('phones') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'phones'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'phones')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag phone section
                                                                </div>
                                                            @endif

                                                            <div class="space-y-2.5">
                                                                @foreach ($previewPhones as $phone)
                                                                    @php
                                                                        $phoneType =
                                                                            $this->phoneTypes[
                                                                                $phone['type'] ?? 'other'
                                                                            ] ?? $this->phoneTypes['other'];
                                                                        $phoneLabel = filled($phone['label'] ?? '')
                                                                            ? $phone['label']
                                                                            : $phoneType['label'];
                                                                    @endphp
                                                                    <div class="{{ $fieldCardClass }}"
                                                                        style="{{ $fieldCardStyle }}">
                                                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                                                            style="{{ $glassIconStyle }}">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $phone['icon'] ?? $phoneType['icon'] }}</span>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <p class="text-[11px] font-black"
                                                                                style="color: {{ $previewMuted }}">
                                                                                {{ $phoneLabel }}</p>
                                                                            <p class="truncate text-sm font-semibold"
                                                                                style="color: {{ $previewText }}">
                                                                                {{ $phone['value'] }}</p>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($previewEmails->isNotEmpty())
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('emails') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'emails'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'emails')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag email section
                                                                </div>
                                                            @endif

                                                            <div class="space-y-2.5">
                                                                @foreach ($previewEmails as $email)
                                                                    <div class="{{ $fieldCardClass }}"
                                                                        style="{{ $fieldCardStyle }}">
                                                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                                                            style="{{ $glassIconStyle }}">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">mail</span>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <p class="text-[11px] font-black"
                                                                                style="color: {{ $previewMuted }}">
                                                                                {{ filled($email['label'] ?? '') ? $email['label'] : 'Email' }}
                                                                            </p>
                                                                            <p class="truncate text-sm font-semibold"
                                                                                style="color: {{ $previewText }}">
                                                                                {{ $email['value'] }}</p>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($previewSites->isNotEmpty())
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('sites') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'sites'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'sites')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag website section
                                                                </div>
                                                            @endif

                                                            <div class="space-y-2.5">
                                                                @foreach ($previewSites as $site)
                                                                    <div class="{{ $fieldCardClass }}"
                                                                        style="{{ $fieldCardStyle }}">
                                                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                                                            style="{{ $glassIconStyle }}">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $site['icon'] ?? 'language' }}</span>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <p class="text-[11px] font-black"
                                                                                style="color: {{ $previewMuted }}">
                                                                                {{ filled($site['label'] ?? '') ? $site['label'] : 'Website' }}
                                                                            </p>
                                                                            <p class="truncate text-sm font-semibold"
                                                                                style="color: {{ $previewText }}">
                                                                                {{ $site['value'] }}</p>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($hasPreviewLocationCard)
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('location') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'location'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'location')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag location section
                                                                </div>
                                                            @endif

                                                            <a href="{{ $locationHref ?: '#' }}" target="_blank"
                                                                class="{{ $fieldCardClass }} text-left transition hover:-translate-y-0.5"
                                                                style="{{ $fieldCardStyle }}">
                                                                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                                                    style="{{ $glassIconStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-xl">{{ $this->locationIcon ?: 'location_on' }}</span>
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <p class="text-[11px] font-black"
                                                                        style="color: {{ $previewMuted }}">
                                                                        {{ $this->locationLabel ?: 'Location' }}</p>
                                                                    <p class="truncate text-sm font-semibold"
                                                                        style="color: {{ $previewText }}">
                                                                        {{ $locationDisplay }}</p>
                                                                    @if ($locationSubText)
                                                                        <p class="mt-0.5 truncate text-[11px] font-semibold"
                                                                            style="color: {{ $previewMuted }}">
                                                                            {{ $locationSubText }}</p>
                                                                    @endif
                                                                </div>
                                                            </a>
                                                        </div>
                                                    @endif

                                                    @if ($hasPreviewCompanyCards)
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('companies') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'companies'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'companies')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag companies section
                                                                </div>
                                                            @endif

                                                            <div class="space-y-2.5">
                                                                @foreach ($previewCompanies as $company)
                                                                    <div class="{{ $fieldCardClass }}"
                                                                        style="{{ $fieldCardStyle }}">
                                                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                                                            style="{{ $glassIconStyle }}">
                                                                            <span
                                                                                class="material-symbols-outlined text-xl">{{ $company['icon'] ?? 'business_center' }}</span>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <p class="truncate text-[11px] font-black"
                                                                                style="color: {{ $previewMuted }}">
                                                                                {{ $company['company_name'] ?? 'Company' }}
                                                                            </p>
                                                                            <p class="truncate text-sm font-semibold"
                                                                                style="color: {{ $previewText }}">
                                                                                {{ $company['profession'] ?? '' }}</p>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($hasPreviewSocialCards)
                                                        <div class="{{ $this->showReorderPanel ? 'cursor-grab rounded-2xl ring-2 ring-cyan-300/35 active:cursor-grabbing' : '' }}"
                                                            style="{{ $previewSectionStyle('social') }}"
                                                            draggable="{{ $this->showReorderPanel ? 'true' : 'false' }}"
                                                            @dragstart="dragKey = 'social'" @dragover.prevent
                                                            @drop="reorder(dragKey, 'social')"
                                                            @dragend="dragKey = null">
                                                            @if ($this->showReorderPanel)
                                                                <div class="mb-1 flex items-center gap-2 rounded-xl px-3 py-1.5 text-[10px] font-bold"
                                                                    style="{{ $dragLabelStyle }}">
                                                                    <span
                                                                        class="material-symbols-outlined text-sm">drag_indicator</span>
                                                                    Drag social section
                                                                </div>
                                                            @endif

                                                            @if ($this->showSocialAsCards)
                                                                <div class="space-y-2.5">
                                                                    @foreach ($this->socialLinks as $platform => $url)
                                                                        @if (filled($url))
                                                                            @php
                                                                                $social = $this->socialOptions[
                                                                                    $platform
                                                                                ] ?? [
                                                                                    'label' => ucfirst($platform),
                                                                                    'icon_slug' => 'linktree',
                                                                                    'color' => $this->accentColor,
                                                                                ];
                                                                            @endphp

                                                                            <a href="{{ $url }}"
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                                class="{{ $fieldCardClass }} text-left transition hover:-translate-y-0.5"
                                                                                style="{{ $fieldCardStyle }}">
                                                                                <div
                                                                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-white/40">
                                                                                    @if ($this->socialUsesBrandIcon($platform))
                                                                                        @php $selectedBrand = $this->socialDisplayBrand($platform); @endphp
                                                                                        <img src="{{ $this->socialIconUrl($selectedBrand) }}"
                                                                                            alt="{{ $this->socialOptions[$selectedBrand]['label'] ?? ucfirst($selectedBrand) }} icon"
                                                                                            class="h-5 w-5 object-contain" loading="lazy">
                                                                                    @else
                                                                                        <span class="material-symbols-outlined text-xl" style="color: {{ $this->accentColor }};">{{ $this->socialCustomIcon($platform) }}</span>
                                                                                    @endif
                                                                                </div>
                                                                                <div class="min-w-0">
                                                                                    <p class="text-[11px] font-black"
                                                                                        style="color: {{ $previewMuted }}">
                                                                                        {{ $this->socialDisplayLabel($platform) }}</p>
                                                                                    <p class="truncate text-sm font-semibold"
                                                                                        style="color: {{ $previewText }}">
                                                                                        {{ $url }}</p>
                                                                                </div>
                                                                            </a>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div class="flex flex-wrap justify-center gap-3">
                                                                    @foreach ($this->socialLinks as $platform => $url)
                                                                        @if (filled($url))
                                                                            @php
                                                                                $social = $this->socialOptions[
                                                                                    $platform
                                                                                ] ?? [
                                                                                    'label' => ucfirst($platform),
                                                                                    'icon_slug' => 'linktree',
                                                                                    'color' => $this->accentColor,
                                                                                ];
                                                                            @endphp

                                                                            @if ($this->showSocialName)
                                                                                <a href="{{ $url }}"
                                                                                    target="_blank"
                                                                                    rel="noopener noreferrer"
                                                                                    class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-2 text-[11px] font-medium tracking-normal shadow-sm ring-1 ring-white/55 backdrop-blur-xl transition hover:-translate-y-0.5"
                                                                                    style="color: {{ $previewText }}">
                                                                                    @if ($this->socialUsesBrandIcon($platform))
                                                                                        @php $selectedBrand = $this->socialDisplayBrand($platform); @endphp
                                                                                        <img src="{{ $this->socialIconUrl($selectedBrand) }}"
                                                                                            alt="{{ $this->socialOptions[$selectedBrand]['label'] ?? ucfirst($selectedBrand) }} icon"
                                                                                            class="h-4 w-4 object-contain" loading="lazy">
                                                                                    @else
                                                                                        <span class="material-symbols-outlined text-base" style="color: {{ $this->accentColor }};">{{ $this->socialCustomIcon($platform) }}</span>
                                                                                    @endif
                                                                                    <span
                                                                                        class="leading-none opacity-80">{{ $this->socialDisplayLabel($platform) }}</span>
                                                                                </a>
                                                                            @else
                                                                                <a href="{{ $url }}"
                                                                                    target="_blank"
                                                                                    rel="noopener noreferrer"
                                                                                    class="grid h-10 w-10 place-items-center rounded-full bg-white/80 shadow-sm ring-1 ring-white/60 backdrop-blur-xl transition hover:-translate-y-0.5">
                                                                                    @if ($this->socialUsesBrandIcon($platform))
                                                                                        @php $selectedBrand = $this->socialDisplayBrand($platform); @endphp
                                                                                        <img src="{{ $this->socialIconUrl($selectedBrand) }}"
                                                                                            alt="{{ $this->socialOptions[$selectedBrand]['label'] ?? ucfirst($selectedBrand) }} icon"
                                                                                            class="h-5 w-5 object-contain" loading="lazy">
                                                                                    @else
                                                                                        <span class="material-symbols-outlined text-xl" style="color: {{ $this->accentColor }};">{{ $this->socialCustomIcon($platform) }}</span>
                                                                                    @endif
                                                                                </a>
                                                                            @endif
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                        </div>
                                    </div>

                                    @if ($this->contactButtonPosition === 'floating')
                                        <div
                                            class="absolute {{ $floatingButtonPlacementClass }} z-30 grid h-14 w-14 place-items-center transition hover:scale-105">
                                            @if ($this->floatingButtonRingEnabled && $this->floatingButtonRingWidth > 0)
                                                <span class="pointer-events-none absolute"
                                                    style="inset: -{{ (int) $this->floatingButtonRingWidth }}px; border: {{ (int) $this->floatingButtonRingWidth }}px solid {{ $this->floatingButtonRingColor }}; border-radius: {{ $floatingButtonRingRadius }}; box-shadow: 0 24px 60px rgba(15,23,42,0.30);"></span>
                                            @endif

                                            <button type="button"
                                                class="relative grid h-14 w-14 place-items-center shadow-2xl transition"
                                                style="background: {{ $this->accentColor }}; color: {{ $this->buttonTextColor }}; border-radius: {{ $floatingButtonRadius }};"
                                                title="{{ $this->contactButtonText }}"
                                                aria-label="{{ $this->contactButtonText }}">
                                                <span class="material-symbols-outlined text-2xl">person_add</span>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="button" wire:click="toggleReorderPanel"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/8 px-5 py-3 text-xs font-black text-cyan-100 shadow-lg transition hover:-translate-y-0.5 hover:bg-white/12">
                                <span class="material-symbols-outlined text-base">drag_indicator</span>
                                {{ $this->showReorderPanel ? 'Drag mode enabled' : 'Reorder Contact & Social Info' }}
                            </button>

                            @if ($this->showReorderPanel)
                                <div
                                    class="mt-3 rounded-3xl border border-cyan-300/20 bg-cyan-400/10 p-4 backdrop-blur-xl">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-black text-white">Drag sections inside preview
                                            </h3>
                                            <p class="mt-1 text-xs leading-5 text-blue-100/55">
                                                Drag Phone, Email, Website, Location, Companies, or Social Links
                                                directly inside the
                                                mobile preview. Name/about and contact button stay fixed.
                                            </p>
                                        </div>
                                        <button type="button" wire:click="toggleReorderPanel"
                                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/5 text-blue-100/70 hover:bg-white/10">
                                            <span class="material-symbols-outlined text-base">close</span>
                                        </button>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                                        @foreach ($this->normalizePreviewSectionOrder() as $section)
                                            @php $sectionMeta = $this->previewSectionLabels[$section] ?? ['label' => ucfirst($section), 'icon' => 'drag_indicator']; @endphp
                                            <div
                                                class="flex items-center gap-2 rounded-2xl border border-white/10 bg-black/20 px-3 py-2 text-[10px] font-bold text-blue-100/75">
                                                <span
                                                    class="material-symbols-outlined text-sm text-cyan-100">{{ $sectionMeta['icon'] }}</span>
                                                <span class="truncate">{{ $sectionMeta['label'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        @error('vcf')
                            <p class="mt-3 text-center text-xs font-semibold text-red-300">{{ $message }}</p>
                        @enderror

                        @error('vcard')
                            <p class="mt-3 text-center text-xs font-semibold text-red-300">{{ $message }}</p>
                        @enderror

                        @if (session('vcard_saved'))
                            <p
                                class="mt-3 rounded-2xl bg-emerald-400/10 px-4 py-3 text-center text-xs font-bold text-emerald-200">
                                {{ session('vcard_saved') }}</p>
                        @endif

                        @if (session('vcard_deleted'))
                            <p
                                class="mt-3 rounded-2xl bg-red-400/10 px-4 py-3 text-center text-xs font-bold text-red-200">
                                {{ session('vcard_deleted') }}</p>
                        @endif

                        <div class="mt-5 rounded-3xl border border-white/10 bg-black/20 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black">QR & Downloads</h3>
                                    <p class="mt-1 text-xs text-blue-100/45">Save the dynamic vCard and download QR.
                                    </p>
                                </div>
                            </div>

                            @if ($this->qrSvg)
                                <div id="vcard-qr-svg"
                                    data-logo-url="{{ $this->qrDisplayLogoUrl ?? '' }}"
                                    class="relative mt-4 grid place-items-center rounded-2xl bg-white p-4 [&_svg]:h-64 [&_svg]:w-64">
                                    {!! $this->qrSvg !!}

                                    @if ($this->qrDisplayLogoUrl)
                                        <div class="pointer-events-none absolute left-1/2 top-1/2 flex h-20 w-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-2xl bg-white p-2 shadow-lg">
                                            <img src="{{ $this->qrDisplayLogoUrl }}" alt="QR center logo preview"
                                                class="max-h-full max-w-full rounded-xl object-contain">
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-4 space-y-3">
                                @if ($this->isPremium)
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-[11px] font-black uppercase tracking-wider text-blue-100/55">QR logo</p>
                                                <p class="mt-0.5 text-[10px] text-blue-100/35">No logo by default. Upload one only when needed.</p>
                                            </div>

                                            <div class="inline-flex shrink-0 rounded-xl border border-white/10 bg-black/25 p-1">
                                                <button type="button"
                                                    wire:click="$set('qrLogoMode', 'none')"
                                                    class="rounded-lg px-2.5 py-1.5 text-[10px] font-black transition {{ $this->qrLogoMode === 'none' ? 'bg-cyan-400/15 text-cyan-100 ring-1 ring-cyan-300/30' : 'text-blue-100/55 hover:text-white' }}">
                                                    No Logo
                                                </button>

                                                <button type="button"
                                                    wire:click="$set('qrLogoMode', 'custom')"
                                                    class="rounded-lg px-2.5 py-1.5 text-[10px] font-black transition {{ $this->qrLogoMode === 'custom' ? 'bg-cyan-400/15 text-cyan-100 ring-1 ring-cyan-300/30' : 'text-blue-100/55 hover:text-white' }}">
                                                    Upload Logo
                                                </button>
                                            </div>
                                        </div>

                                        @if ($this->qrLogoMode === 'custom')
                                            <label class="mt-3 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-white/15 bg-black/20 p-3 transition hover:bg-white/8">
                                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-cyan-400/10 text-cyan-100">
                                                    <span class="material-symbols-outlined text-xl">add_photo_alternate</span>
                                                </span>
                                                <div class="min-w-0 text-left">
                                                    @if ($this->qrLogoPreview)
                                                        <p class="text-xs font-bold text-white">Logo selected</p>
                                                        <p class="mt-0.5 text-[10px] text-blue-100/40">Preview is shown in the center of the QR · Click to replace</p>
                                                    @else
                                                        <p class="text-xs font-bold text-white">Choose a center logo</p>
                                                        <p class="mt-0.5 text-[10px] text-blue-100/40">PNG or JPG · Max 1MB</p>
                                                    @endif
                                                </div>

                                                <input wire:model="qrLogo" type="file" accept="image/png,image/jpeg,image/jpg" class="hidden">
                                            </label>

                                            @if ($this->qrLogoPreview)
                                                <button type="button" wire:click="removeQrLogo"
                                                    class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[10px] font-bold text-red-300 transition hover:bg-red-400/10">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                    Remove logo
                                                </button>
                                            @endif

                                            @error('qrLogo')
                                                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
                                            @enderror
                                        @endif
                                    </div>
                                @else
                                    <div class="rounded-2xl border border-amber-300/15 bg-amber-400/10 p-4 text-xs leading-5 text-amber-100">
                                        Free users automatically get the site logo inside the QR and 10 scans per week. Premium QR codes have no logo by default and can use an uploaded custom logo.
                                    </div>
                                @endif

                                <button type="button" wire:click="saveVcard"
                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-cyan-500 to-blue-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-cyan-500/20 transition hover:-translate-y-0.5">
                                    <span class="material-symbols-outlined text-base">save</span>
                                    Save vCard & Generate QR
                                </button>

                                @if ($this->savedVcard && $this->qrSvg)
                                    <button type="button"
                                        @click="downloadQrPng(@js(Str::slug($this->savedVcard->full_name ?? $this->getFullName()) ?: 'vcard-qr'))"
                                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/6 px-5 py-3.5 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-white/10">
                                        <span class="material-symbols-outlined text-base">download</span>
                                        Download QR Code PNG
                                    </button>
                                @endif

                                {{-- <button type="button" wire:click="generateVcf"
                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/6 px-5 py-3.5 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-white/10">
                                    <span class="material-symbols-outlined text-base">download</span>
                                    Download VCF
                                </button> --}}

                                @if ($this->savedVcard)
                                    <div x-data="{ confirmDelete: false }">
                                        <template x-if="!confirmDelete">
                                            <button type="button" @click="confirmDelete = true"
                                                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-red-400/20 bg-red-400/10 px-5 py-3.5 text-sm font-black text-red-200 transition hover:-translate-y-0.5 hover:bg-red-400/20">
                                                <span class="material-symbols-outlined text-base">delete</span>
                                                Delete vCard
                                            </button>
                                        </template>
                                        <template x-if="confirmDelete">
                                            <div class="flex items-center gap-2">
                                                <button type="button" wire:click="deleteVcard({{ $this->savedVcard->id }})"
                                                    class="inline-flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-red-500 px-5 py-3.5 text-sm font-black text-white transition hover:-translate-y-0.5">
                                                    <span class="material-symbols-outlined text-base">delete</span>
                                                    Confirm Delete
                                                </button>
                                                <button type="button" @click="confirmDelete = false"
                                                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/6 px-4 py-3.5 text-sm font-black text-white transition hover:bg-white/10">
                                                    <span class="material-symbols-outlined text-base">close</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Icon picker modal --}}
    <div x-show="iconPicker.open" x-transition.opacity
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur-xl"
        style="display: none;">
        <div class="absolute inset-0" @click="closeIconPicker()"></div>
        <div x-show="iconPicker.open" x-transition.scale.origin.center
            class="relative w-full max-w-2xl overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950 shadow-2xl shadow-cyan-950/40">
            <div class="border-b border-white/10 bg-white/[0.03] p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-cyan-100/60">Icon library
                        </p>
                        <h3 class="mt-1 text-lg font-black text-white" x-text="iconPicker.title"></h3>
                    </div>
                    <button type="button" @click="closeIconPicker()"
                        class="grid h-10 w-10 place-items-center rounded-2xl border border-white/10 bg-white/5 text-blue-100/70 transition hover:bg-white/10 hover:text-white">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
            </div>

            <div class="max-h-[62vh] overflow-y-auto p-5">
                <div x-show="iconPicker.type === 'contact'"
                    class="grid grid-cols-5 gap-2 sm:grid-cols-7 md:grid-cols-10">
                    @foreach ($this->contactIconOptions as $icon => $label)
                        <button type="button" @click="chooseIcon('{{ $icon }}')"
                            title="{{ $label }}"
                            class="group relative grid h-12 w-full place-items-center rounded-xl border text-blue-100/70 transition hover:border-cyan-300/50 hover:bg-cyan-400/10 hover:text-cyan-100"
                            :class="iconPicker.selected === '{{ $icon }}' ?
                                'border-cyan-300/70 bg-cyan-400/15 text-cyan-100 ring-1 ring-cyan-300/25' :
                                'border-white/10 bg-white/5'">
                            <span class="material-symbols-outlined text-2xl">{{ $icon }}</span>
                            <span x-show="iconPicker.selected === '{{ $icon }}'"
                                class="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-cyan-300 text-[10px] text-slate-950 shadow-lg">
                                <span class="material-symbols-outlined text-[12px]">check</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <div x-show="iconPicker.type === 'social'">
                    <div class="mb-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-cyan-100/55">General icons</p>
                        <p class="mt-1 text-[10px] leading-4 text-blue-100/35">Includes official colored social icons from Simple Icons and general Material Symbols.</p>
                    </div>

                    <div class="grid grid-cols-5 gap-2 sm:grid-cols-7 md:grid-cols-10">
                        @foreach ($this->socialOptions as $brand => $social)
                            <button type="button" @click="chooseIcon('brand:{{ $brand }}')"
                                title="{{ $social['label'] }}"
                                aria-label="Choose {{ $social['label'] }} icon"
                                class="group relative grid h-12 w-full place-items-center rounded-xl border transition hover:border-cyan-300/50 hover:bg-cyan-400/10"
                                :class="iconPicker.selected === 'brand:{{ $brand }}' ?
                                    'border-cyan-300/70 bg-cyan-400/15 ring-1 ring-cyan-300/25' :
                                    'border-white/10 bg-white/5'">
                                <span class="grid h-8 w-8 place-items-center rounded-lg bg-white shadow-sm">
                                    <img src="{{ $this->socialIconUrl($brand) }}" alt="{{ $social['label'] }} icon"
                                        class="h-5 w-5 object-contain" loading="lazy">
                                </span>
                                <span x-show="iconPicker.selected === 'brand:{{ $brand }}'"
                                    class="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-cyan-300 text-[10px] text-slate-950 shadow-lg">
                                    <span class="material-symbols-outlined text-[12px]">check</span>
                                </span>
                            </button>
                        @endforeach

                        @foreach ($this->socialCustomIconOptions as $icon => $label)
                            <button type="button" @click="chooseIcon('{{ $icon }}')"
                                title="{{ $label }}"
                                aria-label="Choose {{ $label }} icon"
                                class="group relative grid h-12 w-full place-items-center rounded-xl border text-blue-100/70 transition hover:border-cyan-300/50 hover:bg-cyan-400/10 hover:text-cyan-100"
                                :class="iconPicker.selected === '{{ $icon }}' ?
                                    'border-cyan-300/70 bg-cyan-400/15 text-cyan-100 ring-1 ring-cyan-300/25' :
                                    'border-white/10 bg-white/5'">
                                <span class="material-symbols-outlined text-2xl">{{ $icon }}</span>
                                <span x-show="iconPicker.selected === '{{ $icon }}'"
                                    class="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-cyan-300 text-[10px] text-slate-950 shadow-lg">
                                    <span class="material-symbols-outlined text-[12px]">check</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
