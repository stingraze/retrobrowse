<?php
//(C)Tsubasa Kato 7/16/2024 - Made with ChatGPT (GPT-4o)
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

    // Downsize images to 640 pixels in width
    $htmlContent = preg_replace_callback('/<img[^>]+>/i', function($matches) {
        $imgTag = $matches[0];
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
        $newUrl = $baseUrl . '?url=' . urlencode($url);
        return str_replace($matches[1], $newUrl, $matches[0]);
    }, $htmlContent);

    // Return the converted HTML content
    return $htmlContent;
}

// Check if the URL parameter is set
if (isset($_GET['url'])) {
    $url = $_GET['url'];
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

    // Fetch the HTML content from the given URL
    $htmlContent = file_get_contents($url);

    if ($htmlContent !== false) {
        // Get the base path of the URL to handle relative links
        $basePath = preg_replace('/\/[^\/]*$/', '/', $url);

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
