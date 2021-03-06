<?php

require_once SYSTEM_PATH.'extensions/enroll/code/enrollment.class.php';

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/" .LOCALES_PATH. "vars.yaml"), false, true);

define('ENROLL_LOG_FILE', 'enroll.log.csv');
define('ENROLL_DATA_FILE', 'enroll.yaml');

$GLOBALS['enroll_form_created']['std'] = false;

$page->addModules('~ext/enroll/js/enroll.js, ~ext/enroll/css/enroll.css');

resetScheduleFile();

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $h = $this->getArg($macroName, 'nNeeded', 'Number of fields in category "needed"', 1);
    $this->getArg($macroName, 'nReserve', 'Number of fields in category "reserve" -> will be visualized differently', 0);
    $this->getArg($macroName, 'listname', 'Any word identifying the enrollment list', "Enrollment-List$inx");
    $this->getArg($macroName, 'header', 'Optional header describing the enrollment list', false);
//    $this->getArg($macroName, 'customFields', '[comma separated list] List of additional field labels (optional)', false);
//    $this->getArg($macroName, 'customFieldPlaceholders', "[comma separated list] List of placeholder used in custom-fields (optional).<br>Special case: \"[val1|val2|...]\" creates dropdown selection.", '');
    $this->getArg($macroName, 'file', 'The file in which to store enrollment data. Default: "&#126;page/enroll.yaml". ', false);
    $this->getArg($macroName, 'globalFile', 'The file to be used by all subsequent instances of enroll(). Default: false. ', false);
    $this->getArg($macroName, 'logAgentData', "[true,false] If true, logs visitor's IP and browser info (illegal if not announced to users)", false);
    $this->getArg($macroName, 'freezeTime', "[false, 0, seconds, ISO-datetime] Defines how long, resp. until when a user can delete/modify his/her entry. 'false' means forever, '0' means not modifiable at all. Duration is specified as a number of seconds. Deadline as an ISO datetime (default: 86400 = 1 day)", 86400);
//    $this->getArg($macroName, 'freezeTime', "[false, 0, -n, time-string] Defines how long, resp. until when a user can delete/modify his/her entry. 'false' means forever, '0' means not modifiable at all. Duration is specified as a negative number of seconds (default: -86400 = 1 day)", -86400);
    $this->getArg($macroName, 'editable', '[true|false] If true, users can modify their (custom field-) entries', false);
    $this->getArg($macroName, 'hideNames', "[true|false|initials] If true, names are not revealed, i.e. presented as '****'.", false);
    $this->getArg($macroName, 'unhideNamesForGroup', '[group] If set, names are shown to users of given group(s), to others they remain hidden.', false);
    $this->getArg($macroName, 'notify', 'Activates notification of designated persons, either upon user interactions or in regular intervals. See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', false);
    $this->getArg($macroName, 'notifyFrom', 'See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', '');
    $this->getArg($macroName, 'scheduleAgent', 'Specifies user code to assemble and send notfications. See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', 'scheduleAgent.php');
    $tooltip = $this->getArg($macroName, 'tooltips', 'Show content of custom elements in tooltips.', false);

    $this->getArg($macroName, 'n_needed', 'Synonym for "nNeeded"', false);
    $this->getArg($macroName, 'n_reserve', 'Synonym for "nReserve"', false);

    if ($h === 'help') {
        return '';
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($h === 'info') {
        $out = $this->translateVariable('lzy-enroll-info', true);
        $this->compileMd = true;
        return $out;
    }

    if ($tooltip) {
        $this->page->addModules( 'TOOLTIPSTER' );
        $this->page->addJq('$(\'.tooltipster\').tooltipster();');
    }

    $args = $this->getArgsArray($macroName);

    $enroll = new Enrollment($this->lzy, $inx, $args);
    $out = $enroll->render();
	return $out;
});




