<?php

class ContentEditor
{
    public function __construct($page)
    {
        $this->page = $page;
    }




    public function injectEditor($filePath)
      {
		$html = $this->page->get('content');

		$inx = 1;
		$p = strpos($html, '<section');

		if (preg_match("/data-filename='([^']*)'/", substr($html, $p), $m)) {
		    $filename = $m[1];
            $edSelector = PageSource::renderEditionSelector($filename);
        } else {
		    $edSelector = '';
        }

		if ($p !== false) {
			$p = strpos($html, '>', $p)+1;
		}
		while ($p !== false) {
			$html = substr($html, 0, $p)."\n\t\t\t<button class='btn_editor' title='{{ Edit Section }}'></button>\n<div id='editor_wrapper$inx' class='editor_wrapper'>\n".substr($html, $p);

			$p = strpos($html, '</section>', $p) - 1;
			$html = substr($html, 0, $p)."\n</div>\n\t\t\t</section>\n\t\t\t$edSelector".substr($html, $p+11);
			$p = strpos($html, '<section', $p);
			if ($p !== false) {
                if (preg_match("/data-filename='([^']*)'/", substr($html, $p), $m)) {
                    $filename = $m[1];
                    $edSelector = PageSource::renderEditionSelector($filename);
                } else {
                    $edSelector = '';
                }
                $p = strpos($html, '>', $p)+1;
            }
			$inx++;
		}

		// hideWhileEditing -> handled in page.class.php: bodyEndInjections()

		$this->page->addJqFiles("~sys/js/editor.js");
		$this->page->addCssFiles(["~sys/css/editor.css","~sys/third-party/font-awesome/css/font-awesome.min.css","~sys/third-party/simplemde/simplemde.min.css"]);
		$this->page->addJsFiles("~sys/third-party/simplemde/simplemde.min.js");
		$buttons = <<<EOT
			<button class="btn_save" title='{{ Save }}'><img src='~sys/rsc/save.png' alt='{{ Save }}' /></button>
			<button class="btn_done" title='{{ Done }}'><img src='~sys/rsc/done.png' alt='{{ Done }}' /></button>
			<button class="btn_cancel" title='{{ Cancel }}'><img src='~sys/rsc/cancel.png' alt='{{ Cancel }}' /></button>

EOT;

		$html .= <<<EOT

	<div id='editing-html' style='display:none;'>
		<div class="edit_btns edit_btns1">
$buttons
		</div>
		<textarea class="editor">@data</textarea>
		<div class="edit_btns2 edit_btns2">
$buttons
		</div>
	</div>

EOT;

        $html .= $this->injectUploader($filePath);
		$this->page->addContent($html, true);
    } // injectEditor




    private function injectUploader($filePath)
    {
		require_once SYSTEM_PATH.'file_upload.class.php';

        $_SESSION['lizzy'][$filePath]['uploadPath'] = $this->page->config->pagesPath.$filePath;
		$uploader = new FileUpload($this->page);
        return $uploader->render($filePath);
    } // injectUploader


} // ContentEditor

