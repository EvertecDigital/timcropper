<?php

/**
 * TIMCROPPPER - Dynamic Image Resizer and Cache Manager
 *
 * This script handles resizing and caching of images based on provided width and height parameters.
 * It supports JPG, PNG, and WebP output formats based on configuration and availability of GD extensions.
 *
 * Requirements:
 * - PHP GD extension must be enabled.
 * - PHP GD WebP extension is required for WebP format output.
 * - External image references are blocked for security reasons.
 *
 * Configuration:
 * - You can customize default values by creating a 'timcropper-config.php' file in the same directory.
 * - Constants available for customization:
 *   - FOLDER_DEFAULT: Default folder name for storing modified image files.
 *   - QUALITY: Image quality level (for JPG and WebP).
 *   - COMPRESSOR: Compression level (for PNG).
 *   - WIDTH: Default width when not specified.
 *   - AUTO_CLEAN: Enable automatic cleaning of cache folder.
 *   - AUTO_CLEAN_DAYS: Interval in days for automatic cache folder cleaning.
 *   - OUTPUT_FORMAT: Default output format ('jpg', 'png', 'webp').
 *
 * Usage:
 * - Pass 'src' (image path), 'w' (width), and optionally 'h' (height) as GET parameters to resize the image.
 *
 * Example URL:
 * http://example.com/timcropper.php?src=path/to/image.jpg&w=400&h=300
 * 
 * @author Everson Aguiar - www.evertecdigital.com.br
 * @version 1.0
 * @license MIT License
 */
// Check if GD extension is loaded
if (!extension_loaded('gd')) :
    echo 'GD extension disabled';
    exit();
endif;

// Check if mime_content_type is loaded
if (!function_exists('mime_content_type')) :
    exit("mime_content_type function is not available");
endif;

// Load configuration if available
if (file_exists(__DIR__ . '/timcropper-config.php')) :
    require_once __DIR__ . '/timcropper-config.php';
endif;

$CONFIG = [
    'FOLDER_DEFAULT' => 'cache', // Default folder name for storing modified files
    'QUALITY' => 75, // Quality level
    'COMPRESSOR' => 7, // Compression level
    'WIDTH' => 1200, // Default width when not specified
    'MIN_WIDTH' => 50, // Default min width
    'MIN_HEIGHT' => 50, // Default min height
    'MAX_WIDTH' => 2560, // Default max width
    'MAX_HEIGHT' => 2000, // Default max height
    'AUTO_CLEAN' => TRUE, // Enable automatic cleaning of cache folder
    'AUTO_CLEAN_DAYS' => 30, // Interval in days to clean cache folder
    'OUTPUT_FORMAT' => 'webp' // Output format: 'auto' (default), 'jpg', 'png', 'webp'
];

// Adjust output format based on GD extensions
if (!in_array($CONFIG['OUTPUT_FORMAT'], ['jpg', 'png', 'webp']) || ($CONFIG['OUTPUT_FORMAT'] == 'webp' && (!function_exists('imagewebp')))) :
    $CONFIG['OUTPUT_FORMAT'] = 'jpg';
endif;

// Define configuration constants if not already defined
foreach ($CONFIG as $confKey => $confValue) :
    if (!defined($confKey)) :
        define($confKey, $confValue);
    endif;
endforeach;


$clear = filter_input(INPUT_GET, 'clear', FILTER_SANITIZE_URL);
if (!empty($clear)) :
    $timcropper = new timcropper();
    $timcropper->forceClear();
    exit();
endif;

// Retrieve image source path from GET parameter
$src = filter_input(INPUT_GET, 'src', FILTER_SANITIZE_URL);

// Check if source image path is valid
if (empty($src)) :
    exit('Image not found or invalid parameter.');
endif;

// Block external image references for security reasons
$parsed_url = parse_url($src);
$host = filter_var($_SERVER['HTTP_HOST'], FILTER_DEFAULT);

if (isset($parsed_url['host']) && $parsed_url['host'] !== $host) :
    exit('External image references are blocked.');
endif;

// Retrieve width and height parameters, with defaults from configuration
$w = filter_input(INPUT_GET, 'w', FILTER_VALIDATE_FLOAT, ['options' => ['default' => WIDTH, 'min_range' => MIN_WIDTH]]);
$h = filter_input(INPUT_GET, 'h', FILTER_VALIDATE_FLOAT, ['options' => ['default' => null, 'min_range' => MIN_HEIGHT]]);