//=== class ====================================================================
//class Enrollment extends Forms
////class Enrollment
//{
//	public function __construct($inx, $trans, $args)
//	{
//	    $this->inx = $inx;
//	    $this->lzy = $trans->lzy;
//        $this->listname = $args['listname'];
//        $this->header = $args['header'];
//        $this->nNeeded = $args['nNeeded'];
//        if ($args['n_needed'] !== false) {
//            $this->nNeeded = $args['n_needed'];
//        }
//        $this->nReserve = $args['nReserve'];
//        if ($args['n_reserve'] !== false) {
//            $this->nReserve = $args['n_reserve'];
//        }
//        $this->customFields = $args['customFields'];
//        $this->customFieldPlaceholders = $args['customFieldPlaceholders'];
//
//        // determine where to store data:
//        if ($args['globalFile']) {
//            $GLOBALS['globalParams']['enrollFile'] = $args['globalFile'];
//        }
//        if ($args['file']) {
//            $this->file = $args['file'] ? $args['file'] : '~page/enroll.yaml';
//        } elseif (isset($GLOBALS['globalParams']['enrollFile']) && $GLOBALS['globalParams']['enrollFile']) {
//            $this->file = $GLOBALS['globalParams']['enrollFile'];
//        } else {
//            $this->file = '~page/enroll.yaml';
//        }
//
//        $this->logAgentData = $args['logAgentData'];
//        $this->freezeTime = $args['freezeTime'];
//        $this->editable = $args['editable'];
//        $this->hideNames = $args['hideNames'];
//        $this->unhideNamesForGroup = $args['unhideNamesForGroup'];
//
//        $this->notify = $args['notify'];
//        $this->notifyFrom = str_replace(['&#39;', '&#34;'], ["'", '"'], $args['notifyFrom']);
//        $this->scheduleAgent = $args['scheduleAgent'];
//
//		$this->admin_mode = false;
//		$this->trans = $trans;
//
//        // editable time: false=forever; 0=never; pos int=specfic date; neg int=duration after rec stored/modified:
//        if ($this->freezeTime && !preg_match('/^-?\d+$/', $this->freezeTime)) {
//            $this->freezeTime = strtotime('+1 day', strtotime($this->freezeTime)); // -> include given date
//        }
//
//        if ($this->admin_mode) {
//			$trans->addTerm('enroll_result_link', "<a href='?enroll_result'>{{ Show Enrollment Result }}</a>");
//		}
//
//		$this->err_msg = '';
//		$this->name = '';
//		$this->email = '';
//		$this->action = '';
//		$this->focus = '';
//		$this->show_result = false;
//
//        $this->enroll_list_id = base_name(translateToFilename($this->listname), false);
//        $this->enroll_list_name = str_replace("'", '&prime;', $this->listname);
//
//        $this->data_path = dir_name($this->file);
//		$this->dataFile = resolvePath( $this->file, true );
//		$this->logFile = resolvePath($this->data_path.ENROLL_LOG_FILE);
//
//        $this->customFieldsList = explodeTrim(',|', $this->customFields);
//        $this->customFieldsDisplayList = [];
//        foreach ($this->customFieldsList as $i => $item) {
//            if (preg_match('/^ \( (.*) \) $/x', $item, $m)) {
//                $this->customFieldsList[$i] = trim($m[1]);
//                $this->customFieldsDisplayList[$i] = false;
//            } else {
//                $this->customFieldsDisplayList[$i] = $item;
//            }
//        }
//        $this->hash = '';
//        if ($this->customFields) {
//            $this->hash = '-'.hash('crc32', $this->customFields . $this->customFieldPlaceholders);
//        }
//
//		preparePath($this->dataFile);
//        $this->prepareLog();
//        $this->handleClientData();
//        $this->setupScheduler();
//	} // __construct
//
//
//
//
//	//----------------------------------------------------------------------
//	private function handleClientData() {
//
//		if ($this->admin_mode && getUrlArg('enroll_result')) {
//			$this->show_result = true;
//			return;
//		}
//
//		if (isset($_POST) && $_POST) {
//			$action = get_post_data('lzy-enroll-type');
//			$id = trim(get_post_data('lzy-enroll-list-id'));
////			$this->listId = $id = trim(get_post_data('lzy-enroll-list-id'));
//			$name = get_post_data('lzy-enroll-name');
//			if (!$name || ($id !== $this->enroll_list_id)) {
//				return;
//			}
//			$admin_mode = '';
//			if ($this->admin_mode) {
//				$name = preg_replace('/\s*\(.*\)$/m', '', $name);
//				$admin_mode = 'admin ';
//			}
//			$email = strtolower(get_post_data('lzy-enroll-email'));
//
//			$sep = "; ";
//			$log = "$sep$admin_mode$action$sep{$this->enroll_list_id}$sep$name$sep$email";
//
//			$i = 0;
//			$customFieldValues = [];
//			while (isset($_POST["lzy-enroll-custom-$i"])) {
//			    $log .= "$sep".$_POST["lzy-enroll-custom-$i"];
//                $customFieldValues[$i] = $_POST["lzy-enroll-custom-$i"];
//			    $i++;
//            }
//
//            if (!is_legal_email_address($email) && !$this->admin_mode) {
//				writeLog("\tError: illegal email address [$name] [$email]");
//				$this->err_msg = '{{ illegal email address }}';
//				$this->name = $name;
//				$this->email = $email;
//				$this->focus = 'email';
//                $this->enrollLog($log);
//                return;
//			}
//			$_POST = [];
//
//            $ds = new DataStorage2(['dataFile' => $this->dataFile, 'lockDB' => true]);
//            $enrollData = $ds->read();
//			if (!isset($enrollData[$id])) {
//				$enrollData[$id] = array();
//			}
//			$existingData = $enrollData[$id];
//			if ($action === 'add') {
//				$this->action = 'add';
//                // check whether name already entered:
//                foreach ($existingData as $n => $rec) {
//                    if ($n === '_') {
//                        continue;
//                    }
//                    if ($rec['Name'] === $name) { // name already exists:
//                        if ($existingData[$n]['EMail'] === $email) {   // with same email, so we let it pass:
//                            $rec = &$existingData[$n];
//                            $rec['Name'] = $name;
//                            $rec['EMail'] = $email;
//                            $rec['time'] = time();
//                            foreach ($this->customFieldsList as $i => $field) {
//                                $rec[$field] = $customFieldValues[$i];
//                            }
//                            $found = true;
//                            break;
//                        } else {  // existing name but different email -> reject:
//                            writeLog("\tError: enrolling twice [$name] [$email]");
//                            $this->err_msg = '{{ enroll entry already exists }}';
//                            $this->name = $name;
//                            $this->email = $email;
//                            $this->focus = 'name';
//                            $this->enrollLog($log);
//                            return;
//                        }
//                    }
//                }
//                if (!isset($found)) {   // new entry:
//                    $i = sizeof($existingData) - 1; // -1 -> to take system field '_' into account
//                    if ($i >= ($this->nNeeded + $this->nReserve)) {
//                        return; // request probably tampered: attempt to add more records than allowed
//                    }
//                    $rec = &$existingData[ $i ];
//                    $rec['Name'] = $name;
//                    $rec['EMail'] = $email;
//                    $rec['time'] = time();
//                    foreach ($this->customFieldsList as $i => $field) {
//                        $rec[$field] = $customFieldValues[ $i ];
//                    }
//                }
//
//			} elseif ($action === 'delete') {
//				$this->action = 'delete';
//				$found = false;
//				$name = trim($name);
//
//				if (isset($enrollData[ $id ])) {
//				    $set = $enrollData[ $id ];
//                } else {
//				    return;
//                }
//
//				// if in 'initials' mode, try to find data rec based on email, check against initials of name:
//                if (strpos($this->hideNames, 'init') === 0) {
//                    $found = false;
//                    foreach ($set as $rec) {
//                        $initials = $this->getInitials($rec['Name']);
//                        if (($name === $initials) && ($email === $rec['EMail'])) {
//                            $name = $rec['Name'];
//                            $found = true;
//                            break;
//                        }
//                    }
//                    if (!$found) {
//                        writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
//                        return;
//                    }
//                }
//
//
//				foreach ($existingData as $n => $rec) {
//                    if ($n === '_') {
//                        continue;
//                    }
//                    if ($rec['Name'] === $name) {
//						if ($this->admin_mode) {       	// admin-mode needs no email and has no timeout
//							$found = true;
//							unset($existingData[$n]);
//							break;
//						}
//                        if ($this->isInTime($rec['time'])) {
//							if ($rec['EMail'] !== $email) {
//								writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
//								$this->err_msg = '{{ enroll email wrong }}';
//								$this->name = $name;
//								$this->email = $email;
//								$this->focus = 'email';
//							} else {
//                                unset($existingData[$n]);
//								$found = true;
//								continue;
//							}
//						} else{
//							writeLog("\tError: enroll entry too old [$name] [$email]");
//							$this->err_msg = '{{ enroll entry too old }}';
//							$this->name = $name;
//							$this->email = $email;
//						}
//						$found = true;
//					}
//				}
//				if (!$found) {
//					writeLog("\tError: no enroll entry found [$name] [$email]");
//					$this->err_msg = '{{ no enroll entry found }}';
//					$this->name = $name;
//					$this->email = $email;
//				}
//			} else {
//				return; // nothing to do
//			}
//
//			// update data:
//			$nRequired = $existingData['_'];
//			unset($existingData['_']);
//			$enrollData[$id] = array_values($existingData);
//			$enrollData[$id]['_'] = $nRequired;
//
//			if ($this->notify) {
//			    $this->sendNotification($enrollData);
//            }
//
//            $ds->write($enrollData);
//            $this->enrollLog($log);
//		}
//	} // handle $_POST
//
//
//
//
//	//-----------------------------------------------------------------------------------------------
//	public function render()
//    {
//        $this->createDialogs();
//
//        $ds = new DataStorage2($this->dataFile);
//        $enrollData = $ds->read();
//
//        if (!($enrollData)) {
//			$enrollData[$this->enroll_list_id] = array();
//		}
//        $title = $this->header ? $this->header : $this->listname;
//        if (!isset($enrollData[$this->enroll_list_id]['_'])) {
//			$enrollData[$this->enroll_list_id]['_'] = "{$this->nNeeded} => $title";
//            $ds->write($enrollData);
//		}
//        unset($enrollData[$this->enroll_list_id]['_']);
//        $existingData = [];
//        if (isset($enrollData[$this->enroll_list_id])) {
//            $existingData = $enrollData[$this->enroll_list_id];
//        }
//
//
//        $out = '';
//
//		$nn = $this->nNeeded + $this->nReserve;
//		$new_field_done = false;
//		for ($n=0; $n < $nn; $n++) {			// loop over list
//			$res = ($n >= $this->nNeeded) ? ' lzy-enroll-reserve-field': '';
//			$num = "<span class='lzy-num'>".($n+1).":</span>";
//
//            if (isset($existingData[$n]['Name'])) {	// Name exists -> delete
//				$name =  $existingData[$n]['Name'];
//				$email = $existingData[$n]['EMail'];
//				if ($this->admin_mode) {
//					$name .= " &lt;$email>";
//				}
//				$name = $this->hideName( $name );
//
//				if ($this->customFields && $this->editable) {
//                    $targId = "#modifyDialog{$this->hash}";
//                    $tooltip = '{{ lzy-enroll-modify-entry }}';
//                    $icon = "<span class='lzy-enroll-modify'>&#9998;</span>";
//                } else {
//                    $targId = "#delDialog{$this->hash}";
//                    $tooltip = '{{ lzy-enroll-delete-entry }}';
//                    $icon = "<span class='lzy-enroll-del'>−</span>";
//                }
//
//				if ($this->isInTime($existingData[$n]['time'])) {
//					$a = "<a href='$targId' title='$tooltip'>\n\t\t\t\t  <span class='lzy-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
//                    $class = 'lzy-enroll-del_field';
//				} else {
//					$a = "<span class='lzy-name'>$name</span>";
//                    $class = 'lzy-enroll-frozen_field';
//				}
//
//			} else {			// add
//				if (!$new_field_done) {
//					$name = '{{ Enroll me }}';
//					$icon = "<span class='lzy-enroll-add'>+</span>";
//					$a = "<a href='#lzy-enroll-add-dialog{$this->hash}' title='{{ lzy-enroll-new-name }}' data-rel='popup' data-position-to='window' data-transition='pop'>\n\t\t\t\t  <span class='lzy-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
//					$new_field_done = true;
//					$class = 'lzy-enroll-add-field';
//
//				} else {		// free cell
//					$class = 'lzy-enroll-empty-field';
//					$a = "<span class='lzy-name'>&nbsp;</span>\n";
//				}
//			}
//
//			$rowContent = "\t\t\t<div class='lzy-enroll-field $class'>\n\t\t\t\t$num\n\t\t\t\t$a\n\t\t\t</div><!-- /$class -->";
//
//            // assemble auxiliary fields:
//            $aux = '';
//            $hdr = '';
//            foreach ($this->customFieldsDisplayList as $i => $custField) {
//                if (!$custField) {
//                    continue;
//                }
//                $val = isset($existingData[$n][$custField]) && $existingData[$n][$custField] ? $existingData[$n][$custField] : '&nbsp;';
//                $aux .= "\n\t\t\t<div class='lzy-enroll-aux-field' data-class='lzy-enroll-aux-field$i'>\n\t\t\t\t$val\n\t\t\t</div>";
//                $hdr .= "\n\t\t\t<div class='lzy-enroll-aux-field'>$custField</div>";
//            }
//            $out .= "\t\t<div class='lzy-enroll-row$res'>\n$rowContent$aux\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
//		}
//
//
//		// assemble output:
//        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list' data-dialog-title='$this->enroll_list_name' data-dialog-id='{$this->enroll_list_id}' data-dialog-inx='{$this->inx}'>\n";
//		if ($this->header) {
//            $out0 .= "\n\t  <div class='lzy-enroll-field lzy-enroll-header'>{$this->header}</div>\n";
//        }
//        if ($hdr) {
//            $hdr = "\n\t\t<div class='lzy-enroll-row lzy-enroll-hdr'>\n\t\t\t<div class='lzy-enroll-field'>{{ lzy-enroll-hdr-name }}</div>$hdr\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
//            $out0 .= $hdr;
//        }
//        $out0 .= $out;
//        $out0 .= "\t</div> <!-- /lzy-enrollment-list -->\n  ";
//        if ($this->err_msg) {
//		    $this->trans->page->addMessage($this->err_msg);
//        }
//
//		return $out0;
//	} // render
//
//
//
//
//
//    //------------------------------------
//    private function createDialogs()
//    {
//        require_once SYSTEM_PATH.'forms.class.php';
//
//        if ($this->customFields) {
//            if (isset($GLOBALS['enroll_form_created'][$this->hash])) {
//                return;
//            }
//            $dialogs = "\n\n<!-- === Enrollment Custom Dialogs ===================== -->\n";
////            $dialogs .= "<div id='lzy-enroll-popup-bg-{$this->inx}' class='lzy-popup-bg' style='display: none;'>\n";
////            $dialogs .= "<div id='lzy-enroll-popup-bg-{$this->inx}' class='lzy-enroll-popup-bg lzy-enroll-hide-dialog'>\n";
//            $dialogs .= $this->createAddDialog();
////            $dialogs .= $this->createCustomAddDialog();
//            $dialogs .= $this->createDelDialog();
////            $dialogs .= $this->createCustomDelDialog();
////            if ($this->editable) {
////                $dialogs .= $this->createModifyDialog();
////            }
//
////            $dialogs .= "</div><!-- /Enrollment Custom Dialogs -->\n\n\n";
//
//            $dialogs .= $this->createErrorMsgs();
//            $this->trans->page->addBodyEndInjections($dialogs);
//            $GLOBALS['enroll_form_created'][$this->hash] = true;
//
//        } elseif (!$GLOBALS['enroll_form_created']['std']) {
//            $dialogs = "\n\n<!-- === Enrollment Standard Dialogs ====================== -->\n";
////            $dialogs .= "<div id='lzy-enroll-popup-bg' class='lzy-enroll-popup-bg lzy-enroll-hide-dialog'>\n";
////            $dialogs .= "<div class='Xlzy-popup-bg'>\n";
//            $dialogs .= $this->createAddDialog( $this->hash );
////            $dialogs .= $this->createStdAddDialog();
////            $dialogs .= $this->createStdDelDialog();
////            $dialogs .= "</div><!-- /Enrollment Standard Dialogs -->\n\n\n";
////            $dialogs .= "</div><!-- /Enrollment Standard Dialogs -->\n\n\n";
//
//            $dialogs .= $this->createErrorMsgs();
//            $this->trans->page->addBodyEndInjections($dialogs);
//
//            $GLOBALS['enroll_form_created']['std'] = true;
//        }
//    } // createDialogs
//
//
//
//    //------------------------------------
//	private function createAddDialog( $hash = false ) {
////	private function createAddDialog( $customFields = false ) {
////	private function createStdAddDialog() {
////		$reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
////		$url = $reqScheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
//        $options = [
//            'file' => '~data/test.yaml',
//            'translateLabels' => true,
//            'customResponseEvaluation' => 'enrollHandleClientData',
//            'lzy-enroll-type' => [ 'type' => 'hidden', 'value' => 'add'],
//            'lzy-enroll-name' => [
//                'type' =>'string',
//                'required' => true,
//                'placeholder' => '{{ placeholder name }}',
//            ],
//            'lzy-enroll-email' => [
//                'type' =>'email',
//                'required' => true,
//                'placeholder' => 'name@domain.net',
//            ],
//        ];
//        if ($this->customFields) {
//            $customFields = explodeTrim(',|', $this->customFields);
//            foreach ($customFields as $label) {
//                $options[ $label ] = [ 'type' =>'string', ];
//            }
//        }
//        $options['cancel'] = ['label' => 'lzy-edit-form-cancel'];
//        $options['submit'] = ['label' => 'lzy-edit-form-submit'];
//
//		$frm = new Forms( $this->lzy );
//        $form = $frm->renderForm( $options );
//        $hash = $hash ? "-$hash" : '';
//		$form = <<<EOT
//
//
//
//    <!-- === Enroll Add Dialog =================== -->
//    <div id="lzy-enroll-add-dialog$hash" class="lzy-enroll-dialog lzy-enroll-add-dialog" style='display:none;'>
//      <div>
//$form
//          <div class="lzy-enroll-comment">{{ lzy-enroll-add-comment }}</div>
//          <div class="lzy-enroll-title" style="display: none">{{ lzy-enroll-add-title }}</div>
//      </div>
//    </div><!-- /lzy-enroll-add-dialog -->
//
//EOT;
//
////		$form = <<<EOT
////
////    <!-- === Standard Add Dialog =================== -->
////    <div id="lzy-enroll-add-dialog" class="XXlzy-enrollment-dialog Xlzy-popup-wrapper" style='display:none;'>
////      <div>
////<!--    <div id="addDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog" style="display:none;">-->
////<!--        <div class='lzy-enroll-dialog-close'>⊗</div>-->
////        <form id="lzy-enroll-add-form" method='post' action='$url'>
////            <input type="hidden" id='lzy-enroll-add-list-id' class='lzy-enroll-list-id'  name='lzy-enroll-list-id' value='' />
////            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
////            <div>
////                <div class="lzy-enroll-title" style="display: none">{{ lzy-enroll-add-title }}</div>
////<!--                <h3>{{ lzy-enroll-add-title }}</h3>-->
////                <div class="lzy-enroll-dialog-row">
////                    <label for="lzy-add-name" class="ui-hidden-accessible">Name:</label>
////                    <input type="text" id="lzy-add-name" class="lzy-enroll-name" name="lzy-enroll-name" value="" placeholder="{{ placeholder name }}" data-theme="a" required aria-required="true"  />
////                </div>
////
////                <div class="lzy-enroll-dialog-row">
////                    <label for="lzy-add-email" class="ui-hidden-accessible">E-Mail:</label>
////                    <input type="email" id="lzy-add-email" class="lzy-enroll-email" name="lzy-enroll-email" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />
////                </div>
////
////                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
////                    <button type="submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
////                    <button type="reset" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
////                </div>
////            </div>
////            <div class='lzy-enroll-comment'>
////                {{ Enroll add comment }}
////            </div>
////        </form>
////      </div>
////    </div><!-- /lzy-enroll-add-dialog -->
////
////EOT;
//
//		return $form;
//	} // createStdAddDialog
//
//
//
//    //------------------------------------
//	private function createCustomAddDialog() {
//        $customFields = $this->renderAddDialogCustomFields();
//
//		$reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
//		$url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
//		$form = <<<EOT
//
//    <!-- === Custom Add Dialog =================== -->
//    <div id="lzy-enroll-add-dialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
//        <div class='lzy-enroll-dialog-close'>⊗</div>
//        <form id="lzy-enroll-add-form{$this->hash}" method='post' action='$url'>
//            <input type="hidden" name='lzy-enroll-list-id' class='lzy-enroll-list-id' value='' />
//            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
//            <div>
//                <h3>{{ lzy-enroll-add-title }}</h3>
//                <div class="lzy-enroll-dialog-row">
//                    <label for="lzy-add-name{$this->hash}" class="ui-hidden-accessible">Name:</label>
//                    <input type="text" class="lzy-enroll-name" name="lzy-enroll-name" id="lzy-add-name{$this->hash}" value="" placeholder="{{ placeholder name }}" data-theme="a" required aria-required="true"/>
//                </div>
//
//                <div class="lzy-enroll-dialog-row">
//                    <label for="lzy-add-email{$this->hash}" class="ui-hidden-accessible">E-Mail:</label>
//                    <input type="email" class="lzy-enroll-email" name="lzy-enroll-email" id="lzy-add-email{$this->hash}" value="" placeholder="name@domain.net" data-theme="a" required aria-required="true" />
//                </div>
//
//$customFields
//                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
//                    <button type="submit" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Enroll now }}</button>
//                    <button type="reset" class="ui-btn ui-corner-all ui-shadow ui-btn-b ui-btn-icon-left ui-icon-check">{{ Cancel }}</button>
//                </div>
//            </div>
//        <div class='lzy-enroll-comment'>
//            {{ Enroll add comment }}{{^ lzy-enroll-add-comment-{$this->inx} }}
//        </div>
//       </form>
//    </div><!-- /Custom Add Dialog -->
//
//EOT;
//
//		return $form;
//	} // createCustomAddDialog
//
//
//
//	//------------------------------------
//	private function createDelDialog( $hash = false) {
////        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
////        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
////		$form = <<<EOT
////
////    <!-- === Standard Delete Dialog =================== -->
////    <div id="delDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
////        <div class='lzy-enroll-dialog-close'>⊗</div>
////        <form id="lzy-enroll-del-form" action='$url' method='post' >
////            <input type="hidden" class='lzy-enroll-list-id' name='lzy-enroll-list-id' value='' />
////            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='delete' />
////            <div>
////                <h3>{{ Enroll delete }}</h3>
////
////                <div class="lzy-enroll-dialog-row">
////                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
////                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'>?</div><br />
////                </div>
////
////                <div class="lzy-enroll-dialog-row">
////                    <label class='ui-hidden-accessible lzy-name' for='lzy-del-email'>E-Mail:</label>
////                    <input type="email" id='lzy-del-email' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
////                </div>
////
////                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
////                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll delete now }}</button>
////                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
////                </div>
////            </div>
////            <div class='lzy-enroll-comment'>
////                {{ Enroll delete comment }}
////            </div>
////        </form>
////    </div><!-- /delDialog  -->
////
////EOT;
//
//        $options = [
//            'file' => '~data/test.yaml',
//            'translateLabels' => true,
//            'lzy-enroll-type' => [ 'type' => 'hidden', 'value' => 'delete'],
//            'lzy-enroll-name' => [
//                'type' =>'readonly',
//            ],
//            'lzy-enroll-email' => [
//                'type' =>'email',
//                'required' => true,
//                'placeholder' => 'name@domain.net',
//            ],
//        ];
////        if ($this->customFields) {
////            $customFields = explodeTrim(',|', $this->customFields);
////            foreach ($customFields as $label) {
////                $options[ $label ] = [ 'type' =>'string', ];
////            }
////        }
//        $options['cancel'] = ['label' => 'lzy-edit-form-cancel'];
//        $options['submit'] = ['label' => 'lzy-enroll-delete-label'];
//
////        require_once SYSTEM_PATH.'forms.class.php';
//		$frm = new Forms( $this->lzy );
//        $form = $frm->renderForm( $options );
//        $hash = $hash ? "-$hash" : '';
//		$form = <<<EOT
//
//
//
//    <!-- === Enroll Delete Dialog =================== -->
//    <div id="lzy-enroll-del-dialog$hash" class="lzy-enroll-dialog lzy-enroll-del-dialog" style='display:none;'>
//      <div>
//$form
//          <div class="lzy-enroll-comment">{{ lzy-enroll-del-comment }}</div>
//          <div class="lzy-enroll-title" style="display: none">{{ lzy-enroll-del-title }}</div>
//      </div>
//    </div><!-- /lzy-enroll-del-dialog -->
//
//EOT;
//
//
//		return $form;
//	} // createStdDelDialog
//
////	private function createStdDelDialog() {
////        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
////        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
////		$form = <<<EOT
////
////    <!-- === Standard Delete Dialog =================== -->
////    <div id="delDialog" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
////        <div class='lzy-enroll-dialog-close'>⊗</div>
////        <form id="lzy-enroll-del-form" action='$url' method='post' >
////            <input type="hidden" class='lzy-enroll-list-id' name='lzy-enroll-list-id' value='' />
////            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='delete' />
////            <div>
////                <h3>{{ Enroll delete }}</h3>
////
////                <div class="lzy-enroll-dialog-row">
////                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
////                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'>?</div><br />
////                </div>
////
////                <div class="lzy-enroll-dialog-row">
////                    <label class='ui-hidden-accessible lzy-name' for='lzy-del-email'>E-Mail:</label>
////                    <input type="email" id='lzy-del-email' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
////                </div>
////
////                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
////                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll delete now }}</button>
////                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
////                </div>
////            </div>
////            <div class='lzy-enroll-comment'>
////                {{ Enroll delete comment }}
////            </div>
////        </form>
////    </div><!-- /delDialog  -->
////
////EOT;
////		return $form;
////	} // createStdDelDialog
//
//
//
//
//	//------------------------------------
//	private function createCustomDelDialog() {
//        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
//        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
//		$form = <<<EOT
//
//    <!-- === Custom Delete Dialog =================== -->
//    <div id="delDialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
//        <div class='lzy-enroll-dialog-close'>⊗</div>
//        <form id="lzy-enroll-del-form{$this->hash}" action='$url' method='post' >
//            <input type="hidden" class='lzy-enroll-list-id' name='lzy-enroll-list-id' value='' />
//            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='delete' />
//            <div>
//                <h3>{{ Enroll delete }}</h3>
//
//                <div class="lzy-enroll-dialog-row">
//                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
//                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'></div><br />
//                </div>
//
//                <div class="lzy-enroll-dialog-row">
//                    <label class='ui-hidden-accessible lzy-name' for='lzy-del-email{$this->hash}'>E-Mail:</label>
//                    <input type="email" id='lzy-del-email{$this->hash}' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
//                </div>
//
//                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
//                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll delete now }}</button>
//                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
//                </div>
//            </div>
//            <div class='lzy-enroll-comment'>
//                {{ Enroll delete comment }}{{^ lzy-enroll-delete-comment-{$this->inx} }}
//            </div>
//        </form>
//    </div><!-- /delDialog  -->
//
//EOT;
//		return $form;
//	} // createCustomDelDialog
//
//
//
//
//	//------------------------------------
//	private function createModifyDialog() {
//        $reqScheme = (isset($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
//        $url = $reqScheme.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
//        $customFields = $this->renderAddDialogCustomFields('-modify');
//
//        $form = <<<EOT
//
//    <!-- === Custom Modify Dialog =================== -->
//    <div id="modifyDialog{$this->hash}" data-role="popup" data-theme="a" class="lzy-enrollment-dialog lzy-enroll-hide-dialog">
//        <div class='lzy-enroll-dialog-close'>⊗</div>
//        <form id="lzy-enroll-modify-form{$this->hash}" action='$url' method='post' >
//            <input type="hidden" class='lzy-enroll-list-id' name='lzy-enroll-list-id' value='' />
//            <input type="hidden" class='lzy-enroll-type' name='lzy-enroll-type' value='add' />
//            <div>
//                <h3>{{ lzy-enroll-delete-or-modify-title }}</h3>
//
//                <div class="lzy-enroll-dialog-row">
//                    <input type="hidden" class='lzy-enroll-name' name='lzy-enroll-name' value='unknown' />
//                    <div class='lzy-name'>{{ Enroll delete for }}:</div><div class='lzy-enroll-name-text'></div><br />
//                </div>
//
//                <div class="lzy-enroll-dialog-row">
//                    <label class='ui-hidden-accessible lzy-name' for='lzy-modify-email{$this->hash}'>E-Mail:</label>
//                    <input type="email" id='lzy-modify-email{$this->hash}' class='lzy-enroll-email' name='lzy-enroll-email' placeholder="name@domain.net" data-theme="a" required aria-required="true" />
//                </div>
//
//$customFields
//                <div class="lzy-enroll-dialog-row lzy-enroll-button-row">
//                    <button type="submit" class="lzy-enroll-btn lzy-enroll-submit-btn">{{ Enroll modify save }}</button>
//                    <button type="reset" class="lzy-enroll-btn lzy-enroll-cancel-btn">{{ Cancel }}</button>
//                </div>
//                <div class="lzy-enroll-or">{{ lzy-enroll-or }}</div>
//                <div class="lzy-enroll-dialog-row lzy-enroll-button-row lzy-enroll-dialog-del-row">
//                    <button type="submit" class="lzy-enroll-btn lzy-enroll-delete-entry">{{ lzy-enroll-delete-btn }}</button>
//                </div>
//            </div>
//            <div class='lzy-enroll-comment'>
//                {{ Enroll modify comment }}{{^ lzy-enroll-modify-comment-{$this->inx} }}
//            </div>
//        </form>
//    </div><!-- /createModifyDialog  -->
//
//EOT;
//		return $form;
//	} // createModifyDialog
//
//
//
//    //------------------------------------
////    private function renderAddDialogCustomFields($mod = '')
////    {
////        if (!$this->customFields) {
////            return '';
////        }
////
////        $out = '';
////        $customFields = explodeTrim(',|', $this->customFields);
////        $customFieldPlaceholders = explodeTrim(',', $this->customFieldPlaceholders);
////        foreach ($customFields as $i => $field) {
////            // omit hidden fields in modify dialog:
////            if ($mod && !$this->customFieldsDisplayList[$i]) {
////                continue;
////            }
////            if (preg_match('/^\s* \( (.*) \) \s*$/x', $field, $m)) {
////                $field = $m[1];
////            }
////            $id = translateToIdentifier($field).$mod.$this->hash;
////            $class = "lzy-enroll-aux-field$i";
////            $placeholder = isset($customFieldPlaceholders[$i]) ? $customFieldPlaceholders[$i] : '';
////
////            // special case: placeholder of pattern '[x,y...]' -> render select tag:
////            if (preg_match('/\s*^\[(.*)\]\s*$/', $placeholder, $m)) {
////                $options = explodeTrim('|', $m[1]);
////                $s = '';
////                foreach ($options as $option ) {
////                    $val = $option? $option : '';
////                    $option = (!$option) ? '': $option;
////                    $s .= "\t\t\t\t\t\t<option value='$val' label='$option'>$option</option>\n";
////                }
////                $out .= <<<EOT
////
////                <div class="lzy-enroll-dialog-row">
////                    <label for="lzy-enroll-field-$id" class="ui-hidden-accessible">$field:</label>
////                    <select name="lzy-enroll-custom-$i" id="lzy-enroll-field-$id" class="lzy-enroll-customfield $class">
////$s                    </select>
////                </div>
////EOT;
////
////            } else {
////                $out .= <<<EOT
////
////                <div class="lzy-enroll-dialog-row">
////                    <label for="lzy-enroll-field-$id" class="ui-hidden-accessible">$field:</label>
////                    <input type="text" class='lzy-enroll-customfield $class' name="lzy-enroll-custom-$i" id="lzy-enroll-field-$id" value="" placeholder="$placeholder" data-theme="a" />
////                </div>
////EOT;
////            }
////        }
////
////        return $out;
////    } // renderAddDialogCustomFields
//
//
//
//
//    //------------------------------------
//    private function sendNotification($enrollData)
//    {
//        $notifies = explodeTrim(',', $this->notify);
//        foreach ($notifies as $notify) {
//            if (preg_match('/\( (.*) \) (.*)/x', $notify, $m)) {
//                $time = trim($m[1]);
//                $to = trim($m[2]);
//                if (preg_match('/(.*) :: (.*)/x', $time, $mm)) {
//                    $time = $mm[1];
//                    $freq = $mm[2];
//                    $this->setupScheduler($time, $freq, $to);
//                    continue;
//                }
//                list($from, $till) = $this->deriveFromTill($time);
//                $now = time();
//                if (($now > $from) && ($now < $till)) {
//                    $this->_sendNotification($enrollData, $to);
//                }
//            } else {
//                $this->_sendNotification($enrollData, $notify);
//            }
//        }
//    } // sendNotification
//
//
//
//    //------------------------------------
//    private function _sendNotification($enrollData, $to)
//    {
//        $msg = "{{ lzy-enroll-notify-text-1 }}\n";
//
//        foreach ($enrollData as $listName => $list) {
//            $n = sizeof($list) - 1;
//            list($nRequired, $title) = isset($list['_']) ? explode('=>', $list['_']) : ['?', ''];
//            $listName = $title ? $title : $listName;
//            $listName = str_pad("$listName: ", 24, '.');
//            $msg .= "$listName $n {{ of }} $nRequired\n";
//        }
//        $msg .= "{{ lzy-enroll-notify-text-2 }}\n";
//        $msg = $this->trans->translate($msg);
//        $msg = str_replace('\\n', "\n", $msg);
//        $subject = $this->trans->translate('{{ lzy-enroll-notify-subject }}');
//
//        require_once SYSTEM_PATH.'messenger.class.php';
//
//        $mess = new Messenger($this->notifyFrom, $this->trans->lzy);
//        $mess->send($to, $msg, $subject);
//    } // _sendNotification
//
//
//
//
//    //------------------------------------
//    private function setupScheduler()
//    {
//        if (!$this->notify) {
//            return;
//        }
//
//        $newSchedule = [];
//        $notifies = explode(',', $this->notify);
//        foreach ($notifies as $notify) {
//            if (preg_match('/\( (.*) \) (.*)/x', $notify, $m)) {
//                $time = trim($m[1]);
//                $to = trim($m[2]);
//                if (preg_match('/(.*) :: (.*)/x', $time, $mm)) {
//                    list($from, $till) = $this->deriveFromTill(trim($mm[1]));
//                    $freq = trim(strtolower($mm[2]));
//                    if ($freq === 'daily') {
//                        $time = '****-**-** 08:00';
//                    } elseif ($freq === 'weekly') {
//                        $time = 'Mo 08:00';
//                    } else {
//                        die("Error: enroll() -> time pattern not recognized: '$freq'");
//                    }
//                    $newSchedule[] = [
//                        'src' => $GLOBALS['globalParams']['pathToPage'],
//                        'time' => $time,
//                        'from' => date('Y-m-d H:i', $from),
//                        'till' => date('Y-m-d H:i', $till),
//                        'loadLizzy' => true,
//                        'do' => $this->scheduleAgent,
//                        'args' => [
//                            'to' => $to,
//                            'from' => $this->notifyFrom,
//                            'dataFile' => $this->dataFile,
//                        ]
//                    ];
//                }
//            }
//        }
//
//        $file = SCHEDULE_FILE;
//        $schedule = getYamlFile($file);
//
//        // clean up existing schedule entries:
//        $now = time();
//        foreach ($schedule as $i => $rec) {
//            $from = strtotime($rec['from']);
//            $till = strtotime($rec['till']);
//            if (($now < $from) || ($now > $till)) {
//                unset($schedule[$i]);
//            }
//        }
//
//        $schedule = array_merge($schedule, $newSchedule);
//        writeToYamlFile($file, $schedule);
//    } // setupScheduler
//
//
//
//
//    //------------------------------------
//    private function createErrorMsgs()
//    {
//        $errMsg = <<<EOT
//
//<!-- Enrollment Dialog error messages: -->
//<div class="dispno">
//    <div class="lzy-enroll-name-required">{{ lzy-enroll-name-required }}:</div>
//    <div class="lzy-enroll-email-required">{{ lzy-enroll-email-required }}:</div>
//    <div class="lzy-enroll-email-invalid">{{ lzy-enroll-email-invalid }}:</div>
//</div>
//
//
//EOT;
//        return $errMsg;
//    } // createErrorMsgs
//
//
//
//    //------------------------------------
//    private function enrollLog($out)
//    {
//        $err = '';
//        if ($this->err_msg) {
//            $err = "\tError: {$this->err_msg}";
//        }
//        if ($this->logAgentData) {
//            file_put_contents($this->logFile, timestamp() . "$out\t{$_SERVER["HTTP_USER_AGENT"]}\t{$_SERVER['REMOTE_ADDR']}$err\n", FILE_APPEND);
//        } else {
//            file_put_contents($this->logFile, timestamp() . "$out$err\n", FILE_APPEND);
//        }
//    }
//
//
//
//    //------------------------------------
//    private function prepareLog()
//    {
//        if (!file_exists($this->logFile)) {
//            $customFields = '';
//            foreach ($this->customFieldsList as $item) {
//                if (preg_match('/[\s,;]/', $item)) {
//                    $customFields .= "; '$item'";
//                } else {
//                    $customFields .= "; $item";
//                }
//            }
//            if ($this->logAgentData) {
//                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields; Client; IP\n");
//            } else {
//                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields\n");
//            }
//        }
//    }
//
//
//
//    private function hideName($name)
//    {
//        $hide = false;
//        if ($this->hideNames && !$this->trans->lzy->auth->isAdmin()) {
//            if ($this->unhideNamesForGroup) {
//                if ($this->trans->lzy->auth->checkGroupMembership($this->unhideNamesForGroup)) {
//                    $hide = false;
//                } else {
//                    $hide = true;
//                }
//            } else {
//                $hide = true;
//            }
//        }
//        if ($hide) {
//            if (strpos($this->hideNames, 'init') === 0) {
//                $name = $this->getInitials($name);
//            } else {
//                $name = '****';
//                $this->freezeTime = 0;
//            }
//        } elseif ($this->hideNames && $this->trans->lzy->auth->isAdmin()) {
//            $name = "<span class='lzy-enroll-admin-only' title='Visible to admins only'>$name</span>";
//        }
//
//        return $name;
//    } // hideName
//
//
//
//
//    private function isInTime($lastModified)
//    {
//        if (($this->freezeTime === false) || $this->admin_mode) {
//            $inTime = true;
//        } else {
//            $this->freezeTime = intval($this->freezeTime);
//            if ($this->freezeTime < 0) {
//                $inTime = (intval($lastModified) > time() + $this->freezeTime);
//            } else {
//                $inTime = (time() < $this->freezeTime);
//            }
//        }
//        return $inTime;
//    }
//
//
//
//    private function deriveFromTill($time)
//    {
//        $from = 0;
//        $till = time() + 1000;
//
//        if (preg_match('/^ (\d\d\d\d-\d\d-\d\d) (.*) /x', $time, $m)) {
//            $from = strtotime(trim($m[1]));
//            $time = $m[2];
//        }
//        if (preg_match('/- \s*(\d\d\d\d-\d\d-\d\d) /x', $time, $m)) {
//            $till = strtotime('+1 day', strtotime(trim($m[1])));
//        }
//        return array($from, $till);
//    } // deriveFromTill
//
//
//
//
//    private function getInitials($name): string
//    {
//        $parts = explode(' ', $name);
//        $name = strtoupper($parts[0][0]);
//        if (sizeof($parts) > 1) {
//            $name .= strtoupper($parts[sizeof($parts) - 1][0]);
//        }
//        return $name;
//    } // getInitials
//
//} // class enroll
//
//


function resetScheduleFile()
{
    $thisSrc = $GLOBALS['globalParams']['pathToPage'];
    $file = SCHEDULE_FILE;
    $schedule = getYamlFile($file);
    $modified = false;
    foreach ($schedule as $i => $rec) {
        $src = $rec['src'];
        if ($src === $thisSrc) {
            unset($schedule[$i]);
            $modified = true;
        }
    }
    if ($modified) {
        $schedule = array_values($schedule);
        writeToYamlFile($file, $schedule);
    }
}



//function enrollHandleClientData()
//{
//    return false;
//}