<?php

$public = dirname(__DIR__).DIRECTORY_SEPARATOR.'public';
$font = 'C:\\Windows\\Fonts\\segoeuib.ttf';

$render = function (int $size) use ($font) {
    $image = imagecreatetruecolor($size, $size);
    imageantialias($image, true);
    imagesavealpha($image, true);

    $charcoal = imagecolorallocate($image, 23, 19, 16);
    $ember = imagecolorallocate($image, 228, 87, 46);
    $gold = imagecolorallocate($image, 201, 162, 39);
    $cream = imagecolorallocate($image, 251, 247, 240);

    imagefilledrectangle($image, 0, 0, $size, $size, $charcoal);

    $inset = (int) round($size * 0.09);
    $diameter = $size - ($inset * 2);
    imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), $diameter, $diameter, $ember);
    imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), (int) ($diameter * 0.86), (int) ($diameter * 0.86), $charcoal);

    $fontSize = $size * 0.27;
    $text = 'BK';
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = $box[2] - $box[0];
    $textHeight = $box[7] - $box[1];
    $x = (int) (($size - $textWidth) / 2);
    $y = (int) (($size - $textHeight) / 2 - $box[1]);

    imagettftext($image, $fontSize, 0, $x + (int) round($size * 0.012), $y + (int) round($size * 0.012), $gold, $font, $text);
    imagettftext($image, $fontSize, 0, $x, $y, $cream, $font, $text);

    return $image;
};

$icon = $render(512);
imagepng($icon, $public.DIRECTORY_SEPARATOR.'icon.png');

$sizes = [256, 128, 64, 48, 32, 16];
$images = [];

foreach ($sizes as $size) {
    $image = $render($size);
    ob_start();
    imagepng($image, null, 9);
    $images[$size] = ob_get_clean();
    imagedestroy($image);
}

$header = pack('vvv', 0, 1, count($sizes));
$offset = 6 + (16 * count($sizes));
$entries = '';
$data = '';

foreach ($sizes as $size) {
    $blob = $images[$size];
    $entries .= pack('CCCCvvVV', $size === 256 ? 0 : $size, $size === 256 ? 0 : $size, 0, 0, 1, 32, strlen($blob), $offset);
    $offset += strlen($blob);
    $data .= $blob;
}

file_put_contents($public.DIRECTORY_SEPARATOR.'icon.ico', $header.$entries.$data);
file_put_contents($public.DIRECTORY_SEPARATOR.'favicon.ico', $header.$entries.$data);

imagedestroy($icon);

echo "Icons written to public/icon.png, public/icon.ico and public/favicon.ico\n";
