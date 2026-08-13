<?php
namespace App\Controllers;

use App\Helpers\ErrorHandler;
use App\Helpers\Misc;
use App\Helpers\Wrappers;
use App\Models\FeedTemplate;
use TikScraper\Models\Feed;
use TikScraper\Sender;

/**
 * Discover: búsqueda de vídeos por palabra clave.
 * Reproduce el contenido de la landing SEO /discover/<palabra> de TikTok,
 * que por debajo es app_search. Usa el endpoint web /search/item/full/
 * (solo vídeos, itemStruct estándar) firmado por carcabot vía Sender.
 * No pasa por TikScraper\Api porque ese endpoint no está expuesto allí.
 */
class DiscoverController {
    // web_search_code fijo que envía el frontend web de TikTok en /search
    private const WEB_SEARCH_CODE = '{"tiktok":{"client_params_x":{"search_engine":{"ies_mt_user_live_video_card_use_libra":1,"mt_search_general_user_live_card":1}},"search_server":{}}}';

    public static function get(string $term) {
        $keyword = trim(rawurldecode($term));
        if ($keyword === '') {
            ErrorHandler::showText(400, 'Empty search term');
            return;
        }

        $cursor = isset($_GET['cursor']) && is_numeric($_GET['cursor']) ? intval($_GET['cursor']) : 0;

        $sender = new Sender(Misc::getScraperOptions());
        $res = $sender->sendApi('/search/item/full/', [
            'keyword' => $keyword,
            'cursor' => $cursor,
            'count' => 20,
            'from_page' => 'search',
            'web_search_code' => self::WEB_SEARCH_CODE
        ], '/search');

        // El endpoint de búsqueda devuelve snake_case; Feed::fromReq espera camelCase.
        if (isset($res->jsonBody)) {
            if (isset($res->jsonBody->item_list) && !isset($res->jsonBody->itemList)) {
                $res->jsonBody->itemList = $res->jsonBody->item_list;
            }
        }

        $feed = Feed::fromReq($res);
        // El search anónimo no pagina de forma fiable (la sesión con estado exigiría
        // login, high risk / low reward). Nos quedamos con la primera tanda.
        $feed->hasMore = false;

        if ($feed->meta->success && count($feed->items) > 0) {
            Wrappers::latte('discover', new FeedTemplate($keyword, $feed));
        } else {
            ErrorHandler::showUnavailable('discover');
        }
    }
}