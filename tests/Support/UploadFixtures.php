<?php

namespace Tests\Support;

final class UploadFixtures
{
    public static function pdf(string $catalogEntries = ''): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj\n"
            .'<< /Type /Catalog '.$catalogEntries." >>\n"
            ."endobj\n"
            ."trailer\n"
            ."<< /Root 1 0 R >>\n"
            ."%%EOF\n";
    }

    public static function png(int $width = 240, int $height = 100): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $ink = imagecolorallocatealpha($image, 20, 20, 20, 0);
        imageline($image, 10, intdiv($height, 2), $width - 10, intdiv($height, 2), $ink);

        ob_start();
        imagepng($image, null, 6);
        $contents = ob_get_clean();
        imagedestroy($image);

        return is_string($contents) ? $contents : '';
    }
}
