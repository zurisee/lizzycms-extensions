<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	SlideShow Support
*/

if (!$GLOBALS['globalParams']['legacyBrowser']) {
//    $this->page->addJsFiles("HAMMERJS");
    $this->page->addModules("HAMMERJS, HAMMERJQ, TOUCH_DETECTOR, SLIDESHOW_SUPPORT, JQUERY");
    if (getUrlArg('revealall')) {
        $this->trans->addVariable('page_name_class', ' slideshow-support reveal-all ');

    } else {
        $this->trans->addVariable('page_name_class', ' slideshow-support ');
    }
}
$this->trans->addVariable('comments', "\n</section>\n<section class='comments'>\n\n<h2>{{ SlideShow Comments }}</h2>\n");
$this->trans->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");

$nextLabel = $this->trans->getVariable('nextPageLabel');
if (!$nextLabel) {
    $nextLabel = '&#8827;';
}
$prevLabel = $this->trans->getVariable('prevPageLabel');
if (!$prevLabel) {
    $prevLabel = '&#8826;';
}

$str = <<<EOT
    <div class='nextPrevPageLinks'>
        <div class='prevPageLink'><a href='~/{$this->siteStructure->prevPage}'>$prevLabel</a></div>
        <div class='nextPageLink'><a href='~/{$this->siteStructure->nextPage}'>$nextLabel</a></div>
    </div>

EOT;
$this->page->addBodyEndInjections($str);

