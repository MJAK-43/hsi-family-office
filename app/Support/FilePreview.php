<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aperçu d'un fichier dans le navigateur avant téléchargement.
 * Seuls les formats sans risque (PDF, images, texte brut) sont affichés ; les autres sont téléchargés.
 */
class FilePreview
{
    public const PREVIEWABLE = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'txt' => 'text/plain', 'csv' => 'text/plain',
    ];

    public static function kind(?string $name): ?string
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return match (true) {
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'image',
            in_array($ext, ['txt', 'csv'], true) => 'text',
            default => null,
        };
    }

    public static function wantsInline(): bool
    {
        return request()->boolean('inline');
    }

    public static function respond(string $path, string $name): Response
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = self::PREVIEWABLE[$ext] ?? null;
        abort_unless(Storage::disk('local')->exists($path), 404);
        if (! $mime) return Storage::disk('local')->download($path, $name);

        $safeName = str_replace(['"', "\r", "\n"], '', $name);

        $headers = [
            'Content-Type' => $mime.($mime === 'text/plain' ? '; charset=utf-8' : ''),
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        // Le lecteur PDF du navigateur ne fonctionne pas dans un contexte « sandbox ».
        if ($mime !== 'application/pdf') $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";

        return Storage::disk('local')->response($path, $name, $headers);
    }
}
