<?php
//Created by Tsubasa Kato - Inspire Search Corporation on 7/17/2024 10:31AM JST using ChatGPT GPT-4o
function convertHTML5toIE3($htmlContent, $baseUrl, $basePath) {
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

    // Downsize images to 640 pixels in width and convert relative URLs to absolute URLs
    $htmlContent = preg_replace_callback('/<img[^>]+>/i', function($matches) use ($basePath) {
        $imgTag = $matches[0];
        // Convert relative URLs to absolute URLs
        if (preg_match('/src="([^"]+)"/i', $imgTag, $srcMatches)) {
            $src = $srcMatches[1];
            if (!preg_match('/^(http|https):\/\//i', $src)) {
                $src = rtrim($basePath, '/') . '/' . ltrim($src, '/');
            }
            $imgTag = str_replace($srcMatches[1], $src, $imgTag);
        }
        // Remove existing width and height attributes
        $imgTag = preg_replace('/(width|height)="[^"]*"/i', '', $imgTag);
        // Add the width attribute
        return preg_replace('/<img/i', '<img width="640"', $imgTag);
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

    // Fetch the HTML content from the given URL using CURL
    $htmlContent = fetchHtmlContent($url);

    if ($htmlContent !== false) {
        // Get the base path of the URL to handle relative links
        $parsedUrl = parse_url($url);
        $basePath = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . dirname($parsedUrl['path']) . '/';

        // Convert the HTML content
        $convertedContent = convertHTML5toIE3($htmlContent, $baseUrl, $basePath);

        // Display the converted content
        header('Content-Type: text/html; charset=UTF-8');
        echo $convertedContent;
    } else {
        echo "Failed to fetch content from the URL.";
    }
} else {
    echo "No URL parameter provided.";
}
?>
