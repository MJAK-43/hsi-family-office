<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pièce jointe (capture, PDF, vidéo) d'un retour de recette, stockée sur le disque privé. */
class TestFeedbackAttachment extends Model
{
    protected $fillable = ['test_feedback_id', 'path', 'original_name', 'mime_type', 'size'];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(TestFeedback::class, 'test_feedback_id');
    }
}
