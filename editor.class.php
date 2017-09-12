<?php

class ContentEditor
{
    public function __construct($page)
    {
        $this->page = $page;
//        $this->injectEditor();
    }

    public function injectEditor()
    {
		$html = $this->page->get('content');
		
		$inx = 1;
		$p = strpos($html, '<section');
		if ($p !== false) {
			$p = strpos($html, '>', $p)+1;
		}
		while ($p !== false) {
			$html = substr($html, 0, $p)."\n\t\t\t<button class='btn_editor'></button>\n<div id='editor_wrapper$inx' class='editor_wrapper'>\n".substr($html, $p);

			$p = strpos($html, '</section>', $p) - 1;
			$html = substr($html, 0, $p)."\n</div>\n\t\t\t".substr($html, $p);
			$p = strpos($html, '<section', $p);
			if ($p !== false) {
				$p = strpos($html, '>', $p)+1;
			}
			$inx++;
		}
		
		$this->page->addJqFiles("~sys/js/editor.js");
		$this->page->addCssFiles(["~sys/css/editor.css","~sys/third-party/font-awesome/css/font-awesome.min.css","~sys/third-party/simplemde/simplemde.min.css"]);
		$this->page->addJsFiles("~sys/third-party/simplemde/simplemde.min.js");
		$html .= <<<EOT

	<div id='editing-html' style='display:none;'>
		<div class="edit_btns">
			<button id="btn_save" title='{{ Save }}'><img src='~sys/rsc/save.png' alt='{{ Save }}' /></button>
			<button id="btn_done" title='{{ Done }}'><img src='~sys/rsc/done.png' alt='{{ Done }}' /></button>
			<button id="btn_cancel" title='{{ Cancel }}'><img src='~sys/rsc/cancel.png' alt='{{ Cancel }}' /></button>
		</div>
		<textarea class="editor">@data</textarea>
	</div>

EOT;

        $html .= $this->injectUploader();
		$this->page->addContent($html, true);
    } // injectEditor

    private function injectUploader()
    {
		require_once SYSTEM_PATH.'file_upload.class.php';
		
		$uploader = new FileUpload($this->page);
        return $uploader->render();        
    } // injectUploader

} // ContentEditor

