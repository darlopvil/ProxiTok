<?php
namespace App\Controllers;

use App\Helpers\Misc;
use App\Helpers\Wrappers;
use App\Models\CommentsTemplate;
use TikScraper\Sender;

/**
 * Comentarios de un vídeo. Devuelve un FRAGMENTO HTML ya renderizado
 * (no JSON): el marcado vive solo en comments.latte y el JS se limita
 * a inyectarlo. El endpoint /comment/list/ no está en TikScraper\Api,
 * así que se instancia Sender directamente (mismo patrón que Discover).
 */
class CommentsController {
    private const PER_PAGE = 20;

    public static function get(string $aweme_id) {
        $cursor = isset($_GET['cursor']) && is_numeric($_GET['cursor']) ? max(0, intval($_GET['cursor'])) : 0;
        $sender = new Sender(Misc::getScraperOptions());
        $res = $sender->sendApi('/comment/list/', [
            'aweme_id' => $aweme_id,
            'count' => self::PER_PAGE,
            'cursor' => $cursor
        ], '/video/' . $aweme_id);
        self::render($res, $aweme_id, $cursor, false);
    }

    public static function replies(string $aweme_id, string $comment_id) {
        $sender = new Sender(Misc::getScraperOptions());
        $res = $sender->sendApi('/comment/list/reply/', [
            'item_id' => $aweme_id,
            'comment_id' => $comment_id,
            'count' => 50,
            'cursor' => 0
        ], '/video/' . $aweme_id);
        self::render($res, $aweme_id, 0, true);
    }

    private static function render($res, string $aweme_id, int $cursor, bool $isReplies) {
        $body = $res->jsonBody ?? null;
        if ($body === null || ($body->status_code ?? -1) !== 0) {
            http_response_code(502);
            echo '<p class="has-text-danger has-text-centered">No se pudieron cargar los comentarios.</p>';
            return;
        }
        $tpl = new CommentsTemplate(
            $body->comments ?? [],
            intval($body->total ?? 0),
            $cursor,
            !empty($body->has_more),
            $aweme_id,
            $isReplies
        );
        header('Content-Type: text/html; charset=utf-8');
        echo Wrappers::latte('comments', $tpl, true);
    }
}