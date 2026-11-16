<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réponse à un retour de recette (par exemple : « corrigé, vous pouvez retester »). */
class TestFeedbackReply extends Model
{
    protected $fillable = ['test_feedback_id', 'author_name', 'author_key', 'body', 'marks_fixed', 'ip_address'];

    protected function casts(): array
    {
        return ['marks_fixed' => 'boolean'];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(TestFeedback::class, 'test_feedback_id');
    }
}
