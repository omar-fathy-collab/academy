<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'session_id',
        'group_id',
        'title',
        'description',
        'file_path',
        'provider',
        'provider_id',
        'thumbnail_url',
        'meta_tags',
        'status',
        'duration',
        'uuid',
        'stream_type',
        'disk',
        'hls_metadata',
        'visibility',
        'is_library',
        'price',
    ];

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Group::class, 'video_group', 'video_id', 'group_id');
    }

    protected $casts = [
        'meta_tags' => 'array',
    ];

    /**
     * Boot the model to handle automatic YouTube ID extraction.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($video) {
            if ($video->provider === 'youtube' && !empty($video->file_path)) {
                $video->provider_id = static::extractYoutubeId($video->file_path);
                if (empty($video->thumbnail_url) && $video->provider_id) {
                    $video->thumbnail_url = "https://img.youtube.com/vi/{$video->provider_id}/maxresdefault.jpg";
                }
            } elseif ($video->provider === 'vimeo' && !empty($video->file_path)) {
                $video->provider_id = static::extractVimeoId($video->file_path);
                if (empty($video->thumbnail_url) && $video->provider_id) {
                    // Using a public vumbnail service for Vimeo thumbnails without API key
                    $video->thumbnail_url = "https://vumbnail.com/{$video->provider_id}.jpg";
                }
            }
        });
    }

    /**
     * Helper to extract YouTube ID from diversas URLs.
     */
    public static function extractYoutubeId($url)
    {
        preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"'>]+)/", $url, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Helper to extract Vimeo ID from various URLs.
     */
    public static function extractVimeoId($url)
    {
        preg_match("/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:\/([a-z0-9]+))?(?:$|\/|\?)/", $url, $matches);
        return $matches[3] ?? null;
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }
}
