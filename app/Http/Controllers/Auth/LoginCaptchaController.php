<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginSecurity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginCaptchaController extends Controller
{
    public function __invoke(Request $request, string $portal): Response
    {
        $guard = $portal === 'main-admin' ? 'admin' : $portal;
        $answer = LoginSecurity::issueCaptcha($request, $guard);

        $image = imagecreatetruecolor(190, 64);
        $background = imagecolorallocate($image, 238, 247, 255);
        $ink = imagecolorallocate($image, 7, 65, 150);
        $noise = imagecolorallocate($image, 105, 158, 220);
        imagefilledrectangle($image, 0, 0, 190, 64, $background);

        for ($index = 0; $index < 9; $index++) {
            imageline($image, random_int(0, 190), random_int(0, 64), random_int(0, 190), random_int(0, 64), $noise);
        }
        for ($index = 0; $index < 100; $index++) {
            imagesetpixel($image, random_int(0, 189), random_int(0, 63), $noise);
        }

        foreach (str_split($answer) as $index => $character) {
            imagestring($image, 5, 24 + ($index * 31), 23 + random_int(-4, 4), $character, $ink);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
