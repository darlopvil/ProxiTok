<?php
namespace App\Helpers;

use App\Cache\ApcuCache;
use App\Cache\JSONCache;
use App\Cache\RedisCache;
use App\Constants\CacheMethods;
use App\Constants\TextExtras;
use App\Models\BaseTemplate;

class Wrappers {
    /**
     * #hashtag: el lookbehind evita casar dentro de palabras o de entidades HTML (&#039;),
     * pero admite otro # delante (##gamedev); el token acaba en espacio, otro #/@ o un
     * carácter que rompería el href.
     */
    private const HASHTAG = '/(?<![\p{L}\p{N}_&])#([^\s#@<>&"\'\/?]+)/u';

    /** Separa la puntuación final ("#ps2," → ["ps2", ","]), que no forma parte del hashtag. */
    private static function splitTrail(string $s): array {
        preg_match('/^(.*?)([.,;:!)\]}]*)$/su', $s, $p);
        return [$p[1], $p[2]];
    }

    /** Escapa un trozo de texto y enlaza sus hashtags. */
    private static function linkHashtags(string $text): string {
        return preg_replace_callback(self::HASHTAG, function ($m) {
            [$name, $trail] = self::splitTrail($m[1]);
            if ($name === '') return $m[0];
            return '<a href="' . UrlBuilder::tag($name) . '">#' . $name . '</a>' . $trail;
        }, htmlspecialchars($text));
    }
    /**
     * Setup of Latte template engine
     */
    public static function latte(string $template, BaseTemplate $base, bool $return = false): ?string {
        $latte = new \Latte\Engine;
        $cache_path = Misc::env('LATTE_CACHE', __DIR__ . '/../../cache/latte');
        $latte->setTempDirectory($cache_path);

        // -- CUSTOM FUNCTIONS -- //
        // Get URL with optional endpoint
        $latte->addFunction('path', function (string $endpoint = ''): string {
            return Misc::url($endpoint);
        });

        // Static assets
        $latte->addFunction('assets', function (string $type, string $file, bool $isVendor = false): string {
            $endpoint = '';
            switch ($type) {
                case 'js':
                    $endpoint .= '/scripts';
                    break;
                case 'css':
                    $endpoint .= '/styles';
                    break;
                default:
                    throw new \Exception('Invalid asset type');
            }

            if ($isVendor) $endpoint .= '/vendor';

            $endpoint .= '/' . $file;

            return Misc::url($endpoint);
        });

        $latte->addFunction('theme', function(): string {
            return Cookies::theme();
        });

        // Version being used
        $latte->addFunction('version_frontend', function (): string {
            return \Composer\InstalledVersions::getVersion('pablouser1/proxitok');
        });
        $latte->addFunction('version_scraper', function (): string {
            return \Composer\InstalledVersions::getVersion('pablouser1/tikscraper');
        });

        // https://stackoverflow.com/a/36365553
        $latte->addFunction('number', function (float $x) {
            if($x > 1000) {
                $x_number_format = number_format($x);
                $x_array = explode(',', $x_number_format);
                $x_parts = array('K', 'M', 'B', 'T');
                $x_count_parts = count($x_array) - 1;
                $x_display = $x;
                $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
                $x_display .= $x_parts[$x_count_parts - 1];
                return $x_display;
            }
            return $x;
        });

        // UrlBuilder
        $latte->addFunction('url_stream', function (string $url): string {
            return UrlBuilder::stream($url);
        });
        $latte->addFunction('url_user', function (string $username): string {
            return UrlBuilder::user($username);
        });
        $latte->addFunction('url_tag', function (string $tag): string {
            return UrlBuilder::tag($tag);
        });
        $latte->addFunction('url_video_internal', function (string $username, string $id): string {
            return UrlBuilder::video_internal($username, $id);
        });
        $latte->addFunction('url_video_external', function (string $username, string $id): string {
            return UrlBuilder::video_external($username, $id);
        });
        $latte->addFunction('url_video_stream', function (string $username, string $id): string {
            return UrlBuilder::video_stream($username, $id);
        });
        $latte->addFunction('url_music', function (object $item): string {
            if (isset($item->music->playUrl) && $item->music->playUrl !== '') {
                return UrlBuilder::stream($item->music->playUrl);
            }
            return UrlBuilder::audio_stream($item->author->uniqueId ?? '', (string) $item->id);
        });
        $latte->addFunction('url_video_download', function (string $username, string $id, bool $watermark = false): string {
            return UrlBuilder::video_download($username, $id, $watermark);
        });
        $latte->addFunction('url_download', function (string $url, string $username, string $id, bool $watermark): string {
            return UrlBuilder::download($url, $username, $id, $watermark);
        });

        $latte->addFunction('bool_to_str', function (bool $cond): string {
            return $cond ? 'yes' : 'no';
        });

        // Descripción enlazada:
        // - #hashtags detectados en el propio texto (los feeds de perfil de ttdlp no traen
        //   textExtra), emojis incluidos (#indonesia🇮🇩).
        // - @menciones por las posiciones de textExtra (en unidades UTF-16): TikTok escribe en
        //   el texto el NOMBRE visible (@🔪AGGRO CRAB🦀), no el usuario, así que no se puede
        //   casar por texto.
        $latte->addFunction('render_desc', function (string $desc, array $textExtras = []): string {
            $u16 = mb_convert_encoding($desc, 'UTF-16LE', 'UTF-8');
            $len = intdiv(strlen($u16), 2);
            $spans = [];
            foreach ($textExtras as $e) {
                if (empty($e->userUniqueId) || !isset($e->start, $e->end)) continue;
                $start = (int) $e->start; $end = (int) $e->end;
                if ($start >= 0 && $start < $end && $end <= $len) $spans[] = [$start, $end, $e->userUniqueId];
            }
            usort($spans, fn($x, $y) => $x[0] <=> $y[0]);

            $piece = fn(int $from, int $to) => mb_convert_encoding(substr($u16, $from * 2, ($to - $from) * 2), 'UTF-8', 'UTF-16LE');
            $out = ''; $pos = 0;
            foreach ($spans as [$start, $end, $user]) {
                if ($start < $pos) continue; // solapada con la anterior
                $out .= self::linkHashtags($piece($pos, $start));
                $out .= '<a href="' . UrlBuilder::user($user) . '">' . htmlspecialchars($piece($start, $end)) . '</a>';
                $pos = $end;
            }
            return $out . self::linkHashtags($piece($pos, $len));
        });

        // Hashtags del vídeo que NO aparecen ya en la descripción (allí ya van enlazados).
        $latte->addFunction('extra_challenges', function (array $challenges, string $desc): array {
            preg_match_all(self::HASHTAG, $desc, $m);
            $inDesc = [];
            foreach ($m[1] as $t) $inDesc[mb_strtolower(self::splitTrail($t)[0])] = true;
            return array_values(array_filter($challenges, function ($c) use ($inDesc) {
                return isset($c->title) && $c->title !== '' && !isset($inDesc[mb_strtolower($c->title)]);
            }));
        });

        if ($return) {
            return $latte->renderToString(Misc::getView($template), $base);
        }
        $latte->render(Misc::getView($template), $base);
        return null;
    }

