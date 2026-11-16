<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccess extends Model
{
    protected $fillable = ['document_id', 'user_id', 'role_id', 'can_download'];
    protected function casts(): array { return ['can_download' => 'boolean']; }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function role(): BelongsTo { return $this->belongsTo(\Spatie\Permission\Models\Role::class); }
}
