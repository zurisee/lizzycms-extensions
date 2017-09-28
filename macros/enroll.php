<?php

$macroName = basename(__FILE__, '.php');
define('ENROLL_LOG_FILE', 'enroll.txt');
define('ENROLL_DATA_FILE', 'enroll.yaml');

$enroll_form_created = false;

$page->addCssFiles(['JQUERYUI_CSS', '~sys/css/enroll.css']);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $enroll_list_name = $this->getArg($macroName, 'enroll_list_name', '', '');
    $n_needed = $this->getArg($macroName, 'n_needed', '', '');
    $n_reserve = $this->getArg($macroName, 'n_reserve', '', 0);
    $data_path = $this->getArg($macroName, 'data_path', '', '~page/');

	$enroll = new enroll($data_path, $this);
	$out = $enroll->enroll($enroll_list_name, $n_needed, $n_reserve);
	return $out;
});

//=== class ====================================================================
class enroll
{
	function __construct($data_path, $trans)
	{
		$this->admin_mode = false; //???($param['user'] == 'enroll');
		$this->trans = $trans;
		if ($this->admin_mode) {
			$trans->addTerm('enroll_result_link', "<a href='?enroll_result'>{{ Show Enrollment Result }}</a>");
		}
	
		$this->err_msg = '';
		$this->name = '';
		$this->email = '';
		$this->phone = '';
		$this->action = '';
		$this->focus = '';
		$this->show_result = false;
		$this->enroll_form_created = false;
		
		$this->enroll_list_name = '';
		$this->enroll_list_id = '';
	
		$this->data_path = fixPath($data_path);
		$this->dataFile = resolvePath($data_path.ENROLL_DATA_FILE);
		$this->logFile = resolvePath($data_path.ENROLL_LOG_FILE);
		
		$this->enroll_form_created = false;
	
		preparePath($this->dataFile);
		if (!file_exists($this->logFile)) {
			file_put_contents($this->logFile, "Timestamp\tAction\tList\tName\tEmail\tPhone\tClient\tIP\n");
		}
		$this->handle_post_data();
	} // __construct


	//----------------------------------------------------------------------
	function handle_post_data() {
		
		if ($this->admin_mode && getUrlArg('enroll_result')) {
			$this->show_result = true;
			return;
		}
	
		if (isset($_POST) && $_POST) {
			$action = get_post_data('type');
			$id = $this->enroll_list_id = trim(get_post_data('list_id'));
			$name = get_post_data('name');
			if (!$name) {
				return;
			}
			$admin_mode = '';
			if ($this->admin_mode) {
				$name = preg_replace('/\s*\<.*\>$/m', '', $name);
				$admin_mode = 'admin ';
			}
			$email = strtolower(get_post_data('email'));
			$phone = get_post_data('phone');
			file_put_contents($this->logFile, timestamp()."\t$admin_mode$action\t{$this->enroll_list_id}\t$name\t$email\t$phone\t{$_SERVER["HTTP_USER_AGENT"]}\t{$_SERVER['REMOTE_ADDR']}\n", FILE_APPEND);
	
			if (!is_legal_email_address($email) && !$this->admin_mode) {
				writeLog("\tError: illegal email address [$name] [$email]");
				$this->err_msg = '{{ illegal email address }}';
				$this->name = $name;
				$this->email = $email;
				$this->phone = $phone;
				$this->focus = 'email';
			}
			$_POST = array();
			$file = $this->dataFile;
			if (!($enrollData = getYamlFile($file))) {
				$enrollData[$id] = array();
			}
			if (!isset($enrollData[$id])) {
				$enrollData[$id] = array();
			}
			$entry = &$enrollData[$id];
			if ($action == 'add') {
				$this->action = 'add';
				if (!$this->err_msg) {
					foreach ($entry as $n => $rec) {
						if ($rec['Name'] == $name){ // name already exists
							writeLog("\tError: enrolling twice [$name] [$email]");
							$this->err_msg = '{{ enroll entry already exists }}';
							$this->name = $name;
							$this->email = $email;
							$this->phone = $phone;
							$this->focus = 'name';
							return;
						}
					}
					$n = sizeof($entry);
					$entry[$n]['Name'] = $name;
					$entry[$n]['EMail'] = $email;
					$entry[$n]['Phone'] = $phone;
					$entry[$n]['time'] = time();
				}
	
			} elseif ($action == 'delete') {
				$this->action = 'delete';
				$found = false;
				$name = trim($name);
				$entry1 = array();
				$i = 0;
				foreach ($entry as $n => $rec) {
					if ($rec['Name'] == $name) {
						if ($this->admin_mode) {       	// admin-mode needs no email and has no timeout
							$found = true;
							unset($entry[$n]);
							break;
						}
						if (intval($rec['time']) > time()-(3600*24)) {
							if ($rec['EMail'] != $email) {
								writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
								$this->err_msg = '{{ enroll email wrong }}';
								$this->name = $name;
								$this->email = $email;
								$this->phone = $phone;
								$this->focus = 'email';
							} else {
								$found = true;
								continue;
							}
						} else{
							writeLog("\tError: enroll entry too old [$name] [$email]");
							$this->err_msg = '{{ enroll entry too old }}';
							$this->name = $name;
							$this->email = $email;
							$this->phone = $phone;
						}
						$found = true;
					}
					$entry1[$i] = $rec;
					$i++;
				}
				$enrollData[$id] = $entry1;
				if (!$found) {
					writeLog("\tError: no enroll entry found [$name] [$email]");
					$this->err_msg = '{{ no enroll entry found }}';
					$this->name = $name;
					$this->email = $email;
					$this->phone = $phone;
				}
			} else {
				return; // nothing to do
			}
	
			$yaml = convertToYaml($enrollData);
			file_put_contents($this->dataFile, $yaml);
		}
	} // handle $_POST

