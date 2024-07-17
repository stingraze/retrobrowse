<?php
//(C)Tsubasa Kato - 2024/7/17 22:21PM JST - Created with help from ChatGPT (GPT-4o)
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function convertHTML5toIE3($htmlContent, $baseUrl, $basePath, $imageDir, $webImageDir) {
    // Define the replacements for HTML5 elements and attributes
    $replacements = [
        // HTML5 tags to be replaced or removed
        'header' => 'div',
        'footer' => 'div',
        'nav' => 'div',
        'article' => 'div',
        'section' => 'div',
        'aside' => 'div',
        'figure' => 'div',
        'figcaption' => 'div',
        'main' => 'div',
        // HTML5 attributes to be removed
        'autofocus' => '',
        'required' => '',
        'placeholder' => '',
    ];

    // Regular expressions for finding HTML5 tags and attributes
    $tagRegex = '/<\s*(\/?)\s*(header|footer|nav|article|section|aside|figure|figcaption|main)(\s|>)/i';
    $attrRegex = '/\s*(autofocus|required|placeholder)="[^"]*"/i';

    // Replace HTML5 tags
    $htmlContent = preg_replace_callback($tagRegex, function($matches) use ($replacements) {
        $replacement = $replacements[strtolower($matches[2])];
        return "<{$matches[1]}$replacement{$matches[3]}";
    }, $htmlContent);

    // Remove HTML5 attributes
    $htmlContent = preg_replace($attrRegex, '', $htmlContent);

    // Simplify CSS and JavaScript (example: removing unsupported CSS properties)
    $cssRegex = '/(display\s*:\s*flex\s*;|grid\s*;|flexbox\s*;)/i';
    $htmlContent = preg_replace($cssRegex, 'display: block;', $htmlContent);

    // Extract image tags and process images in parallel
    $imgTags = [];
    $htmlContent = preg_replace_callback('/<img[^>]+>/i', function($matches) use ($basePath, &$imgTags) {
        $imgTag = $matches[0];
        if (preg_match('/src="([^"]+)"/i', $imgTag, $srcMatches)) {
            $src = $srcMatches[1];
            if (!preg_match('/^(http|https):\/\//i', $src)) {
                $src = rtrim($basePath, '/') . '/' . ltrim($src, '/');
            }
            $imgTags[] = [$imgTag, $src, $srcMatches[1]];
        }
        return $imgTag;
    }, $htmlContent);

    // Process images in parallel using curl_multi
    $convertedImages = processImagesInParallel($imgTags, $imageDir, $webImageDir);

    // Update image tags with new src
    foreach ($convertedImages as $originalSrc => $newSrc) {
        $htmlContent = str_replace($originalSrc, $newSrc, $htmlContent);
    }

    // Rewrite URLs to redirect through the converter with absolute paths
    $htmlContent = preg_replace_callback('/<a[^>]+href="([^"]+)"[^>]*>/i', function($matches) use ($baseUrl, $basePath) {
        $url = $matches[1];
        // Convert relative URLs to absolute URLs
        if (!preg_match('/^(http|https):\/\//i', $url)) {
            $url = rtrim($basePath, '/') . '/' . ltrim($url, '/');
        }
        $newUrl = $baseUrl . '?url=' . $url;
        return str_replace($matches[1], htmlspecialchars($newUrl), $matches[0]);
    }, $htmlContent);

    // Return the converted HTML content
    return $htmlContent;
}

function processImagesInParallel($imgTags, $imageDir, $webImageDir) {
    $mh = curl_multi_init();
    $chArray = [];
    $convertedImages = [];

    foreach ($imgTags as $imgData) {
        [$imgTag, $src, $originalSrc] = $imgData;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $src);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_multi_add_handle($mh, $ch);
        $chArray[] = ['handle' => $ch, 'originalSrc' => $originalSrc];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($chArray as $chData) {
        $ch = $chData['handle'];
        $originalSrc = $chData['originalSrc'];
        $imageContent = curl_multi_getcontent($ch);
        if ($imageContent !== false) {
            $newSrc = resizeAndConvertImageContent($imageContent, $imageDir, $webImageDir);
            if ($newSrc !== false) {
                $convertedImages[$originalSrc] = $newSrc;
            }
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $convertedImages;
}

function resizeAndConvertImageContent($imageContent, $imageDir, $webImageDir) {
    // Create an image resource from the content
    $image = imagecreatefromstring($imageContent);
    if ($image === false) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Resize if the width is over 640 pixels
    if ($width > 640) {
        $newWidth = $width * 0.2;
        $newHeight = $height * 0.2;
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Create a unique file name for the resized image, convert to .jpg
    $uniqueFileName = uniqid('img_', true) . '.jpg';
    $imageFileName = $imageDir . '/' . $uniqueFileName;
    $webImagePath = $webImageDir . '/' . $uniqueFileName;
    imagejpeg($resizedImage, $imageFileName, 90);

    imagedestroy($image);
    imagedestroy($resizedImage);

    // Return the web path to the resized image
    return $webImagePath;
}

// Function to check if a string is URL encoded
function isUrlEncoded($string) {
    $string = str_replace('%20', '+', $string);
    return urldecode($string) !== $string;
}

// Function to fetch HTML content using CURL
function fetchHtmlContentInParallel($urls) {
    $mh = curl_multi_init();
    $chArray = [];
    $htmlContents = [];

    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_multi_add_handle($mh, $ch);
        $chArray[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($chArray as $ch) {
        $htmlContent = curl_multi_getcontent($ch);
        $htmlContents[] = $htmlContent;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $htmlContents;
}

// Check if the URL parameter is set
if (isset($_GET['url'])) {
    $urls = is_array($_GET['url']) ? $_GET['url'] : [$_GET['url']];

    // Decode URLs if necessary
    foreach ($urls as &$url) {
        if (isUrlEncoded($url)) {
            $url = urldecode($url);
        }
    }

    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

    // Create image directory
    $date = date('Y-m-d');
    $imageDir = '/var/www/html/retrobrowse/img_' . $date;
    $webImageDir = 'img_' . $date;
    if (!file_exists($imageDir)) {
        if (!mkdir($imageDir, 0777, true)) {
            die('Failed to create image directory');
        }
    }

    // Fetch the HTML content from the given URLs using CURL in parallel
    $htmlContents = fetchHtmlContentInParallel($urls);

    foreach ($htmlContents as $htmlContent) {
        if ($htmlContent !== false) {
            // Get the base path of the URL to handle relative links
            $parsedUrl = parse_url($url);
            $basePath = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['path']) ? dirname($parsedUrl['path']) : '') . '/';

            // Convert the HTML content
            $convertedContent = convertHTML5toIE3($htmlContent, $baseUrl, $basePath, $imageDir, $webImageDir);

            // Display the converted content
            header('Content-Type: text/html; charset=UTF-8');
            echo $convertedContent;
        } else {
            echo "Failed to fetch content from the URL.<br>";
        }
    }
} else {
    echo "No URL parameter provided.<br>";
}
?>
