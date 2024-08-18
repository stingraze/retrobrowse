<?php  
/*  
 * retrobrowse version 0.11.3a - for Ubuntu 20.04 & Windows Environment 
 * This version is intended for devices that don't have browsers with BASIC AUTH IMPLEMENTED.
 * (C)Tsubasa Kato - 2024/7/17 22:21PM JST - Created with help from ChatGPT (GPT-4o) & C:Amie 
 * Last updated on 2024/8/18 18:37PM  
 * Note: Not yet tested by Tsubasa Kato himself on Windowss environment 
 * Enable error reporting for debugging - Off in this. 
 * On Ubuntu envrionment, please make sure you use .htaccess file too. 
 */  
  
    ini_set('display_errors', 1);  
    ini_set('display_startup_errors', 1);  
    error_reporting(E_ALL);  
  
    const IMG_ROOT_DIR		= '/var/www/html/retrobrowse'; 
    #Change above to below if in Windows Environment (Haven't tested yet):  
    #const IMG_ROOT_DIR		= 'C:\inetpub\wwwroot'; 
    const CURL_DEBUG		= false;  
    #Below is made to false to disable BASIC AUTH
    const REQUIRE_BASIC_AUTH	= false;  
    const BASIC_AUTH_USER	= 'myuser';  
    const BASIC_AUTH_PASSWORD	= 'password-here-please';
    #Below is made to true to enable normal form type authentication.
    const REQUIRE_NORMAL_AUTH = true;

// Define constants for the username and password

if(REQUIRE_NORMAL_AUTH == true) {
    define('USERNAME', 'user');
define('PASSWORD', '8-18-2024passall');

// Start a session to manage login state

session_start();

// Check if the user is already logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Check if the form has been submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate the submitted username and password
        if (isset($_POST['username'], $_POST['password']) && 
            $_POST['username'] === USERNAME && 
            $_POST['password'] === PASSWORD) {
            // Set the session variable to indicate the user is logged in
            $_SESSION['logged_in'] = true;
            // Redirect to the same page to avoid resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            // Incorrect username or password message
            $error = 'Incorrect username or password!';
        }
    }
    
    // Display the login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
    </head>
    <body>
        <h1>Please enter your credentials to continue</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
            <br>
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
            <br>
            <button type="submit">Submit</button>
        </form>
    </body>
    </html>

<?php
    exit; // Stop further execution until the user is logged in
    }
}


