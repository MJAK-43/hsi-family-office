<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'version_number', 'original_name', 'file_name', 'file_path', 'mime_type', 'file_size', 'sha256', 'scan_status', 'change_note', 'uploaded_by'];
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
