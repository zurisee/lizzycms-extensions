<?php

// @info:  Lets you set up enrollment lists where people can put their name to indicate that they intend to participate at some event, for instance.

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/vars.yaml"));

define('ENROLL_LOG_FILE', 'enroll.log.csv');
define('ENROLL_DATA_FILE', 'enroll.yaml');

$GLOBALS['enroll_form_created']['std'] = false;

$page->addModules('~ext/enroll/js/enroll.js, ~ext/enroll/css/enroll.css');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $h = $this->getArg($macroName, 'nNeeded', 'Number of fields in category "needed"', 1);
    $this->getArg($macroName, 'nReserve', 'Number of fields in category "reserve" -> will be visualized differently', 0);
    $this->getArg($macroName, 'listname', 'Any word identifying the enrollment list', "Enrollment-List$inx");
    $this->getArg($macroName, 'customFields', '[comma separated list] List of additional field labels (optional)', false);
    $this->getArg($macroName, 'customFieldPlaceholders', "[comma separated list] List of placeholder used in custom-fields (optional).<br>Special case: \"[val1|val2|...]\" creates dropdown selection.", '');
    $this->getArg($macroName, 'dataPath', 'Where to store data files, default is folder local to current page', '~page/');
    $this->getArg($macroName, 'logAgentData', "[true,false] If true, logs visitor's IP and browser info (illegal if not announced to users)", false);
    $this->getArg($macroName, 'editableTime', '[false, -n, time-string] Defines how long, resp. until when a user can delete/modify his/her entry. Duration is specified as a negative number of seconds (default: -86400 = 1 day)', -86400);
    $this->getArg($macroName, 'editable', '[true|false] If true, users can modify their entries', false);
    $this->getArg($macroName, 'n_needed', 'Synonym for "nNeeded"', false);
    $this->getArg($macroName, 'n_reserve', 'Synonym for "nReserve"', false);
    $this->getArg($macroName, 'data_path', 'Synonyme for dataPath', false);

    if ($h === 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $enroll = new enroll($inx, $this, $args);
    $out = $enroll->render();
	return $out;
});




//=== class ====================================================================
class enroll
{
	public function __construct($inx, $trans, $args)
	{
	    $this->inx = $inx;
        $this->listname = $args['listname'];
        $this->nNeeded = $args['nNeeded'];
        if ($args['n_needed'] !== false) {
            $this->nNeeded = $args['n_needed'];
        }
        $this->nReserve = $args['nReserve'];
        if ($args['n_reserve'] !== false) {
            $this->nReserve = $args['n_reserve'];
        }
        $this->customFields = $args['customFields'];
        $this->customFieldPlaceholders = $args['customFieldPlaceholders'];
        $this->data_path = $args['dataPath'];
        if ($args['data_path']) {
            $this->data_path = $args['data_path'];
        }
        $this->logAgentData = $args['logAgentData'];
        $this->editableTime = $args['editableTime'];
        $this->editable = $args['editable'];

		$this->admin_mode = false;
		$this->trans = $trans;

        // editable time: false=forever; pos int=specfic date; neg int=duration after rec stored/modified:
        if ($this->editableTime && !preg_match('/^-?\d+$/', $this->editableTime)) {
            $this->editableTime = strtotime('+1 day', strtotime($this->editableTime)); // -> include given date
        }

        if ($this->admin_mode) {
			$trans->addTerm('enroll_result_link', "<a href='?enroll_result'>{{ Show Enrollment Result }}</a>");
		}
	
		$this->err_msg = '';
		$this->name = '';
		$this->email = '';
		$this->action = '';
		$this->focus = '';
		$this->show_result = false;

        $this->enroll_list_id = base_name(translateToFilename($this->listname), false);
        $this->enroll_list_name = str_replace("'", '&prime;', $this->listname);

        $this->data_path = fixPath($this->data_path);
		$this->dataFile = resolvePath($this->data_path.ENROLL_DATA_FILE);
		$this->logFile = resolvePath($this->data_path.ENROLL_LOG_FILE);

        $this->customFieldsList = explodeTrim(',|', $this->customFields);
        $this->customFieldsDisplayList = [];
        foreach ($this->customFieldsList as $i => $item) {
            if (preg_match('/^ \( (.*) \) $/x', $item, $m)) {
                $this->customFieldsList[$i] = trim($m[1]);
                $this->customFieldsDisplayList[$i] = false;
            } else {
                $this->customFieldsDisplayList[$i] = $item;
            }
        }
        $this->hash = '';
        if ($this->customFields) {
            $this->hash = '-'.hash('crc32', $this->customFields . $this->customFieldPlaceholders);
        }

		preparePath($this->dataFile);
        $this->prepareLog();
        $this->handlePostData();
	} // __construct


