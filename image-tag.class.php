<?php

// renders <img tag, handling alt, srcset, late-loading, quickview etc. as well as loading of required resourcs


class ImageTag
{

    public function __construct($obj, $args) {
        list($feature_image_default_max_width) = parseDimString($obj->config->feature_ImgDefaultMaxDim, $args['src']);
        $this->feature_SrcsetDefaultStepSize = $obj->config->feature_SrcsetDefaultStepSize;

        $this->feature_image_default_max_width = $feature_image_default_max_width;
        $this->feature_ImgDefaultMaxDim = $obj->config->feature_ImgDefaultMaxDim;
        $this->quickviewEnabled = $obj->config->feature_quickview;

        foreach ($args as $key => $value) {
            $this->$key = $value;
        }

        $this->srcFilename = null;
        $this->path = null;
        $this->basename = null;
        $this->ext = null;
        $this->width = null;
        $this->height = null;
        $this->aspRatio = null;
        $this->imgFullsizeFile = null;
        $this->imgFullsizeWidth = null;
        $this->imgFullsizeHeight = null;
        $this->w0 = $this->h0 = 1;
        $this->fileSize = null;
    } // __construct



    public function render($id) {

        // load late-loading code if not done yet:
        if ($this->lateImgLoading && (!isset($this->lateImgLoadingCodeLoaded))) {
            $this->lateImgLoadingCodeLoaded = true;
        }

        // prepare working copy of image in '_/':
        $this->prepareImageWorkingCopy();

        // figure out src, srcFile and imgFullsizeFile
        $this->determineFilePaths();

        $qvDataAttr = $this->renderQuickview();

        $this->prepareLateLoading();


        // prepare srcset:
        $srcset = $this->renderSrcset();

        if ($class = $this->class) {
            $class = trim("$id $class");
        }

        $attr = " width='{$this->w}' height='{$this->h}'";

        $genericAttibs = $this->imgTagAttributes ? ' '.$this->imgTagAttributes : '';

        // basic img code:
        $str = "<img id='$id' class='$class' {$this->lateImgLoadingPrefix}src='{$this->src}'{$srcset} title='{$this->alt}' alt='{$this->alt}'$genericAttibs $qvDataAttr />";

        return $str;
    } // render




    private function prepareImageWorkingCopy()
    {
        $src = $this->src;
        $origSrc = preg_replace('/( \[ [^\] ]* \] )/x', '', $src);
        $origSrc = resolvePath($origSrc, true);
        $fullsizeImg = dirname($origSrc). '/_/'.basename($origSrc);
        if (!file_exists($fullsizeImg) && file_exists($origSrc)) {
            $resizer = new ImageResizer($this->feature_ImgDefaultMaxDim);
            $resizer->resizeImage($origSrc, $fullsizeImg);
            $this->fullsizeImg = $fullsizeImg;
        }

        if (file_exists($fullsizeImg)) {
            list($this->imgFullsizeWidth, $this->imgFullsizeHeight) = getimagesize($fullsizeImg);
            $this->aspRatio = $this->imgFullsizeHeight / $this->imgFullsizeWidth;

        } elseif (file_exists($origSrc)) {  // rare case: orig img has been deleted but working copy still there
            list($this->width, $this->height) = getimagesize($origSrc);
            $this->aspRatio = $this->height / $this->width;
        }

    } // prepareImageWorkingCopy



    private function renderQuickview()
    {
        $this->qvDataAttr = '';
        if (($this->quickviewEnabled && !preg_match('/\blzy-noquickview\b/', $this->class)) // config setting, but no 'lzy-noquickview' override
            || preg_match('/\blzy-quickview\b/', $this->class)) {                    // or 'lzy-quickview' class

            if ($this->imgFullsizeFile && file_exists($this->imgFullsizeFile)) {
                list($w0, $h0) = getimagesize($this->imgFullsizeFile);
                $this->fileSize = filesize($this->imgFullsizeFile);
                $this->imgFullsizeWidth = $w0;
                $this->imgFullsizeHeight = $h0;
                $this->qvDataAttr = " data-qv-src='~/{$this->imgFullsizeFile}' data-qv-width='$w0' data-qv-height='$h0'";
            } else {
                $this->imgFullsizeFile = false;
            }
        }
        return $this->qvDataAttr;
    } // renderQuickview



    private function prepareLateLoading()
    {
        $this->lateImgLoadingPrefix = '';
        if ($this->lateImgLoading) {
            $this->lateImgLoadingPrefix = 'data-';
            if (strpos($this->class, 'lzy-late-loading') === false) {
                $this->class .= ' lzy-late-loading';
            }
        }
    } // prepareLateLoading



    private function renderSrcset()
    {
        $this->srcset = ($this->srcset === null) ? true : $this->srcset;
        if (!$this->fileSize || !$this->imgFullsizeWidth) {
            if (file_exists($this->imgFullsizeFile)) {
                $this->fileSize = filesize($this->imgFullsizeFile);
                if (!$this->imgFullsizeWidth) {
                    list($this->imgFullsizeWidth, $this->imgFullsizeHeight) = getimagesize($this->imgFullsizeFile);
                }

            } else if (file_exists($this->src)) {
                $this->fileSize = filesize($this->src);
                list($this->imgFullsizeWidth, $this->imgFullsizeHeight) = getimagesize($this->src);

            } else if (file_exists($this->srcFile)) {
                $this->fileSize = filesize($this->srcFile);
                list($this->imgFullsizeWidth, $this->imgFullsizeHeight) = getimagesize($this->srcFile);

            } else {
                $this->fileSize = 0;
            }
        }

        if ($this->srcset && ($this->fileSize > 50000)) {   // activate only if source file is largen than 50kb
            $w1 = ($this->w) ? $this->w : 300;
            $h1 = ($this->h) ? $this->h : round(300 * $this->aspRatio);
            $this->srcset = '';
            while (($w1 < $this->imgFullsizeWidth) && ($w1 < $this->feature_image_default_max_width)) {
                $f = $this->basename . "[{$w1}x{$h1}]" . $this->ext;
                $this->srcset .= "$this->path$f {$w1}w, ";
                $w1 += $this->feature_SrcsetDefaultStepSize;
                $h1 = round($w1 * $this->aspRatio);
            }
            $this->srcset = " {$this->lateImgLoadingPrefix}srcset='" . substr($this->srcset, 0, -2) . "'";
            $this->srcset .= ($this->w) ? " sizes='{$this->w}px'" : '';

        } elseif (is_string($this->srcset)) {
            $this->srcset = " {$this->lateImgLoadingPrefix}srcset='{$this->srcset}'";

        } else {
            $this->srcset = '';
        }
        return $this->srcset;
    } // renderSrcset



    private function determineFilePaths()
    {
        $this->src = makePathRelativeToPage($this->src, true);
        $this->srcFile = resolvePath($this->src, true);
        list($this->src, $this->path, $this->basename, $this->ext, $this->w, $this->h, $dimFound) = parseFileName($this->src, $this->aspRatio);
        $this->w = ($dimFound) ? $this->w : null;
        $this->h = ($dimFound) ? $this->h : null;

        $this->imgFullsizeFile = resolvePath($this->path) . $this->basename . $this->ext;
    } // determineFilePaths

} // class ImagePrep
