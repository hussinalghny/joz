<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The storefront copy and pictures the owner can change from the admin panel.
 *
 * Texts are stored as overrides of the lines in lang/{ar,en}/shop.php: an empty
 * field means "use the built-in wording", so clearing a field restores it.
 * Media are paths on the public disk; an empty slot falls back to the picture or
 * video shipped with the theme.
 */
class SiteContent
{
    public const TEXTS_SETTING = 'site_texts';

    public const MEDIA_SETTING = 'site_media';

    /**
     * Editable lines of lang/*\/shop.php, grouped as the admin page shows them.
     * A line marked as long gets a text area instead of a single-line input.
     *
     * @var array<string, array<string, bool>>
     */
    public const TEXTS = [
        'landing' => [
            'hero.tagline_1' => false,
            'hero.tagline_2' => false,
            'welcome.enter' => false,
            'welcome.contact' => false,
            'welcome.delivery' => false,
        ],
        'announce' => [
            'announce.delivery' => false,
            'announce.cash' => false,
            'announce.whatsapp' => false,
        ],
        'trust' => [
            'trust.label' => false,
            'trust.delivery_title' => false,
            'trust.delivery_text' => false,
            'trust.cash_title' => false,
            'trust.cash_text' => false,
            'trust.chat_title' => false,
            'trust.chat_text' => false,
            'trust.curated_title' => false,
            'trust.curated_text' => false,
        ],
        'home' => [
            'home.sections_title' => false,
            'home.sections_sub' => false,
            'home.offers_eyebrow' => false,
            'home.offers_title' => false,
            'home.offers_sub' => true,
            'home.best_sub' => false,
            'home.new_sub' => false,
            'home.story_eyebrow' => false,
            'home.story_text_1' => true,
            'home.story_text_2' => true,
            'home.story_cta' => false,
            'home.ig_title' => false,
            'home.ig_text' => true,
            'projects.eyebrow' => false,
            'projects.title' => false,
            'projects.text' => true,
            'projects.cta' => false,
        ],
        'footer' => [
            'footer.about' => true,
            'footer.rights' => false,
            'meta.title' => false,
            'meta.description' => true,
        ],
    ];

    /**
     * Replaceable pictures and videos, with the file the theme ships for each.
     *
     * @var array<string, array{type: 'image'|'video', default: string}>
     */
    public const MEDIA = [
        'landing_video_wide' => ['type' => 'video', 'default' => 'assets/video/landing-wide.mp4'],
        'landing_video_portrait' => ['type' => 'video', 'default' => 'assets/video/landing-portrait.mp4'],
        'landing_poster_wide' => ['type' => 'image', 'default' => 'assets/img/welcome/poster-wide.webp'],
        'landing_poster_portrait' => ['type' => 'image', 'default' => 'assets/img/welcome/poster-portrait.webp'],
        'home_picks' => ['type' => 'image', 'default' => 'assets/img/demo/picks.webp'],
        'home_projects' => ['type' => 'image', 'default' => 'assets/img/demo/projects-wide.webp'],
        'home_story' => ['type' => 'image', 'default' => 'assets/img/demo/story.webp'],
    ];

    public const MEDIA_DIRECTORY = 'site';

    /** Videos up to 256 MB: about four minutes of 1080p at a good web bitrate. */
    public const VIDEO_MAX_KB = 262144;

    public const IMAGE_MAX_KB = 8192;

    public const VIDEO_TYPES = ['video/mp4', 'video/webm'];

    /**
     * Lays the owner's wording over the shop translations for one language.
     * Called once per storefront request, after the language is known.
     */
    public static function apply(string $locale): void
    {
        $lines = static::overrides($locale);

        if ($lines === []) {
            return;
        }

        $translator = app('translator');

        // Load the file first: adding lines to a group that is not loaded yet
        // would mark it as loaded and hide every line the owner did not change.
        $translator->load('*', 'shop', $locale);

        $translator->addLines(
            collect($lines)->mapWithKeys(fn (string $text, string $key) => ["shop.{$key}" => $text])->all(),
            $locale,
        );
    }

    /**
     * The owner's non-empty wording for one language, keyed like "hero.tagline_1".
     *
     * @return array<string, string>
     */
    public static function overrides(string $locale): array
    {
        $stored = static::decode(static::setting(self::TEXTS_SETTING));
        $allowed = static::keys();

        return collect(Arr::dot($stored[$locale] ?? []))
            ->filter(fn ($text, $key) => in_array($key, $allowed, true) && is_string($text) && trim($text) !== '')
            ->map(fn (string $text) => trim($text))
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(array_merge(...array_values(self::TEXTS)));
    }

    /**
     * The URL of a replaceable picture or video: the owner's upload if there is
     * one, otherwise the file the theme ships.
     */
    public static function mediaUrl(string $slot): string
    {
        $path = static::mediaPath($slot);

        if ($path !== null) {
            return Storage::disk('public')->url($path);
        }

        return asset(self::MEDIA[$slot]['default']);
    }

    public static function mediaPath(string $slot): ?string
    {
        $path = static::decode(static::setting(self::MEDIA_SETTING))[$slot] ?? null;

        if (! is_string($path) || $path === '' || ! array_key_exists($slot, self::MEDIA)) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        $value = json_decode($json, true);

        return is_array($value) ? $value : [];
    }

    private static function setting(string $key): mixed
    {
        try {
            return Setting::get($key);
        } catch (Throwable) {
            // No database yet (first install, or migrations still running).
            return null;
        }
    }
}