	//----------------------------------------------------------------------
	private function handlePostData() {
		
		if ($this->admin_mode && getUrlArg('enroll_result')) {
			$this->show_result = true;
			return;
		}
	
		if (isset($_POST) && $_POST) {
			$action = get_post_data('lzy-enroll-type');
			$id = trim(get_post_data('lzy-enroll-list-id'));
			$name = get_post_data('lzy-enroll-name');
			if (!$name || ($id !== $this->enroll_list_id)) {
				return;
			}
			$admin_mode = '';
			if ($this->admin_mode) {
				$name = preg_replace('/\s*\(.*\)$/m', '', $name);
				$admin_mode = 'admin ';
			}
			$email = strtolower(get_post_data('lzy-enroll-email'));

			$sep = "; ";
			$log = "$sep$admin_mode$action$sep{$this->enroll_list_id}$sep$name$sep$email";

			$i = 0;
			$customFieldValues = [];
			while (isset($_POST["lzy-enroll-custom-$i"])) {
			    $log .= "$sep".$_POST["lzy-enroll-custom-$i"];
                $customFieldValues[$i] = $_POST["lzy-enroll-custom-$i"];
			    $i++;
            }

            if (!is_legal_email_address($email) && !$this->admin_mode) {
				writeLog("\tError: illegal email address [$name] [$email]");
				$this->err_msg = '{{ illegal email address }}';
				$this->name = $name;
				$this->email = $email;
				$this->focus = 'email';
                $this->enrollLog($log);
                return;
			}
			$_POST = [];
			$file = $this->dataFile;
			if (!($enrollData = getYamlFile($file))) {
				$enrollData[$id] = array();
			}
			if (!isset($enrollData[$id])) {
				$enrollData[$id] = array();
			}
			$existingData = &$enrollData[$id];
			if ($action === 'add') {
				$this->action = 'add';
                // check whether nane already entered:
                foreach ($existingData as $n => $rec) {
                    if ($rec['Name'] === $name) { // name already exists:
                        if ($existingData[$n]['EMail'] === $email) {   // with same email, so we let it pass:
                            $rec = &$existingData[$n];
                            $rec['Name'] = $name;
                            $rec['EMail'] = $email;
                            $rec['time'] = time();
                            foreach ($this->customFieldsList as $i => $field) {
                                $rec[$field] = $customFieldValues[$i];
                            }
                            $found = true;
                            break;
                        } else {  // existing name but different email -> reject:
                            writeLog("\tError: enrolling twice [$name] [$email]");
                            $this->err_msg = '{{ enroll entry already exists }}';
                            $this->name = $name;
                            $this->email = $email;
                            $this->focus = 'name';
                            $this->enrollLog($log);
                            return;
                        }
                    }
                }
                if (!isset($found)) {   // new entry:
                    $i = sizeof($existingData);
                    if ($i >= ($this->nNeeded + $this->nReserve)) {
                        return; // request probably tampered: attempt to add more records than allowed
                    }
                    $rec = &$existingData[ $i ];
                    $rec['Name'] = $name;
                    $rec['EMail'] = $email;
                    $rec['time'] = time();
                    foreach ($this->customFieldsList as $i => $field) {
                        $rec[$field] = $customFieldValues[$i];
                    }
                }

			} elseif ($action === 'delete') {
				$this->action = 'delete';
				$found = false;
				$name = trim($name);
				$entry1 = array();
				$i = 0;
				foreach ($existingData as $n => $rec) {
					if ($rec['Name'] === $name) {
						if ($this->admin_mode) {       	// admin-mode needs no email and has no timeout
							$found = true;
							unset($existingData[$n]);
							break;
						}
                        if ($this->isInTime($rec['time'])) {
							if ($rec['EMail'] != $email) {
								writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
								$this->err_msg = '{{ enroll email wrong }}';
								$this->name = $name;
								$this->email = $email;
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
				}
			} else {
				return; // nothing to do
			}

            $this->enrollLog($log);
            $yaml = convertToYaml($enrollData);
			file_put_contents($this->dataFile, $yaml);
		}
	} // handle $_POST




	//-----------------------------------------------------------------------------------------------
	public function render()
    {
        $this->createDialogs();

        if (!($enrollData = getYamlFile($this->dataFile))) {
			$enrollData[$this->enroll_list_id] = array();
		}
        $existingData = [];
        if (isset($enrollData[$this->enroll_list_id])) {
            $existingData = $enrollData[$this->enroll_list_id];
        }

        $out = '';

		$nn = $this->nNeeded + $this->nReserve;
		$new_field_done = false;
		for ($n=0; $n < $nn; $n++) {			// loop over list
			$res = ($n >= $this->nNeeded) ? ' lzy-enroll-reserve-field': '';
			$num = "<span class='lzy-num'>".($n+1).":</span>";

            if (isset($existingData[$n]['Name'])) {	// Name exists -> delete
				$name =  $existingData[$n]['Name'];
				$email = $existingData[$n]['EMail'];
				if ($this->admin_mode) {
					$name .= " &lt;$email>";
				}
				if ($this->customFields && $this->editable) {
                    $targId = "#modifyDialog{$this->hash}";
                    $title = '{{ lzy-enroll-modify-entry }}';
                    $icon = "<span class='lzy-enroll-modify'>&#9998;</span>";
                } else {
                    $targId = "#delDialog{$this->hash}";
                    $title = '{{ lzy-enroll-delete-entry }}';
                    $icon = "<span class='lzy-enroll-del'>−</span>";
                }

				if ($this->isInTime($existingData[$n]['time'])) {
					$a = "<a href='$targId' title='$title'>\n\t\t\t\t  <span class='lzy-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
                    $class = 'lzy-enroll-del_field';
				} else {
					$a = "<span class='lzy-name'>$name</span>";
                    $class = 'lzy-enroll-frozen_field';
				}	

			} else {			// add
				if (!$new_field_done) {
					$name = '{{ Enroll me }}';
					$icon = "<span class='lzy-enroll-add'>+</span>";
					$a = "<a href='#addDialog{$this->hash}' title='{{ lzy-enroll-new-name }}' data-rel='popup' data-position-to='window' data-transition='pop'>\n\t\t\t\t  <span class='lzy-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
					$new_field_done = true;
					$class = 'lzy-enroll-add-field';
	
				} else {		// free cell
					$class = 'lzy-enroll-empty-field';
					$a = "<span class='lzy-name'>&nbsp;</span>\n";
				}			
			}
			
			$rowContent = "\t\t\t<div class='lzy-enroll-field $class'>\n\t\t\t\t$num\n\t\t\t\t$a\n\t\t\t</div><!-- /$class -->";

            // assemble auxiliary fields:
            $aux = '';
            $hdr = '';
            foreach ($this->customFieldsDisplayList as $i => $custField) {
                if (!$custField) {
                    continue;
                }
                $val = isset($existingData[$n][$custField]) && $existingData[$n][$custField] ? $existingData[$n][$custField] : '&nbsp;';
                $aux .= "\n\t\t\t<div class='lzy-enroll-aux-field lzy-enroll-aux-field$i'>\n\t\t\t\t$val\n\t\t\t</div>";
                $hdr .= "\n\t\t\t<div class='lzy-enroll-aux-field'>$custField</div>";
            }
            $out .= "\t\t<div class='lzy-enroll-row$res'>\n$rowContent$aux\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
		}

        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list' data-dialog-title='$this->enroll_list_name' data-dialog-id='{$this->enroll_list_id}' data-dialog-inx='{$this->inx}'>\n";
        if ($hdr) {
            $hdr = "\n\t\t<div class='lzy-enroll-row lzy-enroll-hdr'>\n\t\t\t<div class='lzy-enroll-field'>{{ lzy-enroll-hdr-name }}</div>$hdr\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
            $out0 .= $hdr;
        }
        $out0 .= $out;
        $out0 .= "\t</div> <!-- /lzy-enrollment-list -->\n  ";
        if ($this->err_msg) {
		    $this->trans->page->addMessage($this->err_msg);
        }

		return $out0;
	} // render





    //------------------------------------
    private function createDialogs()
    {
        if ($this->customFields) {
            if (isset($GLOBALS['enroll_form_created'][$this->hash])) {
                return;
            }
            $dialogs = "\n\n<!-- Enrollment Custom Dialogs ------------------------------->\n";
            $dialogs .= "<div id='lzy-enroll-popup-bg-{$this->inx}' class='lzy-enroll-popup-bg lzy-enroll-hide-dialog'>\n";
            $dialogs .= $this->createCustomAddDialog();
            $dialogs .= $this->createCustomDelDialog();
            if ($this->editable) {
                $dialogs .= $this->createModifyDialog();
            }

            $dialogs .= "</div><!-- /Enrollment Custom Dialogs -->\n\n\n";

            $dialogs .= $this->createErrorMsgs();
            $this->trans->page->addBodyEndInjections($dialogs);
            $GLOBALS['enroll_form_created'][$this->hash] = true;

        } elseif (!$GLOBALS['enroll_form_created']['std']) {
            $dialogs = "\n\n<!-- Enrollment Standard Dialogs ----------------------->\n";
            $dialogs .= "<div id='lzy-enroll-popup-bg' class='lzy-enroll-popup-bg lzy-enroll-hide-dialog'>\n";
            $dialogs .= $this->createStdAddDialog();
            $dialogs .= $this->createStdDelDialog();
            $dialogs .= "</div><!-- /Enrollment Standard Dialogs -->\n\n\n";

            $dialogs .= $this->createErrorMsgs();
            $this->trans->page->addBodyEndInjections($dialogs);

            $GLOBALS['enroll_form_created']['std'] = true;
        }
    } // createDialogs



    //------------------------------------
	private function createStdAddDialog() {
		$reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
		$url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

    <!-- === Standard Add Dialog =================== -->
    <div id="addDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
        <div class='lzy-enroll-dialog-close'>⊗</div>
        <form id="lzy-enroll-add-form" method='post' action='$url'>
            <input type="hidden" id='lzy-enroll-add-list-id' class='lzy-enroll-list-id'  name='lzy-enroll-list-id' value='' />
            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
            <div>
                <h3>{{ lzy-enroll-add-title }}</h3>
                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-add-name" class="ui-hidden-accessible">Name:</label>
                    <input type="text" id="lzy-add-name" class="lzy-enroll-name" name="lzy-enroll-name" value="" placeholder="{{ placeholder name }}" data-theme="a" required aria-required="true"  />
                </div>
            
                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-add-email" class="ui-hidden-accessible">E-Mail:</label>
                    <input type="email" id="lzy-add-email" class="lzy-enroll-email" name="lzy-enroll-email" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />
                </div>
                            
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
                    <button type="submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
                    <button type="reset" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
                </div>
            </div>
            <div class='lzy-enroll-comment'>
                {{ Enroll add comment }}
            </div>
        </form>
    </div><!-- /addDialog -->

EOT;

		return $form;
	} // createStdAddDialog



    //------------------------------------
	private function createCustomAddDialog() {
        $customFields = $this->renderAddDialogCustomFields();

		$reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
		$url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

    <!-- === Custom Add Dialog =================== -->
    <div id="addDialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
        <div class='lzy-enroll-dialog-close'>⊗</div>
        <form id="lzy-enroll-add-form{$this->hash}" method='post' action='$url'>
            <input type="hidden" name='lzy-enroll-list-id' value='{$this->enroll_list_id}' />
            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
            <div>
                <h3>{{ lzy-enroll-add-title }}</h3>
                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-add-name{$this->hash}" class="ui-hidden-accessible">Name:</label>
                    <input type="text" class="lzy-enroll-name" name="lzy-enroll-name" id="lzy-add-name{$this->hash}" value="" placeholder="{{ placeholder name }}" data-theme="a" required aria-required="true"/>
                </div>
                            
                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-add-email{$this->hash}" class="ui-hidden-accessible">E-Mail:</label>
                    <input type="email" class="lzy-enroll-email" name="lzy-enroll-email" id="lzy-add-email{$this->hash}" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />
                </div>
                    
$customFields
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
                    <button type="submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
                    <button type="reset" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
                </div>
            </div>
        <div class='lzy-enroll-comment'>
            {{ Enroll add comment }}
        </div>
       </form>
    </div><!-- /Custom Add Dialog -->

EOT;

		return $form;
	} // createStdAddDialog



	//------------------------------------
	private function createStdDelDialog() {
        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

    <!-- === Standard Delete Dialog =================== -->
    <div id="delDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
        <div class='lzy-enroll-dialog-close'>⊗</div>
        <form id="lzy-enroll-del-form" action='$url' method='post' >
            <input type="hidden" class='lzy-enroll-del-list-id' name='lzy-enroll-list-id' value='{$this->enroll_list_id}'' />
            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='delete' />
            <div>
                <h3>{{ Enroll delete }}</h3>
                
                <div class="lzy-enroll-dialog-row">
                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'>?</div><br />
                </div>
                                
                <div class="lzy-enroll-dialog-row">
                    <label class='ui-hidden-accessible lzy-name' for='lzy-del-email'>E-Mail:</label>
                    <input type="email" id='lzy-del-email' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
                </div>
                                
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll delete now }}</button>
                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
                </div>
            </div>
            <div class='lzy-enroll-comment'>
                {{ Enroll delete comment }}
            </div>
        </form>
    </div><!-- /delDialog  -->

EOT;
		return $form;
	} // createStdDelDialog




	//------------------------------------
	private function createCustomDelDialog() {
        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		$form = <<<EOT

    <!-- === Custom Delete Dialog =================== -->
    <div id="delDialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
        <div class='lzy-enroll-dialog-close'>⊗</div>
        <form id="lzy-enroll-del-form{$this->hash}" action='$url' method='post' >
            <input type="hidden" class='lzy-enroll-del-list-id' name='lzy-enroll-list-id' value='{$this->enroll_list_id}' />
            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='delete' />
            <div>
                <h3>{{ Enroll delete }}</h3>

                <div class="lzy-enroll-dialog-row">
                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'></div><br />
                </div>
                
                <div class="lzy-enroll-dialog-row">
                    <label class='ui-hidden-accessible lzy-name' for='lzy-del-email{$this->hash}'>E-Mail:</label>
                    <input type="email" id='lzy-del-email{$this->hash}' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
                </div>
                
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll delete now }}</button>
                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
                </div>
            </div>
            <div class='lzy-enroll-comment'>
                {{ Enroll delete comment }}
            </div>
        </form>
    </div><!-- /delDialog  -->

EOT;
		return $form;
	} // createCustomDelDialog




	//------------------------------------
	private function createModifyDialog() {
        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
        $customFields = $this->renderAddDialogCustomFields('-modify');

        $form = <<<EOT

    <!-- === Custom Modify Dialog =================== -->
    <div id="modifyDialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
        <div class='lzy-enroll-dialog-close'>⊗</div>
        <form id="lzy-enroll-modify-form{$this->hash}" action='$url' method='post' >
            <input type="hidden" class='lzy-enroll-modify-list-id' name='lzy-enroll-list-id' value='{$this->enroll_list_id}' />
            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
            <div>
                <h3>{{ lzy-enroll-delete-or-modify-title }}</h3>

                <div class="lzy-enroll-dialog-row">
                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'></div><br />
                </div>
                
                <div class="lzy-enroll-dialog-row">
                    <label class='ui-hidden-accessible lzy-name' for='lzy-modify-email{$this->hash}'>E-Mail:</label>
                    <input type="email" id='lzy-modify-email{$this->hash}' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
                </div>

$customFields
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll modify save }}</button>
                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
                </div>
                <div class="lzy-enroll-or">{{ lzy-enroll-or }}</div>
                <div class="lzy-enroll-dialog-row lzy-enroll-button-row lzy-enroll-dialog-del-row">
                    <button type="submit" class="lzy-enroll-btn lzy-enroll-delete-entry">{{ lzy-enroll-delete-btn }}</button>
                </div>
            </div>
            <div class='lzy-enroll-comment'>
                {{ Enroll modify comment }}
            </div>
        </form>
    </div><!-- /createModifyDialog  -->

