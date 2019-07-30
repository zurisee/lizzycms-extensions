<?php

// @info: Renders a link.

require_once SYSTEM_PATH.'link.class.php';


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $href = $this->getArg($macroName, 'href', 'Path or URL to link target', '');
    $this->getArg($macroName, 'text', 'Link-text. If missing, "href" will be displayed instead.', '');
    $this->getArg($macroName, 'type', '[intern, extern or mail, pdf, sms, tel, geo, slack] "mail" renders link as "mailto:", "intern" suppresses automatic prepending of "https://", "extern" causes link target to be opened in new window.', '');
    $this->getArg($macroName, 'class', 'Class to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'title', 'Title attribute to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'target', 'Target attribute to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'subject', 'In case of "mail": subject to be preset.', '');
    $this->getArg($macroName, 'body', 'In case of "mail": mail body to be preset.', '');

    if ($href == 'help') {
        return '';
    }



    $args = $this->getArgsArray($macroName, false, ['href','text','type','class','title','target','subject','body']);


    $cl = new CreateLink( $this->lzy );

    if (isset($args['href'])) {
        $args['href'] = resolvePath($args['href'], true, true);
//        $args['href'] = resolvePath($args['href'], true, false, true);
    }

    $str = $cl->render($args);

    $this->optionAddNoComment = true;
	return $str;
});
