<?php

function initPresentation( $trans )
{
    if (!$GLOBALS['lizzy']['legacyBrowser']) {
        $trans->page->addModules("HAMMERJS, HAMMERJQ, TOUCH_DETECTOR");
        if (getUrlArg('revealall')) {
            $trans->page->addBodyClasses(' lzy-slideshow-support reveal-all ');

        } else {
            $trans->page->addBodyClasses(' lzy-slideshow-support ');
        }
    }
    $trans->addVariable('comments', "\n</section>\n<section class='comments'>\n\n<h2>{{ SlideShow Comments }}</h2>\n");
    //$trans->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");
    //$trans->trans->addVariable('speaker-notes', "\n</section>\n<section class='speaker-notes'>\n");

    $nextLabel = $trans->getVariable('nextPageLabel');
    if (!$nextLabel) {
        $nextLabel = '〉';
    }
    $prevLabel = $trans->getVariable('prevPageLabel');
    if (!$prevLabel) {
        $prevLabel = '〈';
    }

    $str = <<<EOT
    <div class='lzy-next-prev-page-links'>
        <div class='lzy-prev-page-link'><a href='~/{$trans->lzy->siteStructure->prevPage}'>$prevLabel</a></div>
        <div class='lzy-next-page-link'><a href='~/{$trans->lzy->siteStructure->nextPage}'>$nextLabel</a></div>
    </div>

EOT;
    $trans->page->addBodyEndInjections($str);

    $path = '~/' . $trans->config->systemPath . 'extensions/presentationsupport/';
    $trans->page->addModules([
        "{$path}js/presentation_support.js",
        "{$path}css/presentation_support.css",
        "{$path}third-party/jsizes/jquery.sizes.js"
    ]);
} // initPresentation



$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $this->optionAddNoComment = true;

    $fresh = getUrlArg('fresh');

	$inx = $this->invocationCounter[$macroName] + 1;

    $init = $this->getArg($macroName, 'init', 'If false, only initializes presentation-support when url-arg "?present" is set. Default: true', true);
    $help = $this->getArg($macroName, 'help', 'Show help-text', false);


    // help:
    if ($help) {
        return <<<EOT
    <h2>Note:</h2>
    <p>See sample template in '_lizzy/extensions/presentationsupport/config/page_template.html'</p>

EOT;
    }

    // only 1 invocation allowed:
    if ($inx > 1) {
        return '';
    }

    if ($init || getUrlArg('present')) {
        initPresentation( $this );
    } else {
        return '';
    }

    // save current url for other clients following this presentation (?flw):
    $url = $GLOBALS['lizzy']['pageUrl'];
    $flwMode = getUrlArg('flw');
    if ($flwMode) {
        $this->page->addModules('~sys/extensions/presentationsupport/js/follow.js');
        $this->page->addJs("var presBackend = '~sys/extensions/presentationsupport/backend/_presentation-backend.php';");

    } elseif (isAdmin()) {
        // Follow feature unfinished:
        // $this->page->addJs("var presBackend = '~sys/extensions/presentationsupport/backend/_presentation-backend.php';");
        file_put_contents(CACHE_PATH.'curr_slide.url', $url);
        if (getUrlArg('passive')) { // passive mode -> keys inactive:
            $this->page->addJs('var presentationPassive = true;');

        } else {

            // dashboard mode:
            if (getUrlArgStatic('dashboard') && isAdmin()) {
                $nextPageUrl = rtrim($GLOBALS['lizzy']['host'], '/') . $GLOBALS['lizzy']['appRoot'] . $this->lzy->siteStructure->nextPage;
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

    $this->page->addBodyEndInjections("\n\t<div id='lzy-cursor-mark' style='display: none;'></div>\n");


    $str = "<!-- macro $macroName() loaded -->\n";

    $lzySlideElem = getPostData('lzySlideElem');
    if ($lzySlideElem) {
        $str .= "\t\t<div id='lzy-slide-elem' class='lzy-dispno'>$lzySlideElem</div>\n";
    }
	return $str;
});
