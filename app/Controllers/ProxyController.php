<?php
namespace App\Controllers;

use App\Helpers\Cookies;
use App\Helpers\Misc;

class ProxyController {
    // Sidecar yt-dlp (resuelve y sirve el vídeo reproducible). Interno, red NPM.
    const SIDECAR = 'http://ttdlp_app:8080';

    // Dominios de media de TikTok permitidos para /stream (imágenes, audio, covers).
    const VALID_TIKTOK_DOMAINS = [
        "tiktokcdn.com",
        "tiktokcdn-us.com",
        "tiktokcdn-eu.com",
        "tiktok.com",
        "tiktokv.com",
        "tiktokv.eu",
        "ttwstatic.com",
        "ibyteimg.com"
    ];

    public static function stream() {
        self::checkUrl();
        $url = $_GET['url'];

        $options = Misc::getScraperOptions();
        $streamer = new \TikScraper\Stream($options);
        $streamer->url($url);
    }

    public static function download() {
        self::checkUrl();
        $method = Cookies::downloader();
        $downloader = new \TikScraper\Download($method, Misc::getScraperOptions());

        $id = $_GET['id'] ?? '';
        $watermark = isset($_GET['watermark']);
        $url = $_GET['url'];
        $user = $_GET['user'] ?? '';
        $filename = self::getFilename($id, $user);
        $downloader->url($url, $filename, $watermark);
    }

    // --- Vídeo vía sidecar yt-dlp ---
    public static function videoStream() {
        self::proxySidecar('/video');
    }

    static public function audioStream(): void {
        self::proxySidecar('/audio');
    }

    public static function videoDownload() {
        $extra = isset($_GET['watermark']) ? '&watermark=1' : '';
        self::proxySidecar('/download', $extra);
    }

    static private function proxySidecar(string $path, string $extra = ''): void {
        $id = $_GET['id'] ?? '';
        $user = $_GET['user'] ?? '';
        if (!preg_match('/^\d{6,25}$/', $id)) {
            http_response_code(400);
            die('Invalid id');
        }
        $url = self::SIDECAR . $path . '?id=' . urlencode($id) . '&user=' . urlencode($user) . $extra;

        $send = [];
        if (isset($_SERVER['HTTP_RANGE'])) {
            $send['Range'] = $_SERVER['HTTP_RANGE'];
        }

        $forward = [
            'Content-Type' => null,
            'Content-Length' => null,
            'Content-Range' => null,
            'Content-Disposition' => null,
            'Accept-Ranges' => 'bytes'
        ];

        $client = new \GuzzleHttp\Client(['http_errors' => false, 'timeout' => 120]);
        try {
            $res = $client->get($url, [
                'headers' => $send,
                'stream' => true,
                'on_headers' => function (\Psr\Http\Message\ResponseInterface $response) use (&$forward) {
                    foreach ($response->getHeaders() as $k => $v) {
                        if (array_key_exists($k, $forward)) {
                            $forward[$k] = $v;
                        }
                    }
                }
            ]);

            http_response_code($res->getStatusCode());
            foreach ($forward as $k => $v) {
                if ($v !== null) {
                    if (is_array($v)) {
                        foreach ($v as $vv) header("$k: $vv", false);
                    } else {
                        header("$k: $v", false);
                    }
                }
            }

            $body = $res->getBody();
            while (!$body->eof()) {
                echo $body->read(8192);
            }
        } catch (\Throwable $e) {
            http_response_code(502);
            die('Video resolver error');
        }
    }

    static private function isValidDomain(string $url): bool {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $host_split = explode('.', $host);
        if (count($host_split) < 2) {
            return false;
        }
        $host_domain = $host_split[count($host_split) - 2] . '.' . $host_split[count($host_split) - 1];
        return in_array($host_domain, self::VALID_TIKTOK_DOMAINS);
    }

    static private function checkUrl(): void {
        if (!isset($_GET['url'])) {
            die('You need to send a URL');
        }
        if (!filter_var($_GET['url'], FILTER_VALIDATE_URL) || !self::isValidDomain($_GET['url'])) {
            die('Not a valid URL');
        }
    }

    static private function getFilename(string $id, string $user): string {
        return 'tiktok-video-' . $id . '-' . $user;
    }
}