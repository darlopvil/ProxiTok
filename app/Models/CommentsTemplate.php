<?php
namespace App\Models;

/**
 * Fragmento de comentarios (se sirve por AJAX, sin layout).
 */
class CommentsTemplate extends BaseTemplate {
    public array $comments;
    public int $total;
    public int $cursor;
    public bool $hasMore;
    public string $awemeId;
    public bool $isReplies;

    function __construct(array $comments, int $total, int $cursor, bool $hasMore, string $awemeId, bool $isReplies = false) {
        parent::__construct('Comments');
        $this->comments = $comments;
        $this->total = $total;
        $this->cursor = $cursor;
        $this->hasMore = $hasMore;
        $this->awemeId = $awemeId;
        $this->isReplies = $isReplies;
    }
}