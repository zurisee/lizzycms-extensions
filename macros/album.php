<?php

/*
*	Unite Gallery
*	http://unitegallery.net/index.php?page=tiles-justified-various#default
*
*	Usage:
*	gallery($pathToGalleryFolder)						-> images in $pathToGalleryFolder, assumed in usable size
*	gallery($pathToGalleryFolder, $fullsizeImagePath)	-> large images in $fullsizeImagePath -> need to be resized
*
*/

$page->addCssFiles("~sys/third-party/unite_gallery/css/unite-gallery.css");
$page->addJqFiles(["JQUERY", "~sys/third-party/unite_gallery/js/unitegallery.min.js", "~sys/third-party/unite_gallery/themes/tiles/ug-theme-tiles.js"]);
$page->addJq('    $(".gallery").unitegallery({ tiles_type:"justified", tile_enable_textpanel:true, tile_textpanel_title_text_align: "center" });');

require_once SYSTEM_PATH.'gallery.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $galleryPath = $this->getArg($macroName, 'galleryPath', '', '');
    $fullsizeImagePath = $this->getArg($macroName, 'fullsizeImagePath', '', '');
    $imagePath = $this->getArg($macroName, 'imagePath', '', '');
    $thumbsPath = $this->getArg($macroName, 'thumbsPath', '', '');
    $thumbsSize = $this->getArg($macroName, 'thumbsSize', '', '');

//    $galleryPath = resolvePath($galleryPath, true);
    $galleryPath = '~page/'.$galleryPath;
	
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
//	$str = $album->render(basename($galleryPath), 'gallery'.$inx);

	return $str;
});