	//-----------------------------------------------------------------------------------------------
	function enroll($enroll_list_name, $n_needed, $n_reserve = 0) {
		global $enroll_form_created, $page;
	
		$this->enroll_list_id = base_name(translateToFilename($enroll_list_name), false);
		$this->enroll_list_name = $enroll_list_name = str_replace("'", '&prime;', $enroll_list_name);
	
		if (!($enrollData = getYamlFile($this->dataFile))) {
			$enrollData[$this->enroll_list_id] = array();
		}
		$entry = &$enrollData[$this->enroll_list_id];
		$n_entries = sizeof($entry);
		$dialogs = '';
		if (!$enroll_form_created) {
			$enroll_form_created = true;
			$dialogs .= "\n<!-- Enrollment Dialogs ------------------------------->\n<div id='popup-bg' class='hide_dialog'>\n";
			$dialogs .= $this->create_add_dialog();
			$dialogs .= $this->create_delete_dialog();
			$dialogs .= "</div>\n<!-- /enroll_dialogs -->\n";
			$this->trans->page->addBody_end_injections($dialogs); 
			$this->trans->page->addJQ($this->create_jq_scripts());
		} elseif ($this->show_result) {
			return '';
		}
	
			
		$out = "\n\t<div class='$this->enroll_list_id enrollment_list' data-dialog-title='$enroll_list_name'>\n";
	
		$nn = $n_needed + $n_reserve;
		$new_field_done = false;
		for ($n=0; $n < $nn; $n++) {			// loop over list
			$res = ($n >= $n_needed) ? ' enroll_reserve_field': '';
			$name =  '';
			$email = '';
			$phone = '';
			$time =  '';
			$a = '&nbsp;';
			$icon = '';
			$num = "<span class='num'>".($n+1).":</span>";
			$tooltip = 'Name löschen';
	
			if (isset($entry[$n]['Name'])) {	// Name exists -> delete
				$name =  $entry[$n]['Name'];
				$email = $entry[$n]['EMail'];
				$phone = $entry[$n]['Phone'];
				$time =  $entry[$n]['time'];
				if ($this->admin_mode) {
					$name .= " &lt;$email>";
					if ($phone) {$name .= " $phone"; }
					$tooltip = $name;
				}
				if ((intval($entry[$n]['time']) > time()-(3600*24)) || $this->admin_mode) {
					$icon = "<span class='enroll_del'>−</span>";
					$a = "<a href='#delDialog' title='$tooltip'><span class='name'>$name</span>$icon</a>";
				} else {
					$a = "<span class='name'>$name</span>";
				
				}	
				$class = 'del_field';
				
			} else {			// add
				if (!$new_field_done) {
					$name = '{{ Enroll me }}';
					$icon = "<span class='enroll_add'>+</span>";
					$a = "<a href='#addDialog' title='Neuen Namen eintragen' data-rel='popup' data-position-to='window' data-transition='pop'>\n\t\t\t<span class='name'>$name</span>$icon\n\t\t</a>";
					$new_field_done = true;
					$class = 'add_field';
	
				} else {		// free cell
					$name = '&nbsp;';
					$class = 'empty_field';
	
					$a = "&nbsp;";
				}			
			}
			
			$out .= "<div class='enroll_field $class$res'>\n\t\t$num\n\t\t$a\n\t</div><!-- /$class -->";
		}
	
		$out .= "\t</div> <!-- /enrollment_list -->\n  ";

		return $out;
	} // m_enroll

