<?php
/*
 **	Macro() Plug-in 
 ** displays a visible note in the shape of a "Post-it"sticker
*/
// @info: Renders content as sticky notes.


//$page->addHead("\t<link href='https://fonts.googleapis.com/css?family=Kalam' rel='stylesheet'>\n");

$str =  <<<EOT

			$('.post-it').panzoom();
			$('.post-it .close_icon').click(function() {
				$(this).parent().parent().hide();
			});

EOT;
$page->addJQ($str);

$page->addCssFiles('~ext/postit/css/post-it.css');
//$page->addCssFiles('~sys/css/post-it.css');
$page->addJqFiles(['PANZOOM']);

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = '~/'.$this->config->systemPath;

    $text = $this->getArg($macroName, 'text', 'Text to be displayed on the Post-it', '');
    $right = $this->getArg($macroName, 'right', 'Where to place measured from right (use negative value for offset from left)', false);
    $top = $this->getArg($macroName, 'top', 'Where to place measured from top ', false);
    $angle = $this->getArg($macroName, 'angle', 'Angle by which the Post-it is tilted', '');
    $color = $this->getArg($macroName, 'color', 'Background-color for Post-it', '');

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



//------------------------------------------------------------------------------
function darken($hexColor, $decr)
{
    if (!preg_match('/^\#([\da-f])([\da-f])([\da-f])$/i', trim($hexColor), $m) &&
        !preg_match('/^\#([\da-f][\da-f])([\da-f][\da-f])([\da-f][\da-f])$/i', trim($hexColor), $m)) {
        return "#000 /*bad color value submitted to darken(): $hexColor*/";
    }
    if (!$decr) {
        $decr = 1;
    }
    if (strlen($m[1]) == 2) {
        $decr *= 16;
    }
    $r = dechex(hexdec($m[1]) - $decr);
    $g = dechex(hexdec($m[2]) - $decr);
    $b = dechex(hexdec($m[3]) - $decr);
    return "#$r$g$b";
} // darken



