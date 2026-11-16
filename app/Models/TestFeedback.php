<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Résultat donné par un testeur sur une tâche de la recette. */
class TestFeedback extends Model
{
    public const STATUS_UNTESTED = 'untested';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_TO_REVIEW = 'to_review';

    public const STATUSES = [self::STATUS_UNTESTED, self::STATUS_VALIDATED, self::STATUS_TO_REVIEW];

    public const STATUS_LABELS = [
        self::STATUS_UNTESTED => 'Pas testé',
        self::STATUS_VALIDATED => 'Validé',
        self::STATUS_TO_REVIEW => 'À revoir',
    ];

    protected $table = 'test_feedback';

    protected $fillable = ['task_key', 'tester_name', 'tester_key', 'status', 'comment', 'ip_address'];

    public function attachments(): HasMany
    {
        return $this->hasMany(TestFeedbackAttachment::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TestFeedbackReply::class)->orderBy('created_at')->orderBy('id');
    }

    /** « marie  K. » et « Marie k. » désignent la même personne. */
    public static function testerKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
