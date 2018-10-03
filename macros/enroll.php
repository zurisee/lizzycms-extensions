<?php

// @info:  Lets you set up enrollment lists where people can put their name to indicate that they intend to participate at some event, for instance.

$macroName = basename(__FILE__, '.php');
define('ENROLL_LOG_FILE', 'enroll.txt');
define('ENROLL_DATA_FILE', 'enroll.yaml');

$enroll_form_created = false;

$page->addCssFiles(['JQUERYUI_CSS', '~sys/css/enroll.css']);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $enroll_list_name = $this->getArg($macroName, 'listname', 'Any word identifying the enrollment list', "Enrollment-List$inx");
    $n_needed = $this->getArg($macroName, 'n_needed', 'Number of fields in category "needed"', '');
    $n_reserve = $this->getArg($macroName, 'n_reserve', 'Number of fields in category "reserve" -> will be visualized differently', 0);
    $data_path = $this->getArg($macroName, 'data_path', 'Where to store data files, default is folder local to current page', '~page/');

    if ($enroll_list_name == 'help') {
        return '';
    }

    $enroll = new enroll($inx, $data_path, $this);
	$out = $enroll->enroll($enroll_list_name, $n_needed, $n_reserve);
	return $out;
});

//=== class ====================================================================
class enroll
{
	function __construct($inx, $data_path, $trans)
	{
	    $this->inx = $inx;
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
			$action = get_post_data('lzy-enroll-type');
			$id = $this->enroll_list_id = trim(get_post_data('lzy-enroll-list-id'));
			$name = get_post_data('lzy-enroll-name');
			if (!$name) {
				return;
			}
			$admin_mode = '';
			if ($this->admin_mode) {
				$name = preg_replace('/\s*\<.*\>$/m', '', $name);
				$admin_mode = 'admin ';
			}
			$email = strtolower(get_post_data('lzy-enroll-email'));
			$phone = get_post_data('lzy-enroll-phone');
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
			$dialogs .= "\n<!-- Enrollment Dialogs ------------------------------->\n<div id='lzy-enroll-popup-bg' class='lzy-enroll-hide-dialog'>\n";
			$dialogs .= $this->create_add_dialog();
			$dialogs .= $this->create_delete_dialog();
			$dialogs .= "</div>\n<!-- /enroll_dialogs -->\n";
			$this->trans->page->addBody_end_injections($dialogs); 
			$this->trans->page->addJQ($this->create_jq_scripts());
		} elseif ($this->show_result) {
			return '';
		}
	
			
		$out = "\n\t<div class='$this->enroll_list_id lzy-enrollment-list' data-dialog-title='$enroll_list_name'>\n";
	
