<?php
// @info: -> one line description of macro <-


/*
 * The code in the preamble (up to "$this->addMacro()") is executed once during the rendering process.
 * It's place to load libraries etc.
 * E.g.
    require_once SYSTEM_PATH.'gallery.class.php';
 *
 * For manipulating the current page, use:

	$this->page->addOverlay();
	$this->page->addOverride();
	$this->page->addPageSubstitution();
	$this->page->addMessage();
	$this->page->addDebugMsg();

	$this->page->addBodyEndInjections();
	$this->page->addCssFiles();
	$this->page->addCss();
	$this->page->addJsFiles();
	$this->page->addJs();
	$this->page->addJqFiles();
	$this->page->addJq();

	$this->page->addHead();
	$this->page->addKeywords();
	$this->page->addDescription();

    $this->addVariable();
*/

if (!$GLOBALS['globalParams']['legacyBrowser']) {
//    $this->page->addJsFiles("HAMMERJS");
    $this->page->addModules("HAMMERJS, HAMMERJQ, TOUCH_DETECTOR");
//    $this->page->addModules("HAMMERJS, HAMMERJQ, TOUCH_DETECTOR, SLIDESHOW_SUPPORT, JQUERY");
    if (getUrlArg('revealall')) {
        $this->addVariable('page_name_class', ' slideshow-support reveal-all ');
//        $this->trans->addVariable('page_name_class', ' slideshow-support reveal-all ');

    } else {
        $this->addVariable('page_name_class', ' slideshow-support ');
//        $this->trans->addVariable('page_name_class', ' slideshow-support ');
    }
}
$this->addVariable('comments', "\n</section>\n<section class='comments'>\n\n<h2>{{ SlideShow Comments }}</h2>\n");
//$this->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");
//$this->trans->addVariable('comments', "\n</section>\n<section class='comments'>\n\n<h2>{{ SlideShow Comments }}</h2>\n");
//$this->trans->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");

$nextLabel = $this->getVariable('nextPageLabel');
//$nextLabel = $this->trans->getVariable('nextPageLabel');
if (!$nextLabel) {
    $nextLabel = '&#8827;';
}
$prevLabel = $this->getVariable('prevPageLabel');
//$prevLabel = $this->trans->getVariable('prevPageLabel');
if (!$prevLabel) {
    $prevLabel = '&#8826;';
}

$str = <<<EOT
    <div class='nextPrevPageLinks'>
        <div class='prevPageLink'><a href='~/{$this->lzy->siteStructure->prevPage}'>$prevLabel</a></div>
        <div class='nextPageLink'><a href='~/{$this->lzy->siteStructure->nextPage}'>$nextLabel</a></div>
    </div>

EOT;
//$str = <<<EOT
//    <div class='nextPrevPageLinks'>
//        <div class='prevPageLink'><a href='~/{$this->siteStructure->prevPage}'>$prevLabel</a></div>
//        <div class='nextPageLink'><a href='~/{$this->siteStructure->nextPage}'>$nextLabel</a></div>
//    </div>
//
//EOT;
$this->page->addBodyEndInjections($str);


$this->page->addModules([
    '~sys/extensions/presentation-support/js/slideshow_support.js',
    '~sys/extensions/presentation-support/css/slideshow_support.css',
    '~sys/extensions/presentation-support/third-party/jsizes/jquery.sizes.js'
]);


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $this->optionAddNoComment = true;

	// typical variables that might be useful:
	$inx = $this->invocationCounter[$macroName] + 1;
//	$sys = $this->config->systemPath;

	// how to get access to macro arguments:
    $help = $this->getArg($macroName, 'help', 'Show help-text', false);


    // help:
    if ($help) {
        return <<<EOT
    <h2>Note:</h2>
    <p>See sample template in '_lizzy/extensions/presentation-support/config/page_template.html'</p>

EOT;
    }

    // only 1 invocation allowed:
    if ($inx > 1) {
        return '';
    }

    // save current url for other clients following this presentation (?flw):
    $url = $GLOBALS["globalParams"]["pageUrl"];
    $flwMode = getUrlArg('flw');
    if ($flwMode) {
        $this->page->addModules('~sys/extensions/presentation-support/js/follow.js');
        $this->page->addJs("var presBackend = '~sys/extensions/presentation-support/backend/_presentation-backend.php';");

    } else {
        file_put_contents(CACHE_PATH.'curr_slide.url', $url);
        if (getUrlArg('passive')) { // passive mode -> keys inactive:
            $this->page->addJs("var presentationPassive = true;\n" .
                "var presBackend = '~sys/extensions/presentation-support/backend/_presentation-backend.php';");

        } else {

            // dashboard mode:
            if (getUrlArgStatic('dashboard')) {
                //TODO check permission...
                $nextPageUrl = rtrim($GLOBALS["globalParams"]["host"], '/') . $GLOBALS["globalParams"]["appRoot"] . $this->lzy->siteStructure->nextPage;
                $preview = <<<EOT

    <div class='lzy-presentation-preview'>
        <iframe id="lzy-presentation-preview" src="$nextPageUrl?passive"></iframe>
    </div>

EOT;
                $this->page->addBodyClasses('lzy-presentation-dashboard');
                $this->addVariable('presentation-preview', $preview);
            }
        }
    }


    $str = "<!-- macro $macroName() loaded -->\n";
	return $str;
});
