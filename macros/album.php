<?php

// @info: Presents images in a folder as an album.

$page->addCssFiles("~sys/third-party/unite_gallery/css/unite-gallery.css");
$page->addJqFiles(["JQUERY", "~sys/third-party/unite_gallery/js/unitegallery.min.js", "~sys/third-party/unite_gallery/themes/tiles/ug-theme-tiles.js"]);

$jq = <<<EOT
    $('.gallery').each(function() {
        $(this).unitegallery({ tiles_type:'justified', tile_enable_textpanel:true, tile_textpanel_title_text_align: 'center' });
    });
    
EOT;

$page->addJq($jq);

require_once SYSTEM_PATH.'gallery.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $galleryPath = $this->getArg($macroName, 'galleryPath', 'Path to folder where images reside.', '');
    if ($galleryPath == 'help') {
        return '';
    }
    $galleryPath = makePathDefaultToPage($galleryPath);

    $fullsizeImagePath = $this->getArg($macroName, 'fullsizeImagePath', '(optional) If you want to make images in full size accessible, add this path.', '');
    $imagePath = $this->getArg($macroName, 'imagePath', '', '');
    $thumbsPath = $this->getArg($macroName, 'thumbsPath', '', '');
    $thumbsSize = $this->getArg($macroName, 'thumbsSize', '', '');


    $thumbsPath = ($thumbsPath) ? correctPath($thumbsPath) : 'thumbs/';
	$thumbsSize = ($thumbsSize) ? correctPath($thumbsSize) : '512x384';
	
	if ($fullsizeImagePath) {
		$imagePath = ($imagePath) ? correctPath($imagePath) : 'images/';
		$options = [
			'sourcePath' 	=> correctPath($fullsizeImagePath),
			'imageMaxSize' 	=> '1600x1200',
			'imagePath' 	=> $imagePath,
			'thumbnailPath'	=> $thumbsPath,
			'thumbnailSize'	=> $thumbsSize,
		];
	} else {
		$options = [
			'sourcePath' 	=> '',
			'imageMaxSize' 	=> false,
			'imagePath' 	=> $imagePath,
			'thumbnailPath'	=> $thumbsPath,
			'thumbnailSize'	=> $thumbsSize,
		];
	}
	$id = 'gallery'.$inx;
	$album = new ImageGallery($galleryPath, $id, $options);
	$str = $album->render();

	return $str;
});