		$nn = $n_needed + $n_reserve;
		$new_field_done = false;
		for ($n=0; $n < $nn; $n++) {			// loop over list
			$res = ($n >= $n_needed) ? ' lzy-enroll-reserve-field': '';
			$name =  '';
			$email = '';
			$phone = '';
			$time =  '';
			$a = '&nbsp;';
			$icon = '';
			$num = "<span class='lzy-num'>".($n+1).":</span>";
			$tooltip = 'Name löschen';
            $class = '';

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
					$icon = "<span class='lzy-enroll-del'>−</span>";
					$a = "<a href='#delDialog' title='$tooltip'><span class='lzy-name'>$name</span>$icon</a>";
                    $class = 'lzy-enroll-del_field';
				} else {
					$a = "<span class='lzy-name' title='$name'>$name</span>";
				
				}	

			} else {			// add
				if (!$new_field_done) {
					$name = '{{ Enroll me }}';
					$icon = "<span class='lzy-enroll-add'>+</span>";
					$a = "<a href='#addDialog' title='Neuen Namen eintragen' data-rel='popup' data-position-to='window' data-transition='pop'>\n\t\t\t<span class='lzy-name'>$name</span>$icon\n\t\t</a>";
					$new_field_done = true;
					$class = 'lzy-enroll-add-field';
	
				} else {		// free cell
					$name = '&nbsp;';
					$class = 'lzy-enroll-empty-field';
	
					$a = "&nbsp;";
				}			
			}
			
			$out .= "<div class='lzy-enroll-field $class$res'>\n\t\t$num\n\t\t$a\n\t</div><!-- /$class -->";
		}
	
		$out .= "\t</div> <!-- /lzy-enrollment-list -->\n  ";

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
	<style>
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
		var \$err_msg = $('.lzy-err-msg');
		if (\$err_msg.text()) {						// ErrMsg dialog
			\$form = \$err_msg.parent();
			var dialog_id = '#' + \$form.parent().attr('id');
			if (dialog_id == '#addDialog') {
				$('#a_name', \$form).val('{$this->name}');
				$('#a_email', \$form).val('{$this->email}');
				$('#a_phone', \$form).val('{$this->phone}');
				$('#lzy-add-list-id', \$form).val('{$this->enroll_list_id}');
				$('#lzy-enroll-add-type', \$form).val('{$this->action}');
				var dialog_title = '{$this->enroll_list_name}';
				$('#addDialog').removeClass("lzy-enroll-hide-dialog");
				if ('{$this->focus}' != '') {
					$('#addDialog #a_{$this->focus}').focus();
				}
				
			} else if (dialog_id == '#delDialog') {
				$('#d_name', \$form).val('{$this->name}');
				$('#d_email', \$form).val('{$this->email}');
				$('#d_phone', \$form).val('{$this->phone}');
				$('#lzy-add-list-id', \$form).val('{$this->enroll_list_id}');
				$('#lzy-enroll-del-type', \$form).val('{$this->action}');
				var dialog_title = '{$this->enroll_list_name}';
				$('#delDialog').removeClass("lzy-enroll-hide-dialog");
				if ('{$this->focus}' != '') {
					$('#delDialog #d_{$this->focus}').focus();
				}
			}
		}

		$('.lzy-enrollment-list .lzy-enroll-add-field a').click(function(e) {		// open add dialog
			e.preventDefault();
			revealPopup();

			var \$wrapper = $(this).parent().parent();	// <a> of the click
			var dialog_title = \$wrapper.attr('data-dialog-title');
			var elem_class = \$wrapper.attr('class').replace(/\s.*/, '');
			$('#lzy-enroll-add-list-id').val(elem_class);
			$('#addDialog').removeClass("lzy-enroll-hide-dialog");
		});
		
		$('.lzy-enrollment-list .lzy-enroll-del_field a').click(function(e) {		// open delete dialog
			e.preventDefault();
			revealPopup();

			var \$wrapper = $(this).parent().parent();	// <a> of the click
			var dialog_title = \$wrapper.attr('data-dialog-title');
			var elem_class = \$wrapper.attr('class').replace(/\s.*/, '');
			var name = $('.lzy-name', \$(this)).text();
			var email = '';
			if (name.match(/\</)) {
				email = name.replace(/.*\</, '').replace(/\>.*/, '');
				name = name.replace(/\<.*/, '');
			}
			var dialog_id = $(this).attr('href');
			$('#del_Name').val(name);
			$('#del_Name_text').text(name);
			$('#del_EMail').val(email);
			$('#lzy-enroll-del-list-id').val(elem_class);
			$(dialog_id).removeClass("lzy-enroll-hide-dialog");
		});
		
		$('button#a_submit').click(function(e) {	// submit button in add dialog
			$(this).prop("disabled",true);
			$('#lzy-enroll-add-form').submit();
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
		$('.lzy-enroll-dialog-close').click(function(e) {	// close icon in dialog
			e.preventDefault();
			window.location = '$dest';
		});

		function revealPopup() {
			$('#lzy-enroll-popup-bg').removeClass("lzy-enroll-hide-dialog");
		} // revealPopup
EOT;

		return $out;
	} // create_jq_scripts

	//------------------------------------
	function create_add_dialog() {
		$err_msg = '';
		if (($this->action == 'add') && $this->err_msg) {
			$err_msg = "\n\t<div class='lzy-err-msg'>$this->err_msg</div>";
		}
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

<div id="addDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
	<div class='lzy-enroll-dialog-close'>⊗</div>
    <form id="lzy-enroll-add-form" method='post' action='$url'>$err_msg
	<input type="hidden" id='lzy-enroll-add-list-id' name='lzy-enroll-list-id' value='' />
	<input type="hidden" id='lzy-enroll-add-type' name='lzy-enroll-type' value='add' />
        <div>
            <h3>{{ Enroll add }}</h3>
            <label for="lzy-name" class="ui-hidden-accessible">Name:</label>
            <input type="text" name="lzy-enroll-name" id="a_name" value="" placeholder="{{ placeholder name }}" data-theme="a" required aria-required="true" autofocus />
	    
            <label for="lzy-add-email" class="ui-hidden-accessible">E-Mail:</label>
            <input type="text" name="lzy-enroll-email" id="lzy-add-email" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />

            <label for="lzy-phone" class="ui-hidden-accessible">Handy:</label>
            <input type="text" name="lzy-enroll-phone" id="lzy-phone" value="" placeholder="{{ placeholder mobile number }}" data-theme="a" />

            <button type="submit" id="a_submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
            <button type="cancel" id="a_cancel" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
        </div>
    <div style='clear:both;'></div>
	<div class='lzy-enroll-comment'>{{ Enroll add comment }}</div>
   </form>
</div><!-- /addDialog -->

EOT;

		return $form;
	} // create_add_dialog

	//------------------------------------
	function create_delete_dialog() {
		$err_msg = '';
		if (($this->action == 'delete') && $this->err_msg) {
			$err_msg = "\n\t<div class='lzy-err-msg'>$this->err_msg</div>";
		}
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

<div id="delDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
	<div class='lzy-enroll-dialog-close'>⊗</div>
    <form id="form_del" action='$url' method='post' >$err_msg
        <div>
		<h3>{{ Enroll delete }}</h3>
		<input type="hidden" id='lzy-enroll-del-list-id' name='lzy-enroll-list-id' value='' />
		<input type="hidden" id='lzy-enroll-del-type' name='lzy-enroll-type' value='delete' />
		
		<input type="hidden" id='del_Name' name='lzy-enroll-name' value='unknown' />
		<div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-c2' id='del_Name_text'></div><br />
		
		<label class='ui-hidden-accessible lzy-name' for='lzy-del-email'>E-Mail:</label>
		<input type="text" id='lzy-del-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" autofocus />
		
		<button type="submit" id="d_submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll delete now }}</button>
		<button type="cancel" id="d_cancel" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
	</div>
    <div style='clear:both;'></div>
	<div class='lzy-enroll-comment'>{{ Enroll delete comment }}</div>
    </form>
</div><!-- /delDialog  -->

EOT;
		return $form;
	} // create_delete_dialog

} // class enroll

