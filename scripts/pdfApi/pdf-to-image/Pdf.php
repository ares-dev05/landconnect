<?php

namespace Spatie\PdfToImage;

use Imagick;
use Spatie\PdfToImage\Exceptions\InvalidFormat;
use Spatie\PdfToImage\Exceptions\PdfDoesNotExist;
use Spatie\PdfToImage\Exceptions\InvalidLayerMethod;

class Pdf
{
    protected $pdfFile;

    protected $resolution = 144;

    protected $outputFormat = 'jpg';

    protected $page = 1;

    protected $imagick;

    protected $numberOfPages;

    protected $validOutputFormats = ['jpg', 'jpeg', 'png'];

    protected $layerMethod = Imagick::LAYERMETHOD_FLATTEN;

    protected $colorspace;

    protected $compressionQuality;

    /**
     * @param string $pdfFile The path or url to the pdffile.
     *
     * @throws \Spatie\PdfToImage\Exceptions\PdfDoesNotExist
     */
    public function __construct($pdfFile)
    {
        if (! filter_var($pdfFile, FILTER_VALIDATE_URL) && ! file_exists($pdfFile)) {
            throw new PdfDoesNotExist();
        }

        $this->pdfFile = $pdfFile;

        /*
        $this->imagick = new Imagick($pdfFile);
        $this->numberOfPages = $this->imagick->getNumberImages();
        */
    }

    /**
     * Set the raster resolution.
     *
     * @param int $resolution
     *
     * @return $this
     */
    public function setResolution($resolution)
    {
        $this->resolution = $resolution;

        return $this;
    }

    /**
     * Set the output format.
     *
     * @param string $outputFormat
     *
     * @return $this
     *
     * @throws \Spatie\PdfToImage\Exceptions\InvalidFormat
     */
    public function setOutputFormat($outputFormat)
    {
        if (! $this->isValidOutputFormat($outputFormat)) {
            throw new InvalidFormat("Format {$outputFormat} is not supported");
        }

        $this->outputFormat = $outputFormat;

        return $this;
    }

    /**
     * Sets the layer method for Imagick::mergeImageLayers()
     * If int, should correspond to a predefined LAYERMETHOD constant.
     * If null, Imagick::mergeImageLayers() will not be called.
     *
     * @param int|null
     *
     * @return $this
     *
     * @throws \Spatie\PdfToImage\Exceptions\InvalidLayerMethod
     *
     * @see https://secure.php.net/manual/en/imagick.constants.php
     * @see Pdf::getImageData()
     */
    public function setLayerMethod($layerMethod)
    {
        if (
            is_int($layerMethod) === false &&
            is_null($layerMethod) === false
        ) {
            throw new InvalidLayerMethod('LayerMethod must be an integer or null');
        }

        $this->layerMethod = $layerMethod;

        return $this;
    }

    /**
     * Determine if the given format is a valid output format.
     *
     * @param $outputFormat
     *
     * @return bool
     */
    public function isValidOutputFormat($outputFormat)
    {
        return in_array($outputFormat, $this->validOutputFormats);
    }

    /**
     * Set the page number that should be rendered.
     *
     * @param int $page
     *
     * @return $this
     *
     * @throws \Spatie\PdfToImage\Exceptions\PageDoesNotExist
     */
    public function setPage($page)
    {
        /*
        if ($page > $this->getNumberOfPages()) {
            throw new PageDoesNotExist("Page {$page} does not exist");
        }
        */

        $this->page = $page;

        return $this;
    }

    /**
     * Get the number of pages in the pdf file.
     *
     * @return int
     */
    public function getNumberOfPages()
    {
        return $this->numberOfPages;
    }

    /**
     * Save the image to the given path.
     *
     * @param string $pathToImage
     *
     * @return bool
     */
    public function saveImage($pathToImage, $num=0)
    {
        $imageData = $this->getImageData($pathToImage);
        if (!$imageData)
            return false;

        return file_put_contents($pathToImage, $imageData) !== false;
    }

    /**
     * Save the file as images to the given directory.
     *
     * @param string $directory
     * @param string $prefix
     *
     * @return array $files the paths to the created images
     */
    public function saveAllPagesAsImages($directory, $prefix = '')
    {
        $pages = [];
        // try to read up to 50 pages (should we make it 30??)
        for ($pageNumber=1;$pageNumber<=50;++$pageNumber) {
            $this->setPage($pageNumber);

            $destination = "{$directory}/{$prefix}{$pageNumber}.{$this->outputFormat}";

            try {
                if ($this->saveImage($destination, $pageNumber)) {
                    $pages [] = $destination;
                } else {
                    break;
                }
            }   catch (\Exception $e) {
                break;
            }
        }

        return $pages;
    }

    /**
     * Return raw image data.
     *
     * @param string $pathToImage
     *
     * @return \Imagick
     */
    public function getImageData($pathToImage)
    {
        /*
         * Reinitialize imagick because the target resolution must be set
         * before reading the actual image.
         */
        $this->imagick = new Imagick();

        $this->imagick->setResolution($this->resolution, $this->resolution);

        if ($this->colorspace !== null) {
            $this->imagick->setColorspace($this->colorspace);
        }

        if ($this->colorspace !== null) {
            $this->imagick->setCompressionQuality($this->compressionQuality);
        }

        try {
            if ( !$this->imagick->readImage(sprintf('%s[%s]', $this->pdfFile, $this->page - 1)) ) {
                return null;
            }
        }   catch (\Exception $e) {
            return null;
        }

        // we need to merge all the PDF layers so the output looks correct
        if (is_int($this->layerMethod)) {
            $this->imagick = $this->imagick->mergeImageLayers($this->layerMethod);
        }

        $this->imagick->setFormat("jpg");

        return $this->imagick;
    }

    /**
     * @param int $colorspace
     *
     * @return $this
     */
    public function setColorspace($colorspace)
    {
        $this->colorspace = $colorspace;

        return $this;
    }

    /**
     * @param int $compressionQuality
     *
     * @return $this
     */
    public function setCompressionQuality($compressionQuality)
    {
        $this->compressionQuality = $compressionQuality;

        return $this;
    }
}
