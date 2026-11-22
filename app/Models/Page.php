<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'video_path', 'video_poster_path', 'video_position', 'meta_description', 'is_active'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasLocalizedFields;

    public const CORE_SLUGS = ['about', 'contact', 'delivery', 'returns', 'privacy'];

    public const VIDEO_DIRECTORY = 'pages';

    public const VIDEO_TOP = 'top';

    public const VIDEO_BOTTOM = 'bottom';

    protected static function booted(): void
    {
        // A replaced or removed video is deleted, so old uploads do not fill the host.
        static::updated(function (Page $page): void {
            foreach (['video_path', 'video_poster_path'] as $column) {
                if ($page->wasChanged($column)) {
                    static::deleteUpload($page->getOriginal($column));
                }
            }
        });

        static::deleted(function (Page $page): void {
            static::deleteUpload($page->video_path);
            static::deleteUpload($page->video_poster_path);
        });
    }

    public function videoUrl(): ?string
    {
        return $this->uploadUrl($this->video_path);
    }

    public function videoPosterUrl(): ?string
    {
        return $this->uploadUrl($this->video_poster_path);
    }

    public function videoMimeType(): string
    {
        return str_ends_with(strtolower((string) $this->video_path), '.webm') ? 'video/webm' : 'video/mp4';
    }

    private function uploadUrl(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private static function deleteUpload(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, self::VIDEO_DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function isCore(): bool
    {
        return in_array($this->slug, self::CORE_SLUGS, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
