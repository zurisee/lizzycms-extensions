<?php
/*
 **	Macro() Plug-in 
 ** displays a visible note in the shape of a "Post-it"sticker
*/

$page->addHead("\t<link href='https://fonts.googleapis.com/css?family=Kalam' rel='stylesheet'>\n");

$str =  <<<EOT

			$('.post-it').draggable();
			$('.post-it .close_icon').click(function() {
				$(this).parent().parent().hide();
			});

EOT;
$page->addJQ($str);

$page->addCssFiles(['JQUERYUI_CSS', '~sys/css/post-it.css']);
$page->addJqFiles(['JQUERY', 'JQUERYUI', 'JQUERYUI_TOUCH']);

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = '~/'.$this->config->systemPath;

    $text = $this->getArg($macroName, 'text', '', '');
    $right = $this->getArg($macroName, 'right', '', false);
    $top = $this->getArg($macroName, 'top', '', false);
    $angle = $this->getArg($macroName, 'angle', '', '');
    $color = $this->getArg($macroName, 'color', '', '');

    $style = '';
	$style_content = '';
	if ($top) {
		if (preg_match('/^[-\d]*$/', $top)) {
			$top .= 'px';
		}
		$style .= "top:$top;";
	}
	if ($right) {
		if (preg_match('/^(\-?)(\d+)(\w*)/', $right, $m)) {
			if ($m[1] == '-') {
				$edge = 'left';
			} else {
				$edge = 'right';				
			}
			$val = $m[2];
			if ($m[3]) {
				$val .= $m[3];
			} else {
				$val .= 'px';
			}
		}
		$style .= "$edge:$val;";
	}
	if ($angle) {
		if (preg_match('/^[-\d]*$/', $angle)) {
			$angle .= 'deg';
		}
		$style .= "-webkit-transform: rotate($angle);-ms-transform: rotate($angle);transform: rotate($angle);";


	}
	if ($color) {
		$borderCol = darken($color, 3);
		$style_content = " style='background: $color;border-left: 1px solid $borderCol;border-bottom: 1px solid $borderCol;'";
	}
	if ($style) {
		$style = " style='$style'";
	}
	$str = <<<EOT

<div id='post-it$inx' class='post-it'$style>
	<div class='post-it-bg'></div>
	<div class='post-it-content'$style_content>
		<a href='#' class='close_icon' title='Schliessen'><img src='{$sys}rsc/close32.png' alt=''></a>
$text
	</div>
</div> <!-- /post-it -->

EOT;
	return $str;
});
