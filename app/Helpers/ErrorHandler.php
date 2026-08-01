<?php
namespace App\Helpers;

use App\Models\ErrorTemplate;
use App\Models\UnavailableTemplate;
use TikScraper\Constants\Codes;
use TikScraper\Models\Meta;

class ErrorHandler {
    public static function showMeta(Meta $meta, ?string $context = null) {
        // Si el fallo es un código nativo de TikTok (recurso no disponible),
        // mostramos página limpia en vez del error técnico con debug.
        if ($context !== null && self::isUnavailable($meta)) {
            self::showUnavailable($context);
            return;
        }
        http_response_code($meta->httpCode);
        Wrappers::latte('error', new ErrorTemplate($meta->httpCode, $meta->proxitokMsg, $meta->proxitokCode, $meta->response));
    }

    public static function showText(int $code, string $msg) {
        http_response_code($code);
        Wrappers::latte('error', new ErrorTemplate($code, $msg, null, null));
    }

    /**
     * Un código > 10000 que NO pertenece al enum interno Codes es propio de
     * TikTok (10204 vídeo no disponible, 10221 cuenta no encontrada...).
     * VERIFY (10000) sí está en el enum, así que el captcha NO entra aquí y
     * se sigue mostrando como error técnico.
     */
    public static function isUnavailable(Meta $meta): bool {
        return $meta->proxitokCode > 10000 && Codes::tryFrom($meta->proxitokCode) === null;
    }

    public static function showUnavailable(string $context) {
        http_response_code(404);
        if ($context === 'video') {
            $tpl = new UnavailableTemplate(
                'Este video no está disponible actualmente',
                '¿Buscas vídeos? Prueba a buscar por autores, hashtags y sonidos en tendencia.',
                'video',
                'Volver al inicio'
            );
        } else {
            $tpl = new UnavailableTemplate(
                'No se pudo encontrar esta cuenta',
                '¿Buscas vídeos? Prueba a buscar por autores, hashtags y sonidos en tendencia.',
                'user',
                'Volver al inicio'
            );
        }
        Wrappers::latte('unavailable', $tpl);
    }
}