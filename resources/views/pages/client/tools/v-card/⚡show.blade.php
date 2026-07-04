<?php

use App\Models\SiteSetting;
use App\Models\Vcard;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.vcard')] class extends Component {
    public Vcard $vcard;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(string $slug): void
    {
        $vcard = Vcard::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $this->vcard = $vcard;
        $this->data = $this->resolveVcardData($vcard);

        $this->recordScan();
    }

    public function title(): string
    {
        return $this->fullName;
    }

    private function recordScan(): void
    {
        $this->vcard->scans()->create([
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
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

    public function socialUsesBrandIcon(string $platform): bool
    {
        $value = (string) (($this->data['socialCustomIcons'][$platform] ?? null) ?: ('brand:' . $platform));

        return str_starts_with($value, 'brand:');
    }

    public function socialDisplayBrand(string $platform): string
    {
        $value = (string) (($this->data['socialCustomIcons'][$platform] ?? null) ?: ('brand:' . $platform));

        if (!str_starts_with($value, 'brand:')) {
            return $platform;
        }

        $brand = substr($value, 6);

        return isset($this->socialOptions[$brand]) ? $brand : $platform;
    }

    public function socialDisplayLabel(string $platform): string
    {
        $brand = $this->socialDisplayBrand($platform);

        return $this->socialOptions[$brand]['label'] ?? ucfirst($brand);
    }

    public function socialMaterialIcon(string $platform): string
    {
        $value = (string) (($this->data['socialCustomIcons'][$platform] ?? null) ?: 'share');

        return str_starts_with($value, 'brand:') ? 'share' : $value;
    }

    public function downloadVcf(): \Illuminate\Http\Response
    {
        $vcf = $this->buildVcf();
        $filename = Str::slug($this->fullName) ?: 'contact';

        return response($vcf, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.vcf"',
        ]);
    }

    public function getFullNameProperty(): string
    {
        return trim(($this->data['firstName'] ?? '') . ' ' . ($this->data['lastName'] ?? '')) ?: 'Contact';
    }

    public function getInitialsProperty(): string
    {
        $first = strtoupper(substr($this->data['firstName'] ?? '', 0, 1));
        $last = strtoupper(substr($this->data['lastName'] ?? '', 0, 1));

        return ($first . $last) ?: '?';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveVcardData(Vcard $vcard): array
    {
        return [
            'template' => $this->getStoredValue($vcard, 'template', $vcard->card_style ?? 'modern-banner-center'),
            'theme' => $vcard->theme ?? 'modern-banner-center',
            'fontFamily' => $vcard->font_family ?? 'Poppins',
            'accentColor' => $vcard->accent_color ?? '#06b6d4',
            'bgColor' => $vcard->bg_color ?? '#0f172a',
            'textColor' => $vcard->text_color ?? '#ffffff',
            'cardBg' => $vcard->card_bg ?? '#1e293b',
            'buttonTextColor' => $this->getStoredValue($vcard, 'button_text_color', '#ffffff'),
            'firstName' => $vcard->first_name ?? '',
            'lastName' => $vcard->last_name ?? '',
            'designation' => $this->getStoredValue($vcard, 'designation', $vcard->job_title ?? ''),
            'aboutMe' => $this->getStoredValue($vcard, 'about_me', $vcard->note ?? ''),
            'phones' => $this->getStoredArray($vcard, 'phones', array_values(array_filter([
                $vcard->phone_mobile ? ['type' => 'mobile', 'label' => 'Mobile', 'value' => $vcard->phone_mobile, 'icon' => 'call'] : null,
                $vcard->phone_work ? ['type' => 'work', 'label' => 'Work', 'value' => $vcard->phone_work, 'icon' => 'work'] : null,
            ]))),
            'emails' => $this->getStoredArray($vcard, 'emails', array_values(array_filter([
                $vcard->email ? ['label' => 'Email', 'value' => $vcard->email, 'icon' => 'mail'] : null,
            ]))),
            'sites' => $this->getStoredArray($vcard, 'sites', array_values(array_filter([
                $vcard->website ? ['label' => 'Website', 'value' => $vcard->website, 'icon' => 'language'] : null,
            ]))),
            'street' => $this->getStoredValue($vcard, 'street', ''),
            'city' => $this->getStoredValue($vcard, 'city', ''),
            'state' => $this->getStoredValue($vcard, 'state', ''),
            'zip' => $this->getStoredValue($vcard, 'zip', ''),
            'country' => $this->getStoredValue($vcard, 'country', ''),
            'locationLabel' => $this->getStoredValue($vcard, 'location_label', 'Location'),
            'locationIcon' => $this->getStoredValue($vcard, 'location_icon', 'location_on'),
            'locationUrl' => $this->getStoredValue($vcard, 'location_url', ''),
            'latitude' => $this->getStoredValue($vcard, 'latitude', ''),
            'longitude' => $this->getStoredValue($vcard, 'longitude', ''),
            'companies' => $this->getStoredArray($vcard, 'companies', array_values(array_filter([
                ($vcard->company || $vcard->job_title) ? ['company_name' => $vcard->company ?? '', 'profession' => $vcard->job_title ?? '', 'icon' => 'business_center'] : null,
            ]))),
            'socialLinks' => array_filter(array_merge(
                $this->getStoredArray($vcard, 'social_links', []),
                array_filter([
                    'facebook' => $this->getStoredValue($vcard, 'facebook', ''),
                    'linkedin' => $this->getStoredValue($vcard, 'linkedin', ''),
                    'twitter' => $this->getStoredValue($vcard, 'twitter', ''),
                    'instagram' => $this->getStoredValue($vcard, 'instagram', ''),
                ], fn($v) => filled($v)),
            )),
            'showSocialName' => (bool) $this->getStoredValue($vcard, 'show_social_name', false),
            'showSocialAsCards' => (bool) $this->getStoredValue($vcard, 'show_social_as_cards', false),
            'socialIconMode' => $this->getStoredArray($vcard, 'social_icon_mode', []),
            'socialCustomIcons' => $this->getStoredArray($vcard, 'social_custom_icons', []),
            'avatarRingEnabled' => (bool) $this->getStoredValue($vcard, 'avatar_ring_enabled', true),
            'avatarRingColor' => $this->getStoredValue($vcard, 'avatar_ring_color', '#ffffff'),
            'avatarRingWidth' => min(12, max(0, (int) $this->getStoredValue($vcard, 'avatar_ring_width', 4))),
            'floatingButtonRingEnabled' => (bool) $this->getStoredValue($vcard, 'floating_button_ring_enabled', true),
            'floatingButtonRingColor' => $this->getStoredValue($vcard, 'floating_button_ring_color', '#ffffff'),
            'floatingButtonRingWidth' => min(12, max(0, (int) $this->getStoredValue($vcard, 'floating_button_ring_width', 4))),
            'floatingButtonRingShape' => $this->getStoredValue($vcard, 'floating_button_ring_shape', 'circle'),
            'floatingButtonPlacement' => $this->getStoredValue($vcard, 'floating_button_placement', 'bottom-right'),
            'floatingButtonBorderRadius' => min(56, max(0, (int) $this->getStoredValue($vcard, 'floating_button_border_radius', 56))),
            'avatarBorderRadius' => min(56, max(0, (int) $this->getStoredValue($vcard, 'avatar_border_radius', 56))),
            'fieldBorderColor' => $this->getStoredValue($vcard, 'field_border_color', '#e2e8f0'),
            'fieldBorderRadius' => (int) $this->getStoredValue($vcard, 'field_border_radius', 12),
            'fieldBorderWidth' => min(10, max(0, (int) $this->getStoredValue($vcard, 'field_border_width', 1))),
            'fieldBorderStyle' => $this->getStoredValue($vcard, 'field_border_style', 'solid'),
            'fieldShadow' => $this->getStoredValue($vcard, 'field_shadow', 'soft'),
            'contactButtonText' => $this->getStoredValue($vcard, 'contact_button_text', 'Save Contact'),
            'contactButtonPosition' => $this->getStoredValue($vcard, 'contact_button_position', 'top'),
            'bannerPath' => $vcard->banner_path,
            'profilePath' => $vcard->profile_path,
            'previewSectionOrder' => $this->getStoredArray($vcard, 'preview_section_order', ['phones', 'emails', 'sites', 'location', 'companies', 'social']),
            'loadingPath' => $this->getStoredValue($vcard, 'loading_path', null),
            'loadingScreenEnabled' => (bool) $this->getStoredValue($vcard, 'loading_screen_enabled', false),
            'loadingTime' => min(10, max(1, (int) $this->getStoredValue($vcard, 'loading_time', 2))),
        ];
    }

    private function getStoredValue(Vcard $vcard, string $column, mixed $default = null): mixed
    {
        return Schema::hasColumn('vcards', $column) && isset($vcard->{$column}) ? $vcard->{$column} : $default;
    }

    /**
     * @return array<int, mixed>
     */
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

    private function buildVcf(): string
    {
        $d = $this->data;
        $fullName = $this->escapeVcf($this->fullName);
        $firstName = $this->escapeVcf($d['firstName'] ?? '');
        $lastName = $this->escapeVcf($d['lastName'] ?? '');
        $primaryCompany = collect($d['companies'] ?? [])->first(fn($item) => filled($item['company_name'] ?? '') || filled($item['profession'] ?? '')) ?? ['company_name' => '', 'profession' => ''];

        $vcf = "BEGIN:VCARD\r\n";
        $vcf .= "VERSION:3.0\r\n";
        $vcf .= "FN:{$fullName}\r\n";
        $vcf .= "N:{$lastName};{$firstName};;;\r\n";

        if (filled($primaryCompany['company_name'] ?? '')) {
            $vcf .= 'ORG:' . $this->escapeVcf($primaryCompany['company_name']) . "\r\n";
        }

        if (filled($d['designation'] ?? '')) {
            $vcf .= 'TITLE:' . $this->escapeVcf($d['designation']) . "\r\n";
        }

        foreach ($d['emails'] ?? [] as $email) {
            if (filled($email['value'] ?? '')) {
                $vcf .= 'EMAIL;TYPE=' . strtoupper($this->escapeVcf($email['label'] ?? 'Email')) . ':' . $this->escapeVcf($email['value']) . "\r\n";
            }
        }

        foreach ($d['phones'] ?? [] as $phone) {
            if (filled($phone['value'] ?? '')) {
                $type = strtoupper($phone['type'] ?? 'other');
                $vcf .= "TEL;TYPE={$type}:" . $this->escapeVcf($phone['value']) . "\r\n";
            }
        }

        foreach ($d['sites'] ?? [] as $site) {
            if (filled($site['value'] ?? '')) {
                $vcf .= 'URL:' . $this->escapeVcf($site['value']) . "\r\n";
            }
        }

        if (filled($d['aboutMe'] ?? '')) {
            $vcf .= 'NOTE:' . $this->escapeVcf($d['aboutMe']) . "\r\n";
        }

        $parts = array_filter([$d['street'] ?? '', $d['city'] ?? '', $d['state'] ?? '', $d['zip'] ?? '', $d['country'] ?? '']);
        if (count($parts) > 0) {
            $address = ';;' . implode(';', array_map(fn($v) => $this->escapeVcf($v), array_values($parts)));
            $vcf .= "ADR;TYPE=WORK:{$address}\r\n";
        }

        foreach ($d['socialLinks'] ?? [] as $platform => $url) {
            if (filled($url)) {
                $vcf .= 'X-SOCIALPROFILE;TYPE=' . strtoupper((string) $platform) . ':' . $this->escapeVcf($url) . "\r\n";
            }
        }

        $vcf .= "END:VCARD\r\n";

        return $vcf;
    }
};
?>

@push('head')
    @if (filled($data['fontFamily'] ?? ''))
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family={{ str_replace('%2B', '+', urlencode($data['fontFamily'])) }}:wght@300;400;500;600;700;800;900&display=swap"
            rel="stylesheet">
    @endif
    <meta name="description" content="Digital business card for {{ $this->fullName }}">
@endpush

<style>
    @keyframes publicVcardLoadingBar {
        from {
            width: 0
        }

        to {
            width: 100%
        }
    }

    @keyframes publicVcardLoadingPulse {

        0%,
        100% {
            transform: scale(.94);
            opacity: .72
        }

        50% {
            transform: scale(1.04);
            opacity: 1
        }
    }

    @keyframes publicVcardSpinCW {
        to {
            transform: rotate(360deg)
        }
    }

    @keyframes publicVcardSpinCCW {
        to {
            transform: rotate(-360deg)
        }
    }

    @keyframes publicVcardPulseGlow {
        0%,
        100% {
            transform: scale(1);
            opacity: .5
        }

        50% {
            transform: scale(1.3);
            opacity: 1
        }
    }
</style>

@php
    $templates = [
        'modern-banner-center' => ['has_banner' => true, 'avatar_position' => 'center-over-banner', 'effect' => 'normal'],
        'modern-banner-left' => ['has_banner' => true, 'avatar_position' => 'left-over-banner', 'effect' => 'normal'],
        'clean-no-banner' => ['has_banner' => false, 'avatar_position' => 'top-center', 'effect' => 'normal'],
        'minimal-left' => ['has_banner' => false, 'avatar_position' => 'left-inline', 'effect' => 'normal'],
        'dark-banner' => ['has_banner' => true, 'avatar_position' => 'center-over-banner', 'effect' => 'normal'],
        'user-image-banner' => ['has_banner' => true, 'avatar_position' => 'banner-profile-cover', 'use_profile_as_banner' => true, 'effect' => 'hero-portrait'],
        'creative-square' => ['has_banner' => false, 'avatar_position' => 'square-top', 'effect' => 'normal'],
        'water-glass-card' => ['has_banner' => true, 'avatar_position' => 'center-over-banner', 'effect' => 'water-glass'],
        'glassmorphism-card' => ['has_banner' => false, 'avatar_position' => 'top-center', 'effect' => 'glassmorphism'],
    ];

    $activeTemplate = $templates[$data['template'] ?? 'modern-banner-center'] ?? $templates['modern-banner-center'];
    $hasBanner = (bool) ($activeTemplate['has_banner'] ?? true);
    $avatarPosition = $activeTemplate['avatar_position'] ?? 'center-over-banner';
    $templateEffect = $activeTemplate['effect'] ?? 'normal';
    $useProfileAsBanner = (bool) ($activeTemplate['use_profile_as_banner'] ?? false);
    $isWaterGlass = $templateEffect === 'water-glass';
    $isGlass = ($data['fieldShadow'] ?? 'soft') === 'glass' || in_array($templateEffect, ['water-glass', 'glassmorphism'], true);

    $normalizeHex = function ($color, $fallback = '#ffffff') {
        $color = trim((string) $color);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback;
    };
    $isDark = function ($color) use ($normalizeHex) {
        $hex = ltrim($normalizeHex($color), '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return (($r * 299 + $g * 587 + $b * 114) / 1000) < 145;
    };

    $accent = $normalizeHex($data['accentColor'] ?? '#06b6d4', '#06b6d4');
    $cardBg = $normalizeHex($data['cardBg'] ?? '#ffffff', '#ffffff');
    $pageBg = $normalizeHex($data['bgColor'] ?? '#0f172a', '#0f172a');
    $darkCard = $isGlass || $isDark($cardBg);
    $text = $isGlass ? '#ffffff' : $normalizeHex($data['textColor'] ?? ($darkCard ? '#ffffff' : '#111827'), $darkCard ? '#ffffff' : '#111827');
    $muted = $darkCard ? 'rgba(255,255,255,.72)' : '#64748b';
    $fieldBg = $darkCard ? 'rgba(255,255,255,.07)' : '#ffffff';
    $fieldBorderColor = $darkCard ? 'rgba(255,255,255,.14)' : ($data['fieldBorderColor'] ?? '#e2e8f0');
    $fieldRadius = min(32, max(0, (int) ($data['fieldBorderRadius'] ?? 12)));
    $fieldWidth = min(10, max(0, (int) ($data['fieldBorderWidth'] ?? 1)));
    $fieldStyle = in_array(($data['fieldBorderStyle'] ?? 'solid'), ['solid', 'dashed', 'dotted', 'none'], true) ? $data['fieldBorderStyle'] : 'solid';
    $fieldShadow = $data['fieldShadow'] ?? 'soft';
    $fieldShadowCss = match ($fieldShadow) {
        'none' => 'none',
        'medium' => '0 14px 30px rgba(15,23,42,.16)',
        'glass' => 'inset 0 1px 0 rgba(255,255,255,.35), 0 18px 45px rgba(15,23,42,.22)',
        default => '0 1px 3px rgba(15,23,42,.10)',
    };
    $fieldCardStyle = 'border:' . ($fieldStyle === 'none' ? '0' : $fieldWidth . 'px ' . $fieldStyle . ' ' . $fieldBorderColor) . '; border-radius:' . $fieldRadius . 'px; background:' . ($isGlass ? 'linear-gradient(135deg,rgba(255,255,255,.24),rgba(255,255,255,.09))' : $fieldBg) . '; box-shadow:' . $fieldShadowCss . ';' . ($isGlass ? 'backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);' : '');
    $iconStyle = $isGlass ? 'background:rgba(255,255,255,.18);color:#fff;' : 'background:' . ($darkCard ? 'rgba(255,255,255,.10)' : $accent . '18') . ';color:' . $accent . ';';

    $avatarRadiusValue = min(56, max(0, (int) ($data['avatarBorderRadius'] ?? 56)));
    $avatarRadius = $avatarRadiusValue >= 56 ? '999px' : $avatarRadiusValue . 'px';
    $ringEnabled = (bool) ($data['avatarRingEnabled'] ?? true);
    $ringWidth = min(12, max(0, (int) ($data['avatarRingWidth'] ?? 4)));
    $ringColor = $data['avatarRingColor'] ?? '#ffffff';
    $avatarStyle = 'border-radius:' . $avatarRadius . ';' . ($ringEnabled ? 'border:' . $ringWidth . 'px solid ' . $ringColor . ';' : 'border:0;') . 'box-shadow:0 18px 40px rgba(15,23,42,.22);';

    $profileUrl = filled($data['profilePath'] ?? null) ? Storage::url($data['profilePath']) : null;
    $bannerUrl = filled($data['bannerPath'] ?? null) ? Storage::url($data['bannerPath']) : null;
    $bannerImage = $useProfileAsBanner && $profileUrl ? $profileUrl : $bannerUrl;
    $showHeaderAvatar = !($useProfileAsBanner && $profileUrl);

    $phones = collect($data['phones'] ?? [])->filter(fn($x) => filled($x['value'] ?? ''))->values();
    $emails = collect($data['emails'] ?? [])->filter(fn($x) => filled($x['value'] ?? ''))->values();
    $sites = collect($data['sites'] ?? [])->filter(fn($x) => filled($x['value'] ?? ''))->values();
    $companies = collect($data['companies'] ?? [])->filter(fn($x) => filled($x['company_name'] ?? '') || filled($x['profession'] ?? ''))->values();
    $socialLinks = array_filter($data['socialLinks'] ?? []);
    $sectionOrder = $data['previewSectionOrder'] ?? ['phones', 'emails', 'sites', 'location', 'companies', 'social'];

    $address = trim(implode(', ', array_filter([$data['street'] ?? '', $data['city'] ?? '', $data['state'] ?? '', $data['zip'] ?? '', $data['country'] ?? ''])));
    $coordinates = trim(($data['latitude'] ?? '') . (filled($data['latitude'] ?? '') && filled($data['longitude'] ?? '') ? ', ' : '') . ($data['longitude'] ?? ''));
    $locationHref = filled($data['locationUrl'] ?? '') ? $data['locationUrl'] : ($coordinates ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($coordinates) : ($address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address) : ''));
    $locationDisplay = $address ?: ($coordinates ?: (filled($data['locationUrl'] ?? '') ? 'Show on map' : ''));
    $firstPhone = $phones->first();
    $firstEmail = $emails->first();
@endphp

<div class="min-h-screen px-3 py-6 sm:px-5 sm:py-10"
    style="background:{{ $pageBg }};font-family:'{{ $data['fontFamily'] ?? 'Poppins' }}',sans-serif;">
    <div class="mx-auto w-full max-w-[350px]">
        <div
            class="relative rounded-[2.35rem] border border-white/15 bg-white/10 p-2 shadow-2xl ring-1 ring-white/15 backdrop-blur-2xl">
            <div x-data="{showLoader:@js((bool) ($data['loadingScreenEnabled'] ?? false)),seconds:{{ (int) ($data['loadingTime'] ?? 2) }},init(){if(this.showLoader)setTimeout(()=>this.showLoader=false,this.seconds*1000)}}"
                class="relative max-h-[calc(100vh-3rem)] overflow-y-auto rounded-[1.9rem] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                style="{{ $isWaterGlass ? 'background:radial-gradient(circle at 18% 0%,rgba(125,211,252,.50),transparent 34%),radial-gradient(circle at 90% 16%,rgba(34,211,238,.30),transparent 30%),linear-gradient(160deg,rgba(8,47,73,.96),rgba(15,23,42,.90));' : ($isGlass ? 'background:radial-gradient(circle at 20% 0%,rgba(56,189,248,.30),transparent 34%),radial-gradient(circle at 90% 12%,rgba(167,139,250,.32),transparent 30%),linear-gradient(160deg,rgba(15,23,42,.96),rgba(30,41,59,.90));' : 'background:' . $cardBg . ';') }}color:{{ $text }};">

                @if (($data['loadingScreenEnabled'] ?? false))
                    <div x-show="showLoader" x-transition.opacity
                        class="absolute inset-0 z-50 grid place-items-center rounded-[1.9rem] px-8 text-center backdrop-blur-xl"
                        style="background:linear-gradient(160deg,rgba(2,6,23,.92),rgba(15,23,42,.88));">
                        <div class="w-full max-w-[220px]">
                            @if (filled($data['loadingPath'] ?? null))
                                <img src="{{ Storage::url($data['loadingPath']) }}"
                                    class="mx-auto h-24 w-24 rounded-3xl object-cover shadow-2xl ring-1 ring-white/15"
                                    style="animation:publicVcardLoadingPulse 1.8s ease-in-out infinite" alt="Loading">
                            @else
                                <div class="relative mx-auto h-24 w-24">
                                    <div class="absolute inset-0 rounded-full"
                                        style="border:3px solid transparent;border-top-color:{{ $accent }};border-bottom-color:rgba(255,255,255,.12);animation:publicVcardSpinCW 2.2s linear infinite">
                                    </div>
                                    <div class="absolute inset-2 rounded-full"
                                        style="border:2px solid transparent;border-left-color:rgba(255,255,255,.25);border-right-color:{{ $accent }}60;animation:publicVcardSpinCCW 1.4s linear infinite">
                                    </div>
                                    <div class="absolute inset-0 grid place-items-center">
                                        <div class="h-4 w-4 rounded-full"
                                            style="background:{{ $accent }};box-shadow:0 0 30px {{ $accent }}80,0 0 60px {{ $accent }}40;animation:publicVcardPulseGlow 1.8s ease-in-out infinite">
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-300 via-blue-300 to-fuchsia-300"
                                    style="animation:publicVcardLoadingBar {{ (int) ($data['loadingTime'] ?? 2) }}s linear forwards">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($isGlass)
                    <div
                        class="pointer-events-none absolute -left-16 top-28 h-40 w-40 rounded-full bg-cyan-300/20 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute -right-20 top-64 h-48 w-48 rounded-full bg-fuchsia-300/20 blur-3xl">
                    </div>
                @endif

                @if ($hasBanner)
                    <div class="relative h-52 overflow-hidden rounded-t-[1.9rem]"
                        style="background:linear-gradient(135deg,{{ $accent }},#f8fafc);">
                        @if ($bannerImage)<img src="{{ $bannerImage }}" class="absolute inset-0 h-full w-full object-cover"
                        alt="Banner">@endif
                        <div
                            class="absolute inset-0 {{ $isGlass ? 'bg-gradient-to-b from-black/10 via-transparent to-slate-950/70' : 'bg-gradient-to-b from-black/5 via-transparent to-white' }}">
                        </div>
                        @if ($showHeaderAvatar && in_array($avatarPosition, ['center-over-banner', 'left-over-banner'], true))
                            <div
                                class="absolute bottom-5 z-10 {{ $avatarPosition === 'center-over-banner' ? 'inset-x-0 flex justify-center' : 'left-5' }}">
                                @if ($profileUrl)
                                    <img src="{{ $profileUrl }}" class="h-24 w-24 object-cover" style="{{ $avatarStyle }}"
                                        alt="{{ $this->fullName }}">
                                @else
                                    <div class="grid h-24 w-24 place-items-center text-2xl font-black text-white"
                                        style="background:{{ $accent }};{{ $avatarStyle }}">{{ $this->initials }}</div>
                                @endif
                            </div>
                        @elseif ($showHeaderAvatar && $avatarPosition === 'banner-profile-cover')
                            <div class="absolute inset-0 z-10 grid place-items-center">
                                <div class="grid h-28 w-28 place-items-center text-3xl font-black text-white ring-8 ring-white/10"
                                    style="background:{{ $accent }};{{ $avatarStyle }}">{{ $this->initials }}</div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="relative px-5 pt-6">
                        @if ($avatarPosition === 'left-inline')
                            <div class="flex items-center gap-4">
                                @if ($profileUrl)<img src="{{ $profileUrl }}" class="h-20 w-20 object-cover"
                                style="{{ $avatarStyle }}" alt="{{ $this->fullName }}">@else<div
                                        class="grid h-20 w-20 place-items-center text-2xl font-black text-white"
                                    style="background:{{ $accent }};{{ $avatarStyle }}">{{ $this->initials }}</div>@endif
                            </div>
                        @else
                            <div class="flex justify-center">@if ($profileUrl)<img src="{{ $profileUrl }}"
                            class="h-28 w-28 object-cover" style="{{ $avatarStyle }}" alt="{{ $this->fullName }}">@else
                                        <div class="grid h-28 w-28 place-items-center text-3xl font-black text-white"
                                    style="background:{{ $accent }};{{ $avatarStyle }}">{{ $this->initials }}</div>@endif</div>
                        @endif
                    </div>
                @endif

                <div class="relative z-20 flex flex-col px-5 pb-12 {{ $hasBanner ? 'pt-4' : 'pt-5' }}">
                    <div class="{{ $avatarPosition === 'left-inline' && !$hasBanner ? 'mt-3' : '' }}">
                        <h1 class="text-[22px] font-black leading-tight" style="color:{{ $text }}">{{ $this->fullName }}
                        </h1>
                        @if (filled($data['designation'] ?? ''))
                            <p class="mt-1 text-xs font-semibold" style="color:{{ $muted }}">{{ $data['designation'] }}</p>
                        @endif
                        @if (filled($data['aboutMe'] ?? ''))
                        <p class="mt-4 text-sm leading-6" style="color:{{ $muted }}">{{ $data['aboutMe'] }}</p>@endif
                    </div>

                    @if (($data['contactButtonPosition'] ?? 'top') === 'top')
                        <div class="mt-5 flex items-center gap-3">
                            <button wire:click="downloadVcf" class="rounded-full px-5 py-2.5 text-sm font-black shadow-lg"
                                style="background:{{ $accent }};color:{{ $data['buttonTextColor'] ?? '#fff' }}">{{ $data['contactButtonText'] ?? 'Save Contact' }}</button>
                            @if ($firstPhone)<a href="tel:{{ $firstPhone['value'] }}"
                                class="grid h-10 w-10 place-items-center rounded-full shadow-sm ring-1 ring-white/15"
                                style="background:{{ $darkCard ? 'rgba(255,255,255,.10)' : '#fff' }};color:{{ $accent }}"><span
                            class="material-symbols-outlined">call</span></a>@endif
                            @if ($firstEmail)<a href="mailto:{{ $firstEmail['value'] }}"
                                class="grid h-10 w-10 place-items-center rounded-full shadow-sm ring-1 ring-white/15"
                                style="background:{{ $darkCard ? 'rgba(255,255,255,.10)' : '#fff' }};color:{{ $accent }}"><span
                            class="material-symbols-outlined">mail</span></a>@endif
                        </div>
                    @endif

                    <div class="mt-6 flex flex-col gap-2.5">
                        @foreach ($sectionOrder as $section)
                            @if ($section === 'phones')
                                @foreach ($phones as $item)
                                    <a href="tel:{{ $item['value'] }}" class="flex items-center gap-3 px-3 py-3"
                                        style="{{ $fieldCardStyle }}">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="{{ $iconStyle }}">
                                            <span class="material-symbols-outlined">{{ $item['icon'] ?? 'call' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-black" style="color: {{ $muted }}">
                                                {{ $item['label'] ?? 'Phone' }}</p>
                                            <p class="truncate text-sm font-semibold" style="color: {{ $text }}">
                                                {{ $item['value'] }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            @elseif ($section === 'emails')
                                @foreach ($emails as $item)
                                    <a href="mailto:{{ $item['value'] }}" class="flex items-center gap-3 px-3 py-3"
                                        style="{{ $fieldCardStyle }}">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="{{ $iconStyle }}">
                                            <span class="material-symbols-outlined">{{ $item['icon'] ?? 'mail' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-black" style="color: {{ $muted }}">
                                                {{ $item['label'] ?? 'Email' }}</p>
                                            <p class="truncate text-sm font-semibold" style="color: {{ $text }}">
                                                {{ $item['value'] }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            @elseif ($section === 'sites')
                                @foreach ($sites as $item)
                                    <a href="{{ $item['value'] }}" target="_blank" rel="noopener"
                                        class="flex items-center gap-3 px-3 py-3" style="{{ $fieldCardStyle }}">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="{{ $iconStyle }}">
                                            <span class="material-symbols-outlined">{{ $item['icon'] ?? 'language' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-black" style="color: {{ $muted }}">
                                                {{ $item['label'] ?? 'Website' }}</p>
                                            <p class="truncate text-sm font-semibold" style="color: {{ $text }}">
                                                {{ $item['value'] }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            @elseif ($section === 'location' && $locationDisplay)
                                <a href="{{ $locationHref }}" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 px-3 py-3" style="{{ $fieldCardStyle }}">
                                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="{{ $iconStyle }}">
                                        <span
                                            class="material-symbols-outlined">{{ $data['locationIcon'] ?? 'location_on' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-black" style="color: {{ $muted }}">
                                            {{ $data['locationLabel'] ?? 'Location' }}</p>
                                        <p class="truncate text-sm font-semibold" style="color: {{ $text }}">
                                            {{ $locationDisplay }}</p>
                                    </div>
                                </a>
                            @elseif ($section === 'companies')
                                @foreach ($companies as $item)
                                    <div class="flex items-center gap-3 px-3 py-3" style="{{ $fieldCardStyle }}">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg" style="{{ $iconStyle }}">
                                            <span class="material-symbols-outlined">{{ $item['icon'] ?? 'business_center' }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-[11px] font-black" style="color: {{ $muted }}">
                                                {{ $item['company_name'] ?? 'Company' }}</p>
                                            <p class="truncate text-sm font-semibold" style="color: {{ $text }}">
                                                {{ $item['profession'] ?? '' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            @elseif ($section === 'social' && count($socialLinks))
                                @if ($data['showSocialAsCards'] ?? false)
                                    <div class="space-y-2.5">
                                        @foreach ($socialLinks as $platform => $url)
                                            @php
                                                $brand = $this->socialDisplayBrand($platform);
                                                $social = $this->socialOptions[$brand] ?? [
                                                    'label' => ucfirst($brand),
                                                    'icon_slug' => $brand,
                                                    'color' => $accent,
                                                ];
                                            @endphp
                                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                                class="flex items-center gap-3 px-3 py-3" style="{{ $fieldCardStyle }}">
                                                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white shadow-sm">
                                                    @if ($this->socialUsesBrandIcon($platform))
                                                        <img src="https://cdn.simpleicons.org/{{ $social['icon_slug'] }}/{{ ltrim($social['color'], '#') }}"
                                                            class="h-5 w-5" alt="{{ $social['label'] }}">
                                                    @else
                                                        <span class="material-symbols-outlined"
                                                            style="color: {{ $accent }}">{{ $this->socialMaterialIcon($platform) }}</span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-[11px] font-black" style="color: {{ $muted }}">
                                                        {{ $this->socialDisplayLabel($platform) }}</p>
                                                    <p class="truncate text-sm font-semibold" style="color: {{ $text }}">{{ $url }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="flex flex-wrap justify-center gap-3">
                                        @foreach ($socialLinks as $platform => $url)
                                            @php
                                                $brand = $this->socialDisplayBrand($platform);
                                                $social = $this->socialOptions[$brand] ?? [
                                                    'label' => ucfirst($brand),
                                                    'icon_slug' => $brand,
                                                    'color' => $accent,
                                                ];
                                            @endphp
                                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                                class="{{ ($data['showSocialName'] ?? false) ? 'inline-flex items-center gap-2 rounded-full px-3 py-2' : 'grid h-10 w-10 place-items-center rounded-full' }} bg-white/80 shadow-sm ring-1 ring-white/60 backdrop-blur-xl"
                                                title="{{ $this->socialDisplayLabel($platform) }}">
                                                @if ($this->socialUsesBrandIcon($platform))
                                                    <img src="https://cdn.simpleicons.org/{{ $social['icon_slug'] }}/{{ ltrim($social['color'], '#') }}"
                                                        class="h-5 w-5" alt="{{ $social['label'] }}">
                                                @else
                                                    <span class="material-symbols-outlined"
                                                        style="color: {{ $accent }}">{{ $this->socialMaterialIcon($platform) }}</span>
                                                @endif

                                                @if ($data['showSocialName'] ?? false)
                                                    <span class="text-[11px] font-medium"
                                                        style="color: {{ $text }}">{{ $this->socialDisplayLabel($platform) }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            @if (($data['contactButtonPosition'] ?? 'top') === 'floating')
                @php
                    $placement = match ($data['floatingButtonPlacement'] ?? 'bottom-right') { 'top-left' => 'top-6 left-6', 'top-right' => 'top-6 right-6', 'bottom-left' => 'bottom-6 left-6', default => 'bottom-6 right-6'};
                    $btnRadiusValue = min(56, max(0, (int) ($data['floatingButtonBorderRadius'] ?? 56)));
                    $btnRadius = $btnRadiusValue >= 56 ? '999px' : $btnRadiusValue . 'px';
                    $ringShape = match ($data['floatingButtonRingShape'] ?? 'circle') { 'square' => '0px', 'rounded' => '18px', default => '999px'};
                @endphp
                <div class="absolute {{ $placement }} z-30 grid h-14 w-14 place-items-center">
                    @if (($data['floatingButtonRingEnabled'] ?? true) && (int) ($data['floatingButtonRingWidth'] ?? 4) > 0)<span
                        class="pointer-events-none absolute"
                    style="inset:-{{ (int) $data['floatingButtonRingWidth'] }}px;border:{{ (int) $data['floatingButtonRingWidth'] }}px solid {{ $data['floatingButtonRingColor'] ?? '#fff' }};border-radius:{{ $ringShape }}"></span>@endif
                    <button wire:click="downloadVcf"
                        class="relative grid h-14 w-14 place-items-center text-white shadow-2xl"
                        style="background:{{ $accent }};border-radius:{{ $btnRadius }}"><span
                            class="material-symbols-outlined">person_add</span></button>
                </div>
            @endif
        </div>

        @php($siteName = SiteSetting::current()->site_name ?? config('app.name'))
        <p class="mt-5 text-center text-xs text-white/40">Powered by {{ $siteName }}</p>
    </div>
</div>