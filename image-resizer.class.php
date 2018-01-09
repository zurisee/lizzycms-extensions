<?php

define('IMAGE_TYPES' , '(jpg|jpeg|png|gif|tif|tiff)');

class ImageResizer
{    
    //----------------------------------------------------------------
    function deriveImageSize($filename)
    {
        $path_parts = pathinfo($filename);
        $fname = $path_parts['filename'];
        $crop = false;
        if (strpos($fname, '!')) {
            $crop = true;
            $fname = str_replace('!', '', $fname);
        }
        
        if (!preg_match('/([^\[]*)\[(\d*)x(\d*)\]$/', $fname, $m)) {
            if (file_exists($filename) && list($w, $h) = getimagesize($filename)) {
                return [$filename, $w, $h, false ];
            } else {
                return [$filename, 0, 0, false ];
            }
        }
        $fname = $m[1];
        $width = $m[2];
        $height = $m[3];
        
        if (!$crop) {
            if (!$width) {
                $width = 3000;
            }
            if (!$height) {
                $height = 2400;
            }
        }
        
        $ext = $path_parts['extension'];
        $fpath = $path_parts['dirname'].'/';
        
        $orig = "$fpath$fname.$ext";
        return [$orig, $width, $height, $crop ];
    } // deriveImageSize
    
    //----------------------------------------------------------------
    function resizeImage($src, $dst, $width, $height, $crop = false)
    {
        
        if(!file_exists($src) || !list($w, $h) = getimagesize($src)) {
            return "Unsupported picture type!";
        }
        $dstPath = dirname($dst);
        if (!file_exists($dstPath)) {
            mkdir($dstPath, 0755, true);
        }
        $type = strtolower(substr(strrchr($src,"."),1));
        if($type == 'jpeg') $type = 'jpg';
        switch($type){
            case 'bmp': $img = imagecreatefromwbmp($src); break;
            case 'gif': $img = imagecreatefromgif($src); break;
            case 'jpg': $img = imagecreatefromjpeg($src); break;
            case 'png': $img = imagecreatefrompng($src); break;
            default : return "Unsupported picture type!";
        }
        
        // resize
        if($crop){
            if ($w < $width and $h < $height) {
               copy($src, $dst);
               return;
            }
            $ratio = max($width/$w, $height/$h);
            $h = $height / $ratio;
            $x = ($w - $width / $ratio) / 2;
            $w = $width / $ratio;
        } else {
            if ($w < $width and $h < $height) {
               copy($src, $dst);
               return;
            }
            $ratio = min($width/$w, $height/$h);
            $width = $w * $ratio;
            $height = $h * $ratio;
            $x = 0;
        }
        
        $new = imagecreatetruecolor($width, $height);
        
        // preserve transparency
        if ($type == "gif" or $type == "png") {
            imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }
        
        imagecopyresampled($new, $img, 0, 0, $x, 0, $width, $height, $w, $h);
        
        $ok = false;
        switch($type){
            case 'bmp': imagewbmp($new, $dst); break;
            case 'gif': imagegif($new, $dst); break;
            case 'jpg':
                $ok = imageinterlace($new, true);
                imagejpeg($new, $dst);
                break;
            case 'png': imagepng($new, $dst); break;
        }
        imagedestroy($new);
        mylog("Image created: $dst ".($ok?'interlaced':'non interlaced'));
        return true;
    } // resizeImage
    
    //--------------------------------------------------
    public function provideImages($html)
    {
        $p = strpos($html, '<img');
        while ($p !== false) {
            $p1 = strpos($html, 'src=', $p);
            $s = trim(substr($html, $p1+4));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $src = resolvePath($m[1]);
                
                if (!file_exists($src)) {
                    list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                    $this->resizeImage($orig, $src, $width, $height, $crop);
                }
            }

            $p1 = strpos($html, 'srcset=', $p);
            $s = trim(substr($html, $p1+7));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $srcset = explode(',', $m[1]);
                foreach ($srcset as $src) {
                    $src = trim(preg_replace('/(\.'.IMAGE_TYPES.').*$/i', "$1", $src));
                    $src = resolvePath($src);
                    if (!file_exists($src)) {
                        list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                        $this->resizeImage($orig, $src, $width, $height, $crop);
                    }
                }
            }

            $p = strpos($html, '<img', $p+1);
        }

        $p = strpos($html, '<source');
        while ($p !== false) {
            $p1 = strpos($html, 'srcset=', $p);
            $s = trim(substr($html, $p1+7));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $src = resolvePath($m[1]);

                if (!file_exists($src)) {
                    list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                    $this->resizeImage($orig, $src, $width, $height, $crop);
                }
            }
            $p = strpos($html, '<source', $p+1);
        }
    } // provideImages
    
//    //--------------------------------------------------
//    public function provideThumbnails()
//    {
//    	global $globalParams;
//
//        $files = getDir($globalParams['pathToPage'].'*');
//        $thumbPath = $globalParams['pathToPage'].'thumbnail/';
//        foreach ($files as $file) {
//            if (preg_match('/\.(jpg|gif|png|bmp)$/i', $file)) {
//                if (!file_exists(($th = $thumbPath.basename($file)))) {
//                    mylog("creating thumbnail for: $file");
//                    $this->resizeImage($file, $th, 80, 60);
//                }
//            }
//        }
//    } // provideThumbnails

} // class ImageResizer

