<?php
function optimizeImage($source, $destination, $quality) {
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
        // Also create webp
        imagewebp($image, str_replace('.jpg', '.webp', $destination), $quality);
        imagedestroy($image);
        return true;
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, $quality / 10);
        imagewebp($image, str_replace('.png', '.webp', $destination), $quality);
        imagedestroy($image);
        return true;
    }
    return false;
}

echo "Optimizing header.jpg...\n";
if (file_exists('images/header.jpg')) {
    optimizeImage('images/header.jpg', 'images/header_opt.jpg', 60);
    echo "Done header.jpg\n";
}

echo "Optimizing lc.png...\n";
if (file_exists('images/lc.png')) {
    optimizeImage('images/lc.png', 'images/lc_opt.png', 60);
    echo "Done lc.png\n";
}
?>