    /**
     * Setup of TikTok Api wrapper
     */
    public static function api(): \TikScraper\Api {
        $options = Misc::getScraperOptions();
        // Cache config
        $cacheEngine = null;
        if (isset($_ENV['API_CACHE'])) {
            switch ($_ENV['API_CACHE']) {
                case CacheMethods::JSON:
                    $cacheEngine = new JSONCache();
                    break;
                case CacheMethods::APCU:
                    $cacheEngine = new ApcuCache();
                    break;
                case CacheMethods::REDIS:
                    if (!(isset($_ENV['REDIS_URL']) || isset($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']))) {
                        throw new \Exception('You need to set REDIS_URL or REDIS_HOST and REDIS_PORT to use Redis Cache!');
                    }

                    if (isset($_ENV['REDIS_URL'])) {
                        $url = parse_url($_ENV['REDIS_URL']);
                        $host = $url['host'];
                        $port = $url['port'];
                        $password = $url['pass'] ?? null;
                    } else {
                        $host = $_ENV['REDIS_HOST'];
                        $port = intval($_ENV['REDIS_PORT']);
                        $password = $_ENV['REDIS_PASSWORD'] ?? null;
                    }
                    $cacheEngine = new RedisCache($host, $port, $password);
                    break;
            }
        }

        return new \TikScraper\Api($options, $cacheEngine);
    }
}