// Initialize the timcropper class for image processing
$timcropper = new timcropper();
$timcropper->make($src, $w, $h);

/**
 * Class timcropper
 *
 * Handles image resizing and caching operations.
 */
class timcropper
{

    private $cachePath;
    private $imagePath;
    private $imageName;
    private $imageMime;
    private $quality;
    private $compressor;
    private $outputFormat;
    private $imageFile;
    private $imageWidth;
    private $imageHeight;
    private static $allowedExt = ['image/jpeg', 'image/jpg', "image/png", "image/webp"];

    /**
     * timcropper constructor.
     *
     * @param string $cachePath Path to cache folder.
     * @param int $quality Image quality level.
     * @param int $compressor Compression level.
     * @param string $outputFormat Output format ('jpg', 'png', 'webp').
     * @throws Exception If cache folder creation fails.
     */
    public function __construct(string $cachePath = FOLDER_DEFAULT, int $quality = QUALITY, int $compressor = COMPRESSOR, string $outputFormat = OUTPUT_FORMAT)
    {
        $this->cachePath = $cachePath;
        $this->quality = $quality;
        $this->compressor = $compressor;
        $this->outputFormat = $outputFormat;

        // Create cache folder if it doesn't exist
        if (!file_exists($this->cachePath) || !is_dir($this->cachePath)) :
            if (!mkdir($this->cachePath, 0755, true)) :
                throw new Exception("Could not create cache folder");
            endif;
        endif;

        // Create index.php file in cache folder for security
        if (!file_exists($this->cachePath . "/index.php")) :
            file_put_contents($this->cachePath . "/index.php", '<?php header("Location: ../");');
        endif;
    }

    /**
     * Process image resizing and output to browser.
     *
     * @param string $imagePath Path to the source image.
     * @param int $width Desired width of the output image.
     * @param int|null $height Desired height of the output image (optional).
     * @return bool|string True on success, error message on failure.
     */
    public function make(string $imagePath, int $width, int $height = null)
    {

        // Check if source image exists
        if (!file_exists($imagePath)) :
            return false;
        endif;

        $this->imagePath = $imagePath;

        // Determine MIME type of the source image
        $this->imageMime = mime_content_type($this->imagePath);

        // Validate MIME type against allowed types
        if (!in_array($this->imageMime, self::$allowedExt)) :
            return "Not a valid JPG or PNG image";
        endif;

        $this->imageWidth = round($width > MAX_WIDTH ? MAX_WIDTH : $width);
        $this->imageHeight = (!empty($height) ? round($height > MAX_HEIGHT ? MAX_HEIGHT : $height) : null);

        // Generate a unique name for the cached image based on image properties
        $this->imageName = $this->setName();

        // Generate the cached image file
        $this->imageFile = $this->image();

        if (!file_exists($this->imageFile)) :
            return "Failed to create image";
        endif;

        // Output the cached image to the browser
        ob_clean();
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // Expires in one week
        header('Content-Type: ' . $this->imageMime);
        header('Content-Length: ' . filesize($this->imageFile));
        header('Cache-Control: public, max-age=604800'); // Cache for one week
        header("Pragma: public"); // Pragma must be public to allow caching

        $content = file_get_contents($this->imageFile);
        if ($content !== false) :
            echo $content;
            return true;
        endif;

        // Cleanup on failure
        $this->imageDestroy($this->imageFile);

        return true;
    }

    /**
     * Generate a unique name for the cached image based on image properties.
     *
     * @return string Unique image name.
     */
    private function image()
    {
        $imageExt = "{$this->cachePath}/{$this->imageName}." . pathinfo($this->imagePath)['extension'];

        if (file_exists($imageExt) && is_file($imageExt)) :
            return $imageExt;
        endif;

        return $this->imageCache();
    }

