<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationSetting extends Model
{
    protected $fillable = ['group', 'key', 'label', 'value', 'type', 'description', 'is_public', 'updated_by'];
    protected function casts(): array { return ['is_public' => 'boolean']; }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }
}
