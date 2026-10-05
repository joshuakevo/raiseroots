<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;

/**
 * Makes the system installable on phones ("Add to Home Screen"): a web app manifest
 * and square home-screen icons generated from the organisation logo in Settings, so
 * each deployment shows its own name and logo. Public - the phone fetches these
 * before anyone logs in.
 */
class AppManifestController extends Controller
{
    private const THEME = '#0f2444';
    private const SIZES = [180, 192, 512];

    public function manifest()
    {
        $name = (string) SystemSetting::get('org_name', 'ElTech Finance');

        return response()->json([
            'name'             => $name,
            'short_name'       => mb_strimwidth($name, 0, 12, ''),
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => self::THEME,
            'icons'            => [
                ['src' => route('app-icon', 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app-icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app-icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function icon(int $size)
    {
        abort_unless(in_array($size, self::SIZES, true), 404);

        $logo     = (string) SystemSetting::get('org_logo', '');
        $logoPath = $logo !== '' ? public_path($logo) : null;
        $orgName  = (string) SystemSetting::get('org_name', 'ElTech Finance');

        // Cached per logo file + size; a new logo upload gets a new filename, so a new icon.
        $key   = md5(($logoPath && is_file($logoPath) ? $logoPath . filemtime($logoPath) : 'initials:' . $orgName) . $size);
        $cache = storage_path("app/app-icons/{$key}.png");

        if (!is_file($cache)) {
            if (!is_dir(dirname($cache))) {
                mkdir(dirname($cache), 0755, true);
            }
            $image = $this->fromLogo($logoPath, $size) ?? $this->fromInitials($orgName, $size);
            imagepng($image, $cache);
            imagedestroy($image);
        }

        return response()->file($cache, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** Logo centred on white, inside the safe zone phones keep when they round/crop icons. */
    private function fromLogo(?string $path, int $size)
    {
        if (!$path || !is_file($path)) {
            return null;
        }
        $data = @file_get_contents($path);
        $src  = $data !== false ? @imagecreatefromstring($data) : false; // false for SVG
        if (!$src) {
            return null;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        $box   = (int) round($size * 0.72);
        $w     = imagesx($src);
        $h     = imagesy($src);
        $scale = min($box / $w, $box / $h);
        $dw    = (int) round($w * $scale);
        $dh    = (int) round($h * $scale);
        imagecopyresampled($canvas, $src, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($src);

        return $canvas;
    }

    /** No usable logo: the organisation's initials, white on the brand navy. */
    private function fromInitials(string $name, int $size)
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0x0f, 0x24, 0x44));

        $words    = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['E'];
        $initials = strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));

        // Built-in GD font scaled up - no font files needed on the server.
        $font  = 5;
        $tw    = imagefontwidth($font) * strlen($initials);
        $th    = imagefontheight($font);
        $small = imagecreatetruecolor($tw, $th);
        $navy  = imagecolorallocate($small, 0x0f, 0x24, 0x44);
        imagefill($small, 0, 0, $navy);
        imagecolortransparent($small, $navy);
        imagestring($small, $font, 0, 0, $initials, imagecolorallocate($small, 255, 255, 255));

        $scale = ($size * 0.42) / $th;
        $dw    = (int) round($tw * $scale);
        $dh    = (int) round($th * $scale);
        imagecopyresampled($canvas, $small, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $tw, $th);
        imagedestroy($small);

        return $canvas;
    }
}
