<?php
$content = file_get_contents('user.php');
preg_match('/<script>(.*?)<\/script>/s', $content, $matches);
file_put_contents('test.js', $matches[1]);
echo "Extracted.\n";
