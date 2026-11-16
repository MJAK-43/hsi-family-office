<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDownload extends Model
{
    public $timestamps = false;
    protected $fillable = ['document_id', 'document_version_id', 'user_id', 'ip_address', 'user_agent', 'downloaded_at'];
    protected function casts(): array { return ['downloaded_at' => 'datetime']; }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function version(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'document_version_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
