<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $href = $this->getArg($macroName, 'href', 'Destination address.<br>If it starts with \'mailto:\', it will be rendered as a mail-link (same as type:mail)', '');
    $text = $this->getArg($macroName, 'text', '(optional) The text to be shown for the link.', '');
    $type = $this->getArg($macroName, 'type', '(optional) [mail | extern] modifies the way the link is rendered.', '');
    $class = $this->getArg($macroName, 'class', '(optional) Class applied to the &lt;a> tag', '');
    $target = $this->getArg($macroName, 'target', '(optional) Target attribute applied to the &lt;a> tag (permits to control in which browser window the page is opened.)', '');
    $subject = $this->getArg($macroName, 'subject', '(optional &ndash; only for mail) A subject line to be preset when opening e-mail.', '');    // only for email
    $body = $this->getArg($macroName, 'body', '(optional &ndash; only for mail) A text body to be preset when opening e-mail.<br>Use <samp>&#92;n</samp> to insert line-breaks.', '');          // only for email

	$title = '';
	$hiddenText = '';
	if ((stripos($href, 'mailto:') !== false) || (stripos($type, 'mail') !== false)) {
		$class = ($class) ?  "$class mail_link" : 'mail_link';
		$title = " title='{{ opens mail app }}'";
		$arg = '';
        $body = str_replace(' ', '%20', $body);
        $body = str_replace(['\n', "\n"], '%0A', $body);
		if ($subject) {
		    $subject = str_replace(' ', '%20', $subject);
		    $arg = "?subject=$subject";
		    if ($body) {
		        $arg .= "&body=$body";
            }
        } elseif ($body) {
		    $arg = "?body=$body";
        }
		if (!$text) {
			$text = substr($href, 7);
		} else {
			$hiddenText = "<span class='print_only'> [$href]</span>";
		}
		$href .= $arg;

	} else {
	    $href0 = $href;
        $href = resolvePath($href, false, 'https');
        if (!$text) {
            $rec = $this->siteStructure->findSiteElem($href0, true);
            if ($rec) {
                $href = resolvePath('~/'.$rec['folder'], false, 'https');
                $text = $rec['name'];
            } else {
                $text = $href;
            }
        } else {
            $hiddenText = "<span class='print_only'> [$href]</span>";
        }

        if ($target) {
            $target = " target='$target'";

        } elseif (stripos($type, 'extern') !== false) {
            $target = " target='_blank'";
            $class = ($class) ? "$class external_link" : 'external_link';
            $title = " title='{{ opens in new win }}'";
        }
    }
	$class = ($class) ? " class='$class'" : '';
	$str = "<a href='$href' $class$title$target>$text$hiddenText</a>";
	return $str;
});