// Place your existing protected content here
// Example: echo "Welcome to the protected area!";

  
function challengeAuthentication(): void {  
    if (REQUIRE_BASIC_AUTH) {  
        /* test for username/password */  
        if (!( 
                isset($_SERVER['PHP_AUTH_USER']) &&  
                $_SERVER['PHP_AUTH_USER'] == BASIC_AUTH_USER &&  
                isset($_SERVER['PHP_AUTH_PW']) &&  
                $_SERVER['PHP_AUTH_PW'] == BASIC_AUTH_PASSWORD 
             ))  
        {  
            // Send headers to cause a browser to request  
            // username and password from user  
            header("WWW-Authenticate: Basic realm=\"retrobrowse\"");  
            header("HTTP/1.0 401 Unauthorized");  
      
            // Show failure text, which browsers usually  
            // show only after several failed attempts  
            print("Access denied.<br>\n");  
            exit(0);  
        }  
    }  
} 
 
  
function convertHTML5toIE3($htmlContent, $baseUrl, $domainRootPath, $basePath, $imageDir, $webImageDir) {  
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
    $htmlContent = preg_replace_callback('/<img[^>]+>/i', function($matches) use ($basePath, &$imgTags, $domainRootPath) { 
        $imgTag = $matches[0];  
 
        if (preg_match('/src=["\']([^"\']+)["\']/i', $imgTag, $srcMatches)) { 
            $src = $srcMatches[1]; 
 
            if (substr($src, 0, 1) === '/') {				// The input URI is a Root Relative URL e.g. '/images/logo.jpg' 
                $src = $domainRootPath . $src; 
            } else if (!preg_match('/^(http|https):\/\//i', $src)) { 	// The input URI is a Path Relative URL e.g. 'images/logo.jpg' 
                $src = rtrim($basePath, '/') . '/' . ltrim($src, '/'); 	// Make an Absolute URL on the parent domain e.g. 'https://www.domain.com/images/logo.jpg' 
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
    $htmlContent = preg_replace_callback('/<a[^>]+href=["\']([^["\']+)["\'][^>]*>/i', function($matches) use ($baseUrl, $basePath, $domainRootPath) { 
        $url = $matches[1];  
 
        // Convert relative URLs to absolute URLs 
        if (substr($url, 0, 1) === '/') {				// The input URI is a Root Relative URL e.g. '/myfolder/index.html' 
                $url = $domainRootPath . $url; 
            } else if (!preg_match('/^(http|https):\/\//i', $url)) {  	// The input URI is a Path Relative URL e.g. 'myfolder/index.html' 
            $url = rtrim($basePath, '/') . '/' . ltrim($url, '/');  	// Make an Absolute URL on the parent domain e.g. 'https://www.domain.com/myfolder/index.html' 
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  
        curl_multi_add_handle($mh, $ch);  
        if (CURL_DEBUG) {  
            $streamVerboseHandle = fopen('php://temp', 'w+');  
            curl_setopt($ch, CURLOPT_STDERR, $streamVerboseHandle);  
        }  
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
        // Check for a 200 status code on the request, if it 404's everything breaks  
 
        if ((curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) AND ($imageContent !== false)) { 
            $newSrc = resizeAndConvertImageContent($imageContent, $imageDir, $webImageDir);  
            if ($newSrc !== false) {  
                $convertedImages[$originalSrc] = $newSrc;  
            } 
        }  
        curl_multi_remove_handle($mh, $ch);  
        curl_close($ch);  
    }  
  
    if (CURL_DEBUG) {  
        rewind($streamVerboseHandle);  
        $verboseLog = stream_get_contents($streamVerboseHandle);  
        echo "cUrl verbose information:\n",   
             "<pre>", htmlspecialchars($verboseLog), "</pre>\n";  
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
        $newWidth = floor($width * 0.2);  
        $newHeight = floor($height * 0.2);  
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  
        curl_multi_add_handle($mh, $ch);  
        if (CURL_DEBUG) {  
            $streamVerboseHandle = fopen('php://temp', 'w+');  
            curl_setopt($ch, CURLOPT_STDERR, $streamVerboseHandle);  
        }  
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
  
    if (CURL_DEBUG) {  
        rewind($streamVerboseHandle);  
        $verboseLog = stream_get_contents($streamVerboseHandle);  
        echo "cUrl verbose information:\n",   
             "<pre>", htmlspecialchars($verboseLog), "</pre>\n";  
    }  
  
    curl_multi_close($mh);  
    return $htmlContents;  
}  
  
function cleanImageFolders($rootDir) {  
    $d = dir($rootDir);  
    while (false !== ($entry = $d->read())) {  
        if (is_dir($entry) && ($entry != '.') && ($entry != '..')) {  
            if (substr($entry, 0, 4) === 'img_') {    // Prefix matches img_  
                $datePart = substr($entry, 4);  // get the date part of the folder name  
                $time = strtotime($datePart);   // Convert it to EPOC  
                // If the folder date is not the current date, delete it  
                if ($time !== strtotime(date('Y-m-d',time()))) {  
                    deleteDir($rootDir . '/' . $entry);  
                }  
            }  
        }  
    }  
    $d->close();  
}  
  
function deleteDir(string $dirPath): void {  
    if (! is_dir($dirPath)) {  
        throw new InvalidArgumentException("$dirPath must be a directory");  
    }  
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {  
        $dirPath .= '/';  
    }  
    $files = glob($dirPath . '*', GLOB_MARK);  
    foreach ($files as $file) {  
        if (is_dir($file)) {  
            deleteDir($file);  
        } else {  
            unlink($file);  
        }  
    }  
    rmdir($dirPath);  
}  
  
// Test for configured authenticated access and prompt if necessary  
challengeAuthentication();  
  
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
  
    // Clean images directory  
    cleanImageFolders(IMG_ROOT_DIR);  
  
    // Create image directory  
    $date = date('Y-m-d');  
    $imageDir = IMG_ROOT_DIR . '/img_' . $date;  
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
            $domainRootPath = $parsedUrl['scheme'] . '://' . $parsedUrl['host']; 
            $basePath = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['path']) ? str_replace("\\", "/", dirname($parsedUrl['path'])) : ''); 
            if (substr($basePath, -1) !== '/') { 
               $basePath = $basePath . '/'; 
            } 
 
            // Convert the HTML content  
            $convertedContent = convertHTML5toIE3($htmlContent, $baseUrl, $domainRootPath, $basePath, $imageDir, $webImageDir);  
  
            // Display the converted content  
            header('Content-Type: text/html; charset=UTF-8');  
            echo $convertedContent;  
        } else {  
            echo "Failed to fetch content from the URL.<br>";  
        }  
    }  
} else {  
?> 
    <form name="retrobrowse" method="GET" action="index.php"> 
        <input type="text" name="url" value="" maxlength="255" size="40" placeholder="https://..."> <input type="submit" value="Go"> 
        <br><small><font face="arial">Enter a https:// web address to browse</font></small> 
    </form> 
<?php 
}  
?>  