	//----------------------------------------
	function do_show_result() {
		return "[ not implmented yet ]";
	
		$out = '<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<title></title>
	<style type="text/css">
		table { border-collapse: collapse;}
		td { padding: 4px; border: 1px solid gray; min-width: 10em;}
		td:last-child { display: none;}
	</style>
</head>
<body>
';
		foreach ($files as $f) {
			if (strpos($f, ENROLL_LOG_FILE) !== false) {
				continue;
			}
			$title = '<strong>'.base_name($f, false)."</strong>:";
			$title = str_replace('_', ' ', $title);
			$tab = '';
			$out .= "<p>$title</p>\n$tab\n";
		}
		$out .= "</body>\n</html>\n";
		return "[ not implmented yet ]";
	} // do_show_result

	//------------------------------------
	function create_jq_scripts() {
		$dest = $_SERVER['REQUEST_URI'];
		$out = <<<EOT

	<!-- Enrollment jQuery Scripts -------------------------------->
		var \$err_msg = $('.err_msg');
		if (\$err_msg.text()) {						// ErrMsg dialog
			\$form = \$err_msg.parent();
			var dialog_id = '#' + \$form.parent().attr('id');
			if (dialog_id == '#addDialog') {
				$('#a_name', \$form).val('{$this->name}');
				$('#a_email', \$form).val('{$this->email}');
				$('#a_phone', \$form).val('{$this->phone}');
				$('#add_list_id', \$form).val('{$this->enroll_list_id}');
				$('#a_type', \$form).val('{$this->action}');
				var dialog_title = '{$this->enroll_list_name}';
				$('#addDialog').removeClass("hide_dialog");
				if ('{$this->focus}' != '') {
					$('#addDialog #a_{$this->focus}').focus();
				}
				
			} else if (dialog_id == '#delDialog') {
				$('#d_name', \$form).val('{$this->name}');
				$('#d_email', \$form).val('{$this->email}');
				$('#d_phone', \$form).val('{$this->phone}');
				$('#add_list_id', \$form).val('{$this->enroll_list_id}');
				$('#d_type', \$form).val('{$this->action}');
				var dialog_title = '{$this->enroll_list_name}';
				$('#delDialog').removeClass("hide_dialog");
				if ('{$this->focus}' != '') {
					$('#delDialog #d_{$this->focus}').focus();
				}
			}
		}

		$('.enrollment_list .add_field a').click(function(e) {		// open add dialog
			e.preventDefault();
			revealPopup('#addDialog', e.pageX, e.pageY);

			var \$wrapper = $(this).parent().parent();	// <a> of the click
			var dialog_title = \$wrapper.attr('data-dialog-title');
			var elem_class = \$wrapper.attr('class').replace(/\s.*/, '');
			$('#add_list_id').val(elem_class);
			$('#addDialog').removeClass("hide_dialog");
		});
		
		$('.enrollment_list .del_field a').click(function(e) {		// open delete dialog
			e.preventDefault();
			revealPopup('#delDialog', e.pageX, e.pageY);

			var \$wrapper = $(this).parent().parent();	// <a> of the click
			var dialog_title = \$wrapper.attr('data-dialog-title');
			var elem_class = \$wrapper.attr('class').replace(/\s.*/, '');
			var name = $('.name', \$(this)).text();
			var email = '';
			if (name.match(/\</)) {
				email = name.replace(/.*\</, '').replace(/\>.*/, '');
				name = name.replace(/\<.*/, '');
			}
			var dialog_id = $(this).attr('href');
			$('#del_Name').val(name);
			$('#del_Name_text').text(name);
			$('#del_EMail').val(email);
			$('#delete_list_id').val(elem_class);
			$(dialog_id).removeClass("hide_dialog");
		});
		
		$('button#a_submit').click(function(e) {	// submit button in add dialog
			$(this).prop("disabled",true);
			$('#form_add').submit();
		});
		$('button#d_submit').click(function(e) {	// cancel button in delete dialog
			$(this).prop("disabled",true);
			$('#form_del').submit();
		});
		$('button#a_cancel').click(function(e) {	// cancel button in add dialog
			e.preventDefault();
			window.location = '$dest';
		});
		$('button#d_cancel').click(function(e) {	// cancel button in delete dialog
			e.preventDefault();
			window.location = '$dest';
		});
		$('.dialog-close').click(function(e) {	// close icon in dialog
			e.preventDefault();
			window.location = '$dest';
		});

		function revealPopup(id, mouseX, mouseY) {
			var pageW = $( window ).width();
			var pageH = $( window ).height();
			var popupW = 400;
			var popupH = 380;
			var maxX = pageW-popupW-20;
			var maxY = pageH-popupH-0;
			if (pageW < 480) {
				$(id).css('left', 0).css('top', 0).css('width', '100%').css('height', '100%');
			} else {
				if (mouseX > maxX) {
					mouseX = maxX;
				}
				if (mouseY > maxY) {
					mouseY = maxY;
				}
				$(id).css('left', mouseX).css('top', mouseY);
			}
			$('#popup-bg').removeClass("hide_dialog");
		} // revealPopup
EOT;

		return $out;
	} // create_jq_scripts

