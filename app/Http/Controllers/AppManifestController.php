<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;

/**
 * Makes the system installable as a phone/desktop app (PWA): web app manifest, square
 * app icons drawn from the organisation logo in Settings, and the public /install page
 * the "Install app" QR code points to. All public - fetched before anyone logs in.
 * (public/sw.js is the service worker; it deliberately caches nothing.)
 */
class AppManifestController extends Controller
{
    private const THEME = '#0f2444';
    private const SIZES = [180, 192, 512];

    public function manifest()
    {
        $name = (string) SystemSetting::get('org_name', 'ElTech Finance');
        $v    = self::iconVersion();

        return response()->json([
            'name'             => $name,
            'short_name'       => mb_substr($name, 0, 15),
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => self::THEME,
            'theme_color'      => self::THEME,
            'icons'            => [
                ['src' => route('app-icon', 192) . "?v={$v}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => route('app-icon', 512) . "?v={$v}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES);
    }

    public function icon(int $size)
    {
        abort_unless(in_array($size, self::SIZES, true), 404);

        $logoPath = self::logoPath();
        $orgName  = (string) SystemSetting::get('org_name', 'ElTech Finance');

        $cache = storage_path('app/app-icons/' . md5(self::iconVersion() . $orgName . $size . 'navy') . '.png');
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

    /** The page the "Install app" QR code opens on a phone. */
    public function install()
    {
        return view('install', [
            'orgName' => (string) SystemSetting::get('org_name', 'ElTech Finance'),
        ]);
    }

    /** Changes whenever the logo file changes, so icon URLs (?v=) refresh on phones. */
    public static function iconVersion(): string
    {
        $path = self::logoPath();

        return substr(md5(($path ?? 'none') . ($path ? filemtime($path) : '')), 0, 10);
    }

    private static function logoPath(): ?string
    {
        $logo = (string) SystemSetting::get('org_logo', '');
        $path = $logo !== '' ? public_path($logo) : null;

        return $path && is_file($path) ? $path : null;
    }

    /** Logo scaled into the centre 76% on the brand navy. */
    private function fromLogo(?string $path, int $size)
    {
        if (!$path) {
            return null;
        }
        $data = @file_get_contents($path);
        $src  = $data !== false ? @imagecreatefromstring($data) : false; // false for SVG
        if (!$src) {
            return null;
        }

        $canvas = $this->navyCanvas($size);
        imagealphablending($canvas, true);

        $box   = (int) round($size * 0.76);
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
        $canvas = $this->navyCanvas($size);

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
        imagecopyresized($canvas, $small, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $tw, $th);
        imagedestroy($small);

        return $canvas;
    }

    private function navyCanvas(int $size)
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0x0f, 0x24, 0x44));

        return $canvas;
    }
}
