<?php

namespace App\Models;

use Database\Factories\ClientFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * An item in a client's Drive: an uploaded file (kind = file) or a saved
 * link such as a Google Drive file, YouTube, Vimeo or Loom video (kind = link).
 */
#[Fillable(['client_id', 'folder_id', 'kind', 'uploaded_by', 'type', 'title', 'disk', 'path', 'url', 'original_name', 'mime_type', 'size'])]
class ClientFile extends Model
{
    /** @use HasFactory<ClientFileFactory> */
    use HasFactory;

    /** Mime types that are safe to show inline in the browser (no SVG/HTML). */
    public const INLINE_IMAGES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const INLINE_VIDEOS = ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg'];

    /** Upload types the Drive accepts. */
    public const ALLOWED_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,rtf,odt,ods,jpg,jpeg,png,gif,webp,heic,zip,rar,7z,ai,psd,eps,fig,sketch,mp4,mov,webm,m4v,mp3,wav,m4a';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ClientFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withDefault(['name' => 'Deleted user']);
    }

    public function isLink(): bool
    {
        return $this->kind === 'link';
    }

    public function isImage(): bool
    {
        return ! $this->isLink() && in_array($this->mime_type, self::INLINE_IMAGES, true);
    }

    public function isPdf(): bool
    {
        return ! $this->isLink() && $this->mime_type === 'application/pdf';
    }

    public function isVideo(): bool
    {
        return $this->isLink()
            ? in_array($this->linkProvider(), ['YouTube', 'Vimeo', 'Loom'], true)
            : in_array($this->mime_type, self::INLINE_VIDEOS, true);
    }

    /** Whether the item can be previewed inside the app. */
    public function isPreviewable(): bool
    {
        return $this->isLink() ? $this->embedUrl() !== null : $this->viewUrl() !== null;
    }

    /** Category used by the Drive filter chips. */
    public function category(): string
    {
        return match (true) {
            $this->isLink() => $this->isVideo() ? 'video' : 'link',
            $this->isImage() || Str::startsWith((string) $this->mime_type, 'image/') => 'image',
            $this->isPdf() => 'pdf',
            $this->isVideo() || Str::startsWith((string) $this->mime_type, 'video/') => 'video',
            default => 'document',
        };
    }

    public function icon(): string
    {
        return match ($this->category()) {
            'image' => '🖼️',
            'pdf' => '📕',
            'video' => '🎬',
            'link' => $this->linkProvider() === 'Google Drive' ? '🟢' : '🔗',
            default => match (true) {
                Str::contains((string) $this->mime_type, ['sheet', 'excel', 'csv']) => '📊',
                Str::contains((string) $this->mime_type, ['presentation', 'powerpoint']) => '📽️',
                Str::contains((string) $this->mime_type, ['zip', 'rar', '7z', 'compressed']) => '🗜️',
                Str::startsWith((string) $this->mime_type, 'audio/') => '🎵',
                default => '📄',
            },
        };
    }

    public function linkProvider(): ?string
    {
        $host = strtolower((string) parse_url((string) $this->url, PHP_URL_HOST));

        return match (true) {
            ! $this->isLink() => null,
            str_contains($host, 'drive.google.com'), str_contains($host, 'docs.google.com') => 'Google Drive',
            str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => 'YouTube',
            str_contains($host, 'vimeo.com') => 'Vimeo',
            str_contains($host, 'loom.com') => 'Loom',
            str_contains($host, 'dropbox.com') => 'Dropbox',
            str_contains($host, 'figma.com') => 'Figma',
            default => preg_replace('/^www\./', '', $host) ?: 'Link',
        };
    }

    /** Embeddable player/viewer URL for supported links, or null. */
    public function embedUrl(): ?string
    {
        if (! $this->isLink() || ! $this->url) {
            return null;
        }

        $url = $this->url;

        return match (true) {
            (bool) preg_match('~drive\.google\.com/file/d/([\w-]+)~', $url, $m) => "https://drive.google.com/file/d/{$m[1]}/preview",
            (bool) preg_match('~drive\.google\.com/(?:open|uc)\?(?:.*&)?id=([\w-]+)~', $url, $m) => "https://drive.google.com/file/d/{$m[1]}/preview",
            (bool) preg_match('~docs\.google\.com/(document|spreadsheets|presentation)/d/([\w-]+)~', $url, $m) => "https://docs.google.com/{$m[1]}/d/{$m[2]}/preview",
            (bool) preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([\w-]{6,})~', $url, $m) => "https://www.youtube-nocookie.com/embed/{$m[1]}",
            (bool) preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) => "https://player.vimeo.com/video/{$m[1]}",
            (bool) preg_match('~loom\.com/share/([\w-]+)~', $url, $m) => "https://www.loom.com/embed/{$m[1]}",
            default => null,
        };
    }

    /** Thumbnail for YouTube links (others show an icon). */
    public function thumbnailUrl(): ?string
    {
        if ($this->isImage()) {
            return $this->viewUrl();
        }

        if ($this->isLink() && preg_match('~youtube-nocookie\.com/embed/([\w-]+)~', (string) $this->embedUrl(), $m)) {
            return "https://i.ytimg.com/vi/{$m[1]}/hqdefault.jpg";
        }

        return null;
    }

    /** Inline preview URL (images, PDFs, videos) served through an authorised route. */
    public function viewUrl(): ?string
    {
        return ! $this->isLink() && ($this->isImage() || $this->isPdf() || $this->isVideo())
            ? route('client-files.view', $this)
            : null;
    }

    public function humanSize(): string
    {
        return $this->isLink() ? '' : Number::fileSize($this->size);
    }

    public function typeLabel(): string
    {
        return match ($this->category()) {
            'image' => 'Image',
            'pdf' => 'PDF',
            'video' => $this->isLink() ? $this->linkProvider().' video' : 'Video',
            'link' => $this->linkProvider().' link',
            default => strtoupper((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION)) ?: 'File',
        };
    }
}