	//------------------------------------
	function create_add_dialog() {
		$err_msg = '';
		if (($this->action == 'add') && $this->err_msg) {
			$err_msg = "\n\t<div class='err_msg'>$this->err_msg</div>";
		}
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

<div id="addDialog" data-role="popup" data-theme="a" class="enrollment_dialog hide_dialog">
	<div class='dialog-close'>⊗</div>
    <form id="form_add" method='post' action='$url'>$err_msg
	<input type="hidden" id='add_list_id' name='list_id' value='' />
	<input type="hidden" id='a_type' name='type' value='add' />
        <div>
            <h3>{{ Enroll add }}</h3>
            <label for="name" class="ui-hidden-accessible">Name:</label>
            <input type="text" name="name" id="a_name" value="" placeholder="Vorname Name" data-theme="a" required aria-required="true" autofocus />
	    
            <label for="email" class="ui-hidden-accessible">E-Mail:</label>
            <input type="text" name="email" id="a_email" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />

            <label for="phone" class="ui-hidden-accessible">Handy:</label>
            <input type="text" name="phone" id="a_phone" value="" placeholder="Handynummer (optional)" data-theme="a" />

            <button type="submit" id="a_submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
            <button type="cancel" id="a_cancel" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
        </div>
    <div style='clear:both;'></div>
	<div class='Enroll_comment'>{{ Enroll add comment }}</div>
   </form>
</div><!-- /addDialog -->

EOT;

		return $form;
	} // create_add_dialog

	//------------------------------------
	function create_delete_dialog() {
		$err_msg = '';
		if (($this->action == 'delete') && $this->err_msg) {
			$err_msg = "\n\t<div class='err_msg'>$this->err_msg</div>";
		}
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

<div id="delDialog" data-role="popup" data-theme="a" class="enrollment_dialog hide_dialog">
	<div class='dialog-close'>⊗</div>
    <form id="form_del" action='$url' method='post' >$err_msg
        <div>
		<h3>{{ Enroll delete }}</h3>
		<input type="hidden" id='delete_list_id' name='list_id' value='' />
		<input type="hidden" id='d_type' name='type' value='delete' />
		
		<input type="hidden" id='del_Name' name='name' value='unknown' />
		<div class='name'>{{ Enroll delete for }}:</div><div class='c2' id='del_Name_text'></div><br />
		
		<label class='ui-hidden-accessible name' for='email'>E-Mail:</label>
		<input type="text" id='del_EMail' name='email' placeholder="name@domain.net" data-theme="a" autofocus />
		
		<button type="submit" id="d_submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll delete now }}</button>
		<button type="cancel" id="d_cancel" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
	</div>
    <div style='clear:both;'></div>
	<div class='Enroll_comment'>{{ Enroll delete comment }}</div>
    </form>
</div><!-- /delDialog  -->

EOT;
		return $form;
	} // create_delete_dialog

} // class enroll