EOT;
		return $form;
	} // createModifyDialog



    //------------------------------------
    private function renderAddDialogCustomFields($mod = '')
    {
        if (!$this->customFields) {
            return '';
        }

        $out = '';
        $customFields = explodeTrim(',|', $this->customFields);
        $customFieldPlaceholders = explodeTrim(',', $this->customFieldPlaceholders);
        foreach ($customFields as $i => $field) {
            if (preg_match('/^\s* \( (.*) \) \s*$/x', $field, $m)) {
                $field = $m[1];
            }
            $id = translateToIdentifier($field).$mod.$this->hash;
            $placeholder = isset($customFieldPlaceholders[$i]) ? $customFieldPlaceholders[$i] : '';

            // special case: placeholder of pattern '[x,y...]' -> render select tag:
            if (preg_match('/\s*^\[(.*)\]\s*$/', $placeholder, $m)) {
                $options = explodeTrim('|', $m[1]);
                $s = '';
                foreach ($options as $option ) {
                    $val = $option? $option : '';
                    $option = (!$option) ? '': $option;
                    $s .= "\t\t\t\t\t\t<option value='$val' label='$option'>$option</option>\n";
                }
                $out .= <<<EOT

                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-enroll-field-$id" class="ui-hidden-accessible">$field:</label>
                    <select name="lzy-enroll-custom-$i" id="lzy-enroll-field-$id">
$s                    </select>
                </div>
EOT;

            } else {
                $out .= <<<EOT

                <div class="lzy-enroll-dialog-row">
                    <label for="lzy-enroll-field-$id" class="ui-hidden-accessible">$field:</label>
                    <input type="text" class='lzy-enroll-customfield' name="lzy-enroll-custom-$i" id="lzy-enroll-field-$id" value="" placeholder="$placeholder" data-theme="a" />
                </div>
EOT;
            }
        }

        return $out;
    } // renderAddDialogCustomFields



    //------------------------------------
    private function createErrorMsgs()
    {
        $errMsg = <<<EOT

<!-- Enrollment Dialog error messages: -->
<div class="dispno">
    <div class="lzy-enroll-name-required">{{ lzy-enroll-name-required }}:</div>
    <div class="lzy-enroll-email-required">{{ lzy-enroll-email-required }}:</div>
    <div class="lzy-enroll-email-invalid">{{ lzy-enroll-email-invalid }}:</div>
</div>


EOT;
        return $errMsg;
    } // createErrorMsgs



    //------------------------------------
    private function enrollLog($out)
    {
        $err = '';
        if ($this->err_msg) {
            $err = "\tError: {$this->err_msg}";
        }
        if ($this->logAgentData) {
            file_put_contents($this->logFile, timestamp() . "$out\t{$_SERVER["HTTP_USER_AGENT"]}\t{$_SERVER['REMOTE_ADDR']}$err\n", FILE_APPEND);
        } else {
            file_put_contents($this->logFile, timestamp() . "$out$err\n", FILE_APPEND);
        }
    }



    private function prepareLog()
    {
        if (!file_exists($this->logFile)) {
            $customFields = '';
            foreach ($this->customFieldsList as $item) {
                if (preg_match('/[\s,;]/', $item)) {
                    $customFields .= "; '$item'";
                } else {
                    $customFields .= "; $item";
                }
            }
            if ($this->logAgentData) {
                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields; Client; IP\n");
            } else {
                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields\n");
            }
        }
    }




    private function isInTime($lastModified)
    {
        if (!$this->editableTime || $this->admin_mode) {
            $inTime = true;
        } elseif ($this->editableTime < 0) {
            $inTime = (intval($lastModified) > time() + $this->editableTime);
        } else {
            $inTime = (time() < $this->editableTime);
        }
        return $inTime;
    }

} // class enroll

