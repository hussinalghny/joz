<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SiteContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lets the owner reword the storefront and replace its pictures and videos
 * without touching code. Every text field shows the built-in wording as its
 * placeholder; leaving it empty keeps that wording.
 *
 * @property-read Schema $form
 */
class ManageSiteContent extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'site-content';

    private const SHORT_MAX = 300;

    private const LONG_MAX = 1500;

    /** Which media slots each tab shows. */
    private const TAB_MEDIA = [
        'landing' => ['landing_video_wide', 'landing_video_portrait', 'landing_poster_wide', 'landing_poster_portrait'],
        'home' => ['home_picks', 'home_projects', 'home_story'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Setting::class) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.site_content.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.site_content.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.site_content.subheading');
    }

    public function mount(): void
    {
        $this->form->fill([
            'texts' => SiteContent::decode(Setting::get(SiteContent::TEXTS_SETTING)),
            'media' => Arr::only(SiteContent::decode(Setting::get(SiteContent::MEDIA_SETTING)), array_keys(SiteContent::MEDIA)),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('content')
                    ->persistTabInQueryString()
                    ->tabs(collect(SiteContent::TEXTS)->map(fn (array $keys, string $group) => Tab::make(__("admin.site_content.tabs.{$group}"))
                        ->schema([
                            ...$this->mediaSection($group),
                            ...collect($keys)->map(fn (bool $long, string $key) => $this->textPair($key, $long))->values()->all(),
                        ]))->values()->all()),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.site_content.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $texts = [];
        foreach (['ar', 'en'] as $locale) {
            foreach (SiteContent::keys() as $key) {
                $value = trim((string) data_get($state, "texts.{$locale}.{$key}", ''));

                if ($value !== '') {
                    data_set($texts, "{$locale}.{$key}", $value);
                }
            }
        }

        $oldMedia = SiteContent::decode(Setting::get(SiteContent::MEDIA_SETTING));
        $media = collect(SiteContent::MEDIA)
            ->keys()
            ->mapWithKeys(fn (string $slot) => [$slot => $this->uploadedPath(data_get($state, "media.{$slot}"))])
            ->filter()
            ->all();

        Setting::put(SiteContent::TEXTS_SETTING, json_encode($texts, JSON_UNESCAPED_UNICODE));
        Setting::put(SiteContent::MEDIA_SETTING, json_encode($media, JSON_UNESCAPED_SLASHES));

        $this->deleteReplaced($oldMedia, $media);

        Notification::make()
            ->success()
            ->title(__('admin.site_content.saved'))
            ->send();
    }

    /**
     * One line of copy, in Arabic and English side by side.
     */
    private function textPair(string $key, bool $long): Grid
    {
        $label = __('admin.site_content.labels.'.str_replace('.', '__', $key));

        return Grid::make(2)->schema(collect(['ar', 'en'])->map(function (string $locale) use ($key, $long, $label): Component {
            $field = $long
                ? Textarea::make("texts.{$locale}.{$key}")->rows(3)->maxLength(self::LONG_MAX)
                : TextInput::make("texts.{$locale}.{$key}")->maxLength(self::SHORT_MAX);

            $default = __("shop.{$key}", [], $locale);

            return $field
                ->label($label.' · '.__("admin.site_content.locale_{$locale}"))
                ->placeholder(Str::limit($default, 160))
                ->helperText($this->tokens($default) !== '' ? __('admin.site_content.placeholder_hint', ['tokens' => $this->tokens($default)]) : null)
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']);
        })->all());
    }

    /**
     * @return list<Section>
     */
    private function mediaSection(string $group): array
    {
        $slots = self::TAB_MEDIA[$group] ?? [];

        if ($slots === []) {
            return [];
        }

        return [
            Section::make(__('admin.site_content.media'))
                ->description(__('admin.site_content.media_hint'))
                ->columns(2)
                ->schema(collect($slots)->map(fn (string $slot) => $this->upload($slot))->all()),
        ];
    }

    private function upload(string $slot): FileUpload
    {
        $isVideo = SiteContent::MEDIA[$slot]['type'] === 'video';

        $upload = FileUpload::make("media.{$slot}")
            ->label(__("admin.site_content.slots.{$slot}"))
            ->disk('public')
            ->directory(SiteContent::MEDIA_DIRECTORY)
            ->visibility('public')
            ->openable()
            ->helperText(__($isVideo ? 'admin.site_content.video_hint' : 'admin.site_content.image_hint', [
                'size' => $isVideo ? intdiv(SiteContent::VIDEO_MAX_KB, 1024) : intdiv(SiteContent::IMAGE_MAX_KB, 1024),
            ]));

        if ($isVideo) {
            return $upload
                ->acceptedFileTypes(SiteContent::VIDEO_TYPES)
                ->maxSize(SiteContent::VIDEO_MAX_KB);
        }

        return $upload
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->imageResizeUpscale(false)
            ->maxSize(SiteContent::IMAGE_MAX_KB);
    }

    private function uploadedPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = Arr::first($state);
        }

        return is_string($state) && str_starts_with($state, SiteContent::MEDIA_DIRECTORY.'/') ? $state : null;
    }

    /**
     * Removes uploads that are no longer used, so replacing a video does not
     * leave the old one taking space on the host.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, string>  $new
     */
    private function deleteReplaced(array $old, array $new): void
    {
        foreach ($old as $slot => $path) {
            if (is_string($path) && str_starts_with($path, SiteContent::MEDIA_DIRECTORY.'/') && ! in_array($path, $new, true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function tokens(string $text): string
    {
        preg_match_all('/:[a-z_]+/', $text, $matches);

        return implode('، ', array_unique($matches[0]));
    }
}
