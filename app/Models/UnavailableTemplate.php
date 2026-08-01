<?php
namespace App\Models;

/**
 * Página limpia para contenido que TikTok no sirve (cuenta borrada, vídeo no
 * disponible...). Replica el mensaje oficial de TikTok en vez del error técnico.
 */
class UnavailableTemplate extends BaseTemplate {
    public string $heading;
    public string $subtitle;
    public string $icon;
    public string $home_label;

    function __construct(string $heading, string $subtitle, string $icon, string $home_label) {
        parent::__construct($heading);
        $this->heading = $heading;
        $this->subtitle = $subtitle;
        $this->icon = $icon;
        $this->home_label = $home_label;
    }
}