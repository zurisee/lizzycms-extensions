<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	SlideShow Support
*/

if (!$GLOBALS['globalParams']['legacyBrowser']) {
    $this->page->addJsFiles("HAMMERJS");
    $this->page->addJqFiles(["HAMMERJQ", "TOUCH_DETECTOR", "SLIDESHOW_SUPPORT", "JQUERY"]);
    $this->page->addCssFiles('SLIDESHOW_SUPPORT_CSS');
    if (getUrlArg('revealall')) {
        $this->trans->addVariable('page_name_class', ' slideshow-support reveal-all ');

    } else {
        $this->trans->addVariable('page_name_class', ' slideshow-support ');
    }
}
$this->trans->addVariable('comments', "\n</section>\n<section class='comments'>\n\n<h2>{{ SlideShow Comments }}</h2>\n");
$this->trans->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");

$nextLabel = $this->trans->getVariable('nextPageLabel');
$prevLabel = $this->trans->getVariable('prevPageLabel');

$str = <<<EOT

    <div class='nextPrevPageLinks'>
        <div class='prevPageLink'><a href='~/{$this->siteStructure->prevPage}'>$prevLabel</a></div>
        <div class='nextPageLink'><a href='~/{$this->siteStructure->nextPage}'>$nextLabel</a></div>
    </div>

EOT;
$this->page->addBody_end_injections($str);

