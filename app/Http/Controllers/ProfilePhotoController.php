<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProfilePhotoController extends Controller
{
    private const SIZE = 512; // la photo est recadrée en carré de 512 × 512 px

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200'],
        ], [
            'photo.image' => __('Choisissez une image (JPG, PNG ou WebP).'),
            'photo.mimes' => __('Choisissez une image (JPG, PNG ou WebP).'),
            'photo.max' => __('La photo ne doit pas dépasser 5 Mo.'),
            'photo.dimensions' => __('La photo doit mesurer au moins 200 × 200 pixels.'),
        ]);

        $user = $request->user();
        $path = $this->storeSquare($request->file('photo'), $user->id);

        if ($user->avatar_path && $user->avatar_path !== $path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return back()->with('success', __('Votre photo de profil a été mise à jour.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('success', __('Votre photo de profil a été retirée.'));
    }

    /**
     * Recadre l'image au centre en carré et l'enregistre en JPEG léger (GD).
     * Sans GD, l'image d'origine est conservée telle quelle.
     */
    private function storeSquare(UploadedFile $file, int $userId): string
    {
        $name = 'avatars/'.$userId.'-'.Str::random(12);

        try {
            if (! function_exists('imagecreatefromstring')) {
                throw new \RuntimeException('GD indisponible');
            }
            $source = imagecreatefromstring($file->get());
            if (! $source) {
                throw new \RuntimeException('Image illisible');
            }
            $w = imagesx($source);
            $h = imagesy($source);
            $side = min($w, $h);
            $square = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255)); // fond blanc pour les PNG transparents
            imagecopyresampled($square, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

            ob_start();
            imagejpeg($square, null, 85);
            $jpeg = ob_get_clean();
            imagedestroy($square);
            imagedestroy($source);

            Storage::disk('public')->put($name.'.jpg', $jpeg);

            return $name.'.jpg';
        } catch (Throwable) {
            return $file->storeAs('avatars', basename($name).'.'.$file->extension(), 'public');
        }
    }
}
