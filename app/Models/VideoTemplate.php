<?php
namespace App\Models;

use TikScraper\Models\Info;

/**
* Base for templates with a feed
*/
class VideoTemplate extends BaseTemplate {
    public object $item;
    public Info $info;
    public string $layout = 'hero';
    /** Vista para iframe (/embed/v2/<id>): solo el reproductor, sin autoplay. */
    public bool $isEmbed = false;

    function __construct(object $item, Info $info, bool $isEmbed = false) {
        parent::__construct('Video');
        $this->item = $item;
        $this->info = $info;
        $this->isEmbed = $isEmbed;
        if ($isEmbed) {
            $this->layout = 'embed';
        } else {
            $this->layout = 'hero';
        }
    }
}
