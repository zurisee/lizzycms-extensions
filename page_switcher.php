<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Page Switcher: Links and Keyboard Events
*/

if (!$GLOBALS['globalParams']['legacyBrowser']) {
    $this->page->addJsFiles("HAMMERJS");
    $this->page->addJqFiles(["HAMMERJQ", "TOUCH_DETECTOR", "PAGE_SWITCHER", "JQUERY"]);
}

$nextLabel = $this->trans->getVariable('nextPageLabel');
$prevLabel = $this->trans->getVariable('prevPageLabel');

$str = <<<EOT

  <div class='nextPrevPageLinks'>
	<div class='prevPageLink'><a href='~/{$this->siteStructure->prevPage}'>$prevLabel</a></div>
	<div class='nextPageLink'><a href='~/{$this->siteStructure->nextPage}'>$nextLabel</a></div>
  </div>

EOT;
$this->page->addBody_end_injections($str);
