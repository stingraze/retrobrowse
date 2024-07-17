<?php
//(C)Tsubasa Kato - Canary Version, bugs to fix still. 7/17/2024 - 16:51PM JST
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function convertHTML5toIE3($htmlContent, $baseUrl, $basePath, $imageDir) {
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

    // Downsize images to 640 pixels in width, maintain aspect ratio, save them on the server, and update the image src
    $htmlContent = preg_replace_callback('/<img[^>]+>/i', function($matches) use ($basePath, $imageDir) {
        $imgTag = $matches[0];
        // Convert relative URLs to absolute URLs
        if (preg_match('/src="([^"]+)"/i', $imgTag, $srcMatches)) {
            $src = $srcMatches[1];
            if (!preg_match('/^(http|https):\/\//i', $src)) {
                $src = rtrim($basePath, '/') . '/' . ltrim($src, '/');
            }
            // Download and resize the image
            $resizedSrc = resizeAndSaveImage($src, $imageDir);
            if ($resizedSrc !== false) {
                $imgTag = str_replace($srcMatches[1], $resizedSrc, $imgTag);
            }
        }
        return $imgTag;
    }, $htmlContent);

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

function resizeAndSaveImage($url, $imageDir) {
    $imageInfo = getimagesize($url);
    if ($imageInfo === false) {
        echo "Failed to get image info for URL: $url<br>";
        return false;
    }

    list($width, $height) = $imageInfo;
    $newWidth = 640;
    $newHeight = intval($height * ($newWidth / $width));

    $imageType = $imageInfo[2];
    $image = null;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($url);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($url);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($url);
            break;
        default:
            echo "Unsupported image type for URL: $url<br>";
            return false;
    }

    if ($image === null) {
        echo "Failed to create image from URL: $url<br>";
        return false;
    }

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Create a unique file name for the resized image
    $imageFileName = $imageDir . '/' . uniqid('img_', true) . image_type_to_extension($imageType);
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($resizedImage, $imageFileName, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($resizedImage, $imageFileName);
            break;
        case IMAGETYPE_GIF:
            imagegif($resizedImage, $imageFileName);
            break;
    }

    imagedestroy($image);
    imagedestroy($resizedImage);

    // Return the relative path to the resized image
    return basename($imageFileName);
}

// Function to check if a string is URL encoded
function isUrlEncoded($string) {
    $string = str_replace('%20', '+', $string);
    return urldecode($string) !== $string;
}

// Function to fetch HTML content using CURL
function fetchHtmlContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $htmlContent = curl_exec($ch);
    if(curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch) . "<br>";
    }
    curl_close($ch);
    return $htmlContent;
}

// Check if the URL parameter is set
if (isset($_GET['url'])) {
    $url = $_GET['url'];

    // Check if the URL is encoded and decode if necessary
    if (isUrlEncoded($url)) {
        $url = urldecode($url);
    }

    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

    // Create image directory
    $date = date('Y-m-d');
    $imageDir = '/var/www/html/retrobrowse/img_' . $date;
    if (!file_exists($imageDir)) {
        if (!mkdir($imageDir, 0777, true)) {
            die('Failed to create image directory');
        }
    }

    // Fetch the HTML content from the given URL using CURL
    $htmlContent = fetchHtmlContent($url);

    if ($htmlContent !== false) {
        // Get the base path of the URL to handle relative links
        $parsedUrl = parse_url($url);
        $basePath = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['path']) ? dirname($parsedUrl['path']) : '') . '/';

        // Convert the HTML content
        $convertedContent = convertHTML5toIE3($htmlContent, $baseUrl, $basePath, $imageDir);

        // Display the converted content
        header('Content-Type: text/html; charset=UTF-8');
        echo $convertedContent;
    } else {
        echo "Failed to fetch content from the URL.<br>";
    }
} else {
    echo "No URL parameter provided.<br>";
}
?>
