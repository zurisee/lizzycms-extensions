<?php

// @info: Renders components of online forms.


require_once SYSTEM_PATH.'forms.class.php';

/*
** For manipulating the embedding page, use $page:
**		$page->addHead('')
**		$page->addCssFiles('')
**		$page->addCss('')
**		$page->addJsFiles('')
**		$page->addJs('')
**		$page->addJqFiles('')
**		$page->addJq('')
**		$page->addBody_end_injections('')
**		$page->addMessage('')
**		$page->addPageReplacement('')
**		$page->addOverride('')
**		$page->addOverlay('')
*/
$page->addCssFiles('~sys/css/lizzy_forms.css');
$page->addJqFiles('JQUERY');


$macroName = basename(__FILE__, '.php');

// Evaluate if form data received
if (isset($_GET['lizzy_form']) || isset($_POST['lizzy_form'])) {	// we received data:
	$this->form = new Forms($page, $this);
	$page->formEvalResult = $this->form->evaluate();
	unset($this->form);
}

$jsFloatPath = "{$sys}third-party/floatlabel/";

$page->addCssFiles( [ "{$jsFloatPath}jquery.FloatLabel.css", "{$jsFloatPath}main.css" ]);
$page->addJqFiles("{$jsFloatPath}jquery.FloatLabel.js");
$jq = <<<EOT
	if ($('body').hasClass('touch') && $('body').hasClass('small-screen')) {
		$('input').each(function () {
			var type = $(this).attr('type');
			if ((type == 'hidden') || (type == 'submit') || (type == 'reset') || (type == 'cancel')) {
				return;
			}
			var \$label = $("label[for='"+$(this).attr('id')+"']");
			var label = \$label.text().replace(/[\:\*]/g, '').trim();
//mylog('label: ' + label + ' for: ' + $(this).attr('type'));
			\$label.html(label);
		});

		$('form .field-wrapper').addClass('js-float-label-wrapper').FloatLabel();
		mylog('FloatLabel activated');		
	}

	$('#showPassword:checkbox').change(function(e) {
		if($(this).is(":checked")) {
			$('#fld_passwort').attr('type', 'text');
		} else {
			$('#fld_passwort').attr('type', 'password');
		}
	});
/*
	$('input[type=submit]').click(function(e) {
		var \$form = $(this).closest('form');
console.log('submitting: ');
		\$form[0].submit();
	});
*/
	$('input[type=reset]').click(function(e) {
		var \$form = $(this).closest('form');
		$('.lizzy_time',  \$form ).val(0);
var t = $('.lizzy_time',  \$form ).val();
console.log('resetting t: ' + t);
		\$form[0].submit();
	});
	
	$('input[type=button]').click(function(e) {
		var \$form = $(this).closest('form');
		var next = $('.lizzy_next',  \$form ).val();
console.log(\$form.attr('class') + ' => ' + next);
		window.location.href = next;
	});

EOT;
$page->addJq($jq);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$args = $this->getArgsArray($macroName);

	if (isset($args[0]) && ($args[0] == 'help')) {
	    return '';
    }

	if ($inx == 1) {
		$this->form = new Forms($this->page, $this);
	}
	
	$str = $this->form->render($args);
	
	return $str;
});
