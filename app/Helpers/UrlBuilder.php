<?php
namespace App\Helpers;

class UrlBuilder {
    public static function stream(string $url): string {
        return Misc::url('/stream?url=' . urlencode($url));
    }

    public static function download(string $url, string $username, string $id, bool $watermark): string {
        $down_url = Misc::url('/download?url=' . urlencode($url) . '&id=' . $id . '&user=' . $username);
        if ($watermark) $down_url .= '&watermark=1';
        return $down_url;
    }

    // Reproducción de vídeo vía sidecar yt-dlp (esquiva webapp-prime gated)
    public static function video_stream(string $username, string $id): string {
        return Misc::url('/video?id=' . urlencode($id) . '&user=' . urlencode($username));
    }

    public static function audio_stream(string $username, string $id): string {
        return Misc::url('/audio?id=' . urlencode($id) . '&user=' . urlencode($username));
    }

    // Descarga de vídeo vía sidecar yt-dlp
    public static function video_download(string $username, string $id, bool $watermark): string {
        $u = Misc::url('/dl?id=' . urlencode($id) . '&user=' . urlencode($username));
        if ($watermark) $u .= '&watermark=1';
        return $u;
    }

    public static function user(string $username): string {
        return Misc::url('/@' . $username);
    }

    public static function tag(string $tag): string {
        return Misc::url('/tag/' . $tag);
    }

    public static function video_internal(string $username, string $id): string {
        return Misc::url('/@' . $username . "/video/" . $id);
    }

    public static function video_external(string $username, string $id): string {
        return "https://www.tiktok.com/@" . $username . "/video/" . $id;
    }
}