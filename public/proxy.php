<?php
/**
 * Proxy.php takes in image URLs, fetches them, and resizes
 */
class ImageProxy {
    public $url, $originalUrl, $optionString;
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
        $this->originalUrl      = $url;

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

        // if no schema is specified, it can then look in certain specified
        // domains in "translate-domains.ini" and swap them for matching domains
        if(is_null($scheme)) {
            if($opts = parse_ini_file(dirname(dirname(__FILE__)) . '/translate-domains.ini', true)) {
                $this->_getFromAlternateDomain($opts);
            }
            $scheme = parse_url($this->url, PHP_URL_SCHEME);
        }

        switch($scheme) {
            case 'http': case 'https': break;
            default:
                throw new Exception('Schema not allowed');
        }
    }

    /**
     * Get from alternate domain looks in alternate domains and translates them into proper ones
     * 
     * If it can't find the resource at the alternate domain, it then looks in S3 / uploads to S3 as necessary
     * 
     * @param array $opts - should be of the format:
     *      [shortdomain] => 'https://example.com/actual/full/path/
     *      [shortdomain.s3] => [ key => 'AWSKEY', secret => 'AWSSECRET', bucket => 'AWSBUCKET' ] 
     */
    protected function _getFromAlternateDomain(array $opts) {
        $parts = explode('/', $this->url);
        if(isset($opts[$parts[0]])) {
            $this->url = $opts[$parts[0]] . implode('/', array_slice($parts, 1));
            try {
                $s3ArrayKey = $parts[0] . '.s3';
                if (isset($opts[$s3ArrayKey])) {
                    // we're gonna need some S3
                    include dirname(dirname(__FILE__)) . '/aws.phar';
                    $client = \Aws\S3\S3Client::factory(array(
                        'credentials' => array(
                            'key' => $opts[$s3ArrayKey]['key'],
                            'secret' => $opts[$s3ArrayKey]['secret'],
                        ),
                        'region' => 'eu-west-1'
                    ));

                    // check for 404
                    if ($this->_is404($this->url)) {
                        $signedUrl = $client->getObjectUrl(
                            $opts[$s3ArrayKey]['bucket'],
                            $this->originalUrl,
                            '+2 minutes');
                        $this->url = $signedUrl;

                    } else {
                        $this->_copyToTmp();
                        // send to S3
                        $uploader = \Aws\S3\Model\MultipartUpload\UploadBuilder::newInstance()
                            ->setClient($client)
                            ->setSource($this->tmpFile)
                            ->setKey($opts[$s3ArrayKey]['key'])
                            ->setBucket($opts[$s3ArrayKey]['bucket'])
                            ->setKey($this->originalUrl)
                            ->build();
                        $uploader->upload();
                    }
                }
            } catch(Exception $e) {
                echo 'Could not upload';
                die;
            }
        }
    }

    
    /** 
     * Checks to see if a URL 404's 
     */
    protected function _is404($url) {
        try {
            $handle = curl_init($url);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($handle, CURLOPT_NOBODY, true);

            /* Get the HTML or whatever is linked in $url. */
            $response = curl_exec($handle);

            /* Check for 404 (file not found). */
            $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if ($httpCode == 404) {
                return true;
            }

            curl_close($handle);
            return false;
        } catch(Exception $e) {
            var_dump($e);die;
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
        $newFileName = str_replace(array('/', ':'), '', $this->optionString . $this->originalUrl);
        $this->phpThumb->RenderToFile(dirname(__FILE__) . '/' . $newFileName);
        $this->phpThumb->OutputThumbnail();
        unlink($this->tmpFile);
    }
}

// set include for phpThumb
require_once(dirname(dirname(__FILE__)) . '/phpthumb.class.php');

// get whole request, minus the opening slash
$request = substr($_SERVER['REQUEST_URI'], 1);

// first component should be the size
$options = substr($request, 0, strpos($request, '/'));

$url = substr($request, strpos($request, '/') + 1);

$proxy = new ImageProxy();
$proxy->serve($url, $options);