    /**
     * Generate a unique name for the cached image based on image properties.
     *
     * @return string Unique image name.
     */
    private function setName()
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($this->imagePath, PATHINFO_FILENAME))
            . (!empty($this->imageWidth) ? "-{$this->imageWidth}" : '')
            . (!empty($this->imageHeight) ? "x{$this->imageHeight}" : '')
            . "-"
            . md5_file($this->imagePath);
    }

    /**
     * Generate and cache the resized image based on specified dimensions and output format.
     *
     * @return string Path to the cached image file.
     */
    private function imageCache()
    {
        $this->flush();

        // Get original image dimensions
        list($src_w, $src_h) = getimagesize($this->imagePath);

        if (empty($this->imageHeight)) {
            $this->imageHeight = ($this->imageWidth * $src_h) / $src_w;
        }

        // Calculate cropping and resizing parameters
        $src_x = 0;
        $src_y = 0;

        $cmp_x = $src_w / $this->imageWidth;
        $cmp_y = $src_h / $this->imageHeight;

        // Adjust cropping based on aspect ratio
        if ($cmp_x > $cmp_y) {
            $src_x = round(($src_w - ($src_w / $cmp_x * $cmp_y)) / 2);
            $src_w = round($src_w / $cmp_x * $cmp_y);
        } elseif ($cmp_y > $cmp_x) {
            $src_y = round(($src_h - ($src_h / $cmp_y * $cmp_x)) / 2);
            $src_h = round($src_h / $cmp_y * $cmp_x);
        }

        // Choose output format based on configuration or image type
        switch ($this->outputFormat) {
            case 'jpg':
                return $this->fromJpg((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
            case 'png':
                return $this->fromPng((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
            case 'webp':
                return $this->fromWebp((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
            case 'auto':
            default:

                // Automatically determine output format based on source image type
                if ($this->imageMime == 'image/png') {
                    return $this->fromPng((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
                } elseif ($this->imageMime == 'image/webp') {
                    return $this->fromWebp((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
                } else {
                    return $this->fromJpg((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
                }
        }
    }

    /**
     * Clean up older cached images in the folder based on the current image's base name.
     */
    private function flush()
    {
        $searchFileSubstr = substr($this->imageName, 0, strrpos($this->imageName, '-'));

        // Check for and delete older cached images related to the current image
        $autoclean = false;
        foreach (scandir($this->cachePath) as $file) :
            // Ignore '.' and '..' entries
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fileName = substr($file, 0, strrpos($file, '.'));
            if (strstr($file, $searchFileSubstr) && $fileName !== $this->imageName) :
                $autoclean = true;
                $this->imageDestroy("{$this->cachePath}/{$file}");
            endif;
        endforeach;

        // Perform automatic cleaning of the cache folder if enabled
        if ($autoclean) :
            $this->autoclean();
        endif;
    }

    /**
     * Delete a specified image file.
     *
     * @param string $imagePatch Path to the image file to delete.
     */
    private function imageDestroy(string $imagePatch)
    {
        if (file_exists($imagePatch) && is_file($imagePatch)) :
            unlink($imagePatch);
        endif;
    }

    /**
     * Perform automatic cleaning of the cache folder based on configured settings.
     */
    private function autoclean()
    {
        // Exit if automatic cleaning is disabled
        if (!AUTO_CLEAN) :
            return;
        endif;

        $autocleanFile = $this->cachePath . '/autoclean.txt';
        $now = time();

        // Create or update autoclean timestamp file
        if (!file_exists($autocleanFile)) :
            file_put_contents($autocleanFile, $now);
            return;
        endif;


        $lastCleanTime = intval(file_get_contents($autocleanFile));

        // Perform cleaning if the specified time interval has passed
        if (($now - $lastCleanTime) >= (AUTO_CLEAN_DAYS * 86400)) :

            // Remove all files in the cache folder
            foreach (glob("{$this->cachePath}/*") as $file) :
                if (is_file($file)) :
                    unlink($file);
                endif;
            endforeach;

            // Update autoclean timestamp
            file_put_contents($autocleanFile, $now);
        endif;
    }

    public function forceClear()
    {
        // Remove all files in the cache folder
        foreach (glob("{$this->cachePath}/*") as $file) :
            if (is_file($file)) :
                unlink($file);
            endif;
        endforeach;

        // Update autoclean timestamp
        file_put_contents($this->cachePath . '/autoclean.txt', time());

        echo 'clearned!';
    }

    /**
     * Generate a resized JPEG image and store it in the cache folder.
     *
     * @param int $src_x X-coordinate of the source image to start copying from.
     * @param int $src_y Y-coordinate of the source image to start copying from.
     * @param int $src_w Width of the source image to copy.
     * @param int $src_h Height of the source image to copy.
     * @return string Path to the cached JPEG image file.
     * @throws Exception If the image type is not supported.
     */
    private function fromJpg(int $src_x, int $src_y, int $src_w, int $src_h)
    {
        // Create an empty image with the specified dimensions
        $thumb = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        // Load the source image based on MIME type
        switch ($this->imageMime) {
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($this->imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($this->imagePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($this->imagePath);
                break;
            default:
                throw new Exception('Unsupported image type.');
        }

        // Configure for preserving transparency of PNG images
        if ($this->imageMime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        // Resize and copy the specified part of the original image to the empty image
        imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $this->imageWidth, $this->imageHeight, $src_w, $src_h);

        // Save the image with the specified quality and configuration
        switch ($this->outputFormat) {
            case 'jpg':
                imagejpeg($thumb, "{$this->cachePath}/{$this->imageName}.jpg", $this->quality);
                break;
            case 'png':
                imagepng($thumb, "{$this->cachePath}/{$this->imageName}.png", $this->compressor);
                break;
            case 'webp':
                imagewebp($thumb, "{$this->cachePath}/{$this->imageName}.webp", $this->quality);
                break;
            default:
                throw new Exception('Unsupported output format.');
        }

        // Free memory used by created images
        imagedestroy($thumb);
        imagedestroy($source);

        // Return the full path of the generated file
        return "{$this->cachePath}/{$this->imageName}.{$this->outputFormat}";
    }

    /**
     * Generate a resized PNG image and store it in the cache folder.
     *
     * @param int $src_x X-coordinate of the source image to start copying from.
     * @param int $src_y Y-coordinate of the source image to start copying from.
     * @param int $src_w Width of the source image to copy.
     * @param int $src_h Height of the source image to copy.
     * @return string Path to the cached PNG image file.
     * @throws Exception If the image type is not supported.
     */
    private function fromPng(int $src_x, int $src_y, int $src_w, int $src_h)
    {
        // Create an empty image with the specified dimensions
        $thumb = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        // Load the source image based on MIME type
        switch ($this->imageMime) {
            case 'image/png':
                $source = imagecreatefrompng($this->imagePath);
                break;
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($this->imagePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($this->imagePath);
                break;
            default:
                throw new Exception('Unsupported image type.');
        }

        // Configure for preserving transparency of PNG images
        if ($this->imageMime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        // Resize and copy the specified part of the original image to the empty image
        imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $this->imageWidth, $this->imageHeight, $src_w, $src_h);

        // Save the image as WebP with the specified quality
        switch ($this->outputFormat) {
            case 'jpg':
                imagejpeg($thumb, "{$this->cachePath}/{$this->imageName}.jpg", $this->quality);
                break;
            case 'png':
                imagepng($thumb, "{$this->cachePath}/{$this->imageName}.png", $this->compressor);
                break;
            case 'webp':
                imagewebp($thumb, "{$this->cachePath}/{$this->imageName}.webp", $this->quality);
                break;
            default:
                throw new Exception('Unsupported output format.');
        }

        // Free memory used by created images
        imagedestroy($thumb);
        imagedestroy($source);

        // Return the full path of the generated WebP file
        return "{$this->cachePath}/{$this->imageName}.{$this->outputFormat}";
    }

    /**
     * Creates a WebP image from the source image.
     *
     * @param int $src_x The x-coordinate of source point.
     * @param int $src_y The y-coordinate of source point.
     * @param int $src_w The source width.
     * @param int $src_h The source height.
     * @return string The path to the created WebP image.
     */
    private function fromWebp(int $src_x, int $src_y, int $src_w, int $src_h)
    {
        // Create an empty image with the specified dimensions
        $thumb = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        // Load the source image based on MIME type
        switch ($this->imageMime) {
            case 'image/png':
                $source = imagecreatefrompng($this->imagePath);
                break;
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($this->imagePath);
                break;
            default:
                throw new Exception('Unsupported image type for conversion to WebP.');
        }

        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $this->imageWidth, $this->imageHeight, $src_w, $src_h);

        imagewebp($thumb, "{$this->cachePath}/{$this->imageName}.webp", $this->quality);

        // Free memory used by created images
        imagedestroy($thumb);
        imagedestroy($source);

        // Return the full path of the generated WebP file
        return "{$this->cachePath}/{$this->imageName}.webp";
    }
}
