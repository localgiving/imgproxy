<?php
/**
 * Proxy.php takes in image URLs, fetches them, and resizes
 */
class ImageProxy {
    public $url, $optionString;
    protected $tmpFile, $phpThumb;

    /**
     * Serves the image
     *
     * Options can be sent in a comma seperated string, without slashes or bars. For example
     *
     * @param url The URL of the image. (http(s) only)
     * @param options The options of the image, pixels, and other options.
     */
    public function serve($url, $options) {
        $this->url              = $url;
        $this->optionString     = $options;

        // list through functions
        $this->_checkSchema();
        $this->_copyToTmp();
        $this->_setOptions();
        $this->_createFile();
    }

    /**
     * Checks it's http(s) only, and not /etc/passwd, etc
     */
    protected function _checkSchema() {
        $scheme = parse_url($this->url, PHP_URL_SCHEME);
        switch($scheme) {
            case 'http': case 'https': break;
            default:
                throw new Exception('Schema not allowed');
        }
    }

    /**
     * Copies the file at the URL to specified directory
     */
    protected function _copyToTmp() {
        $tempDir    = sys_get_temp_dir();
        $this->tmpFile   = tempnam($tempDir, 'imgproxy');
        copy($this->url, $this->tmpFile);
    }

    /**
     * Parses all the options from the "options" string
     */
    protected function _setOptions() {
        // phpthumb uses some deprecated stuff, so I've disabled the errors
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->phpThumb = new phpThumb();

        $this->phpThumb->setSourceFilename($this->tmpFile);

        $this->phpThumb->setParameter('w', intval($this->optionString));
        $this->phpThumb->setParameter('f', 'png'); //Ensure transparent backgrounds

        // if options has a comma in it, add those as options, not
        // just the width
        if($comma = strstr($this->optionString, ',')) {
            $optionsExploded = explode(',', substr($comma, 1));
            foreach($optionsExploded as $exploded) {
                $postEq = str_replace('-', '|', substr(strstr($exploded, '='), 1));
                $option = strstr($exploded, '=', true);
                if($this->_checkAllowedOption($option)) {
                    $this->phpThumb->setParameter($option, $postEq);
                }
            }
        }
        $this->phpThumb->setParameter('config_allow_src_above_docroot', true);
    }

    protected function _checkAllowedOption($option) {
        return in_array($option, [
            'w',    // width
            'h',    // height
            'wp',   // max width for portrait images
            'hp',   // max height for portrait images
            'wl',   // max width for landscape images
            'hl',   // max height for landspace images
            'ws',   // max width for square images
            'hs',   // max height for square images
            'f',    // output format - jpeg, gif, png
            'q',    // jpeg compression - 1 - 95 (1 is worst)
            'sx',   // left side of source rectangle (0-1 are %ages)
            'sy',   // top side of source rectangle (0-1 are %ages)
            'zc',   // zoom crop
            'bg',   // bg hex
            'bc',   // border colour hex
            'fltr', // filter
            'ra',   // rotate by angle
            'ar',   // auto rotate camera pics
            'iar',  // ignore aspect ratio
            'far',  // force aspect ratio
            'dpi',  // dots per inch
            'maxb'  // maximum number of bytes
        ]);
    }

    /**
     * Creates the file on the local file system, and then serves the content of it.
     */
    protected function _createFile() {
        $this->phpThumb->GenerateThumbnail();
        $newFileName = str_replace(array('/', ':'), '', $this->optionString . $this->url);
        $this->phpThumb->RenderToFile(dirname(__FILE__) . '/' . $newFileName);
        $this->phpThumb->OutputThumbnail();
        unlink($this->tmpFile);
    }
}

// set include for phpThumb
require_once('/var/www/phpthumb.class.php');

// get whole request, minus the opening slash
$request = substr($_SERVER['REQUEST_URI'], 1);

// first component should be the size
$options = substr($request, 0, strpos($request, '/'));

$url = substr($request, strpos($request, '/') + 1);

$proxy = new ImageProxy();
$proxy->serve($url, $options);
