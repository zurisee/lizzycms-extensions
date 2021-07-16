<?php
//define('ENROLLMENT_SPECIFIC_ELEMENTS', ',nNeeded,nReserve,listname,header,customFields,customFieldPlaceholders,'.
define('ENROLLMENT_SPECIFIC_ELEMENTS', ',nNeeded,nReserve,listname,header,tooltips,'.
    'file,globalFile,logAgentData,freezeTime,editable,hideNames,unhideNamesForGroup,notify,notifyFrom,'.
    'scheduleAgent,n_needed,n_reserve,');

require_once SYSTEM_PATH.'forms.class.php';
$GLOBALS['globalParams']['enrollCnt'] = 0;

class Enrollment extends Forms
{
    public function __construct($lzy, $inx, $args)
    {
        $GLOBALS['globalParams']['enrollCnt']++;
        $this->enrollInx = $GLOBALS['globalParams']['enrollCnt'];
        $this->inx = $inx;
        $this->lzy = $lzy;
        $this->args = $args;
        $this->listname = $args['listname'];
        $this->header = $args['header'];
        $this->nNeeded = $args['nNeeded'];
        if ($args['n_needed'] !== false) {
            $this->nNeeded = $args['n_needed'];
        }
        $this->nReserve = $args['nReserve'];
        if ($args['n_reserve'] !== false) {
            $this->nReserve = $args['n_reserve'];
        }
//        $this->customFields = $args['customFields'];
//        $this->customFieldPlaceholders = $args['customFieldPlaceholders'];

        // determine where to store data:
        if ($args['globalFile']) {
            $GLOBALS['globalParams']['enrollFile'] = $args['globalFile'];
        }
        if ($args['file']) {
            $this->file = $args['file'] ? $args['file'] : '~page/enroll.yaml';
        } elseif (isset($GLOBALS['globalParams']['enrollFile']) && $GLOBALS['globalParams']['enrollFile']) {
            $this->file = $GLOBALS['globalParams']['enrollFile'];
        } else {
            $this->file = '~page/enroll.yaml';
        }

        $this->logAgentData = $args['logAgentData'];
        $this->freezeTime = $args['freezeTime'];
        $this->editable = $args['editable'];
        $this->hideNames = $args['hideNames'];
        $this->unhideNamesForGroup = $args['unhideNamesForGroup'];

        $this->notify = $args['notify'];
        $this->notifyFrom = str_replace(['&#39;', '&#34;'], ["'", '"'], $args['notifyFrom']);
        $this->scheduleAgent = $args['scheduleAgent'];
        $this->tooltips = $args['tooltips'];

        $this->admin_mode = false;
        $this->trans = $this->lzy->trans;

        // editable time: false=forever; 0=never; string=specfic date; int=duration after rec stored/modified:
        $this->freezeTimeStr = '';
        if ($this->freezeTime === '0') {
            $this->freezeTime = 0;
        }
        if ($this->freezeTime) {
            if (preg_match('/\D/', $this->freezeTime)) {
                $this->freezeTimeStr = $this->freezeTime;
                $this->freezeTime = strtotime($this->freezeTime);
            } else {
                $this->freezeTimeStr = strftime('%c', time() + $this->freezeTime);
                $this->freezeTime = -$this->freezeTime;
            }
        }
//        if ($this->freezeTime && preg_match('/\D/', $this->freezeTime)) {
//            $this->freezeTimeStr = $this->freezeTime;
//            $this->freezeTime = strtotime($this->freezeTime);
//        } else {
////            $this->freezeTimeStr = $this->trans->translate(secondsToTime( $this->freezeTime ));
//            $this->freezeTimeStr = strftime('%c', time() + $this->freezeTime);
//            $this->freezeTime = -$this->freezeTime;
//        }


        if ($this->admin_mode) {
            $this->trans->addTerm('enroll_result_link', "<a href='?enroll_result'>{{ Show Enrollment Result }}</a>");
        }

        $this->err_msg = '';
        $this->name = '';
        $this->email = '';
        $this->action = '';
        $this->focus = '';
        $this->show_result = false;

//        $this->enroll_list_id = base_name(translateToFilename($this->listname), false);
        $this->enroll_list_id = translateToIdentifier($this->listname);
        $this->enroll_list_name = str_replace("'", '&prime;', $this->listname);

        $this->data_path = dir_name($this->file);
        $this->dataFile = resolvePath( $this->file, true );
        $this->logFile = resolvePath($this->data_path.ENROLL_LOG_FILE);

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

        preparePath($this->dataFile);

        parent::__construct($lzy);
//        parent::__construct($lzy, false);

        $this->prepareLog();
        $this->handleClientData();
        $this->setupScheduler();

        $this->lzy->page->addModules('POPUPS');
    } // __construct




    private function handleClientData() {

        if ($this->admin_mode && getUrlArg('enroll_result')) {
            $this->show_result = true;
            return;
        }

        if (isset($_POST) && $_POST) {
            $action = get_post_data('lzy-enroll-type');
            $id = trim(get_post_data('lzy-enroll-list-id'));
//			$this->listId = $id = trim(get_post_data('lzy-enroll-list-id'));
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

            $ds = new DataStorage2(['dataFile' => $this->dataFile, 'lockDB' => true]);
            $enrollData = $ds->read();
            if (!isset($enrollData[$id])) {
                $enrollData[$id] = array();
            }
            $existingData = $enrollData[$id];
            if ($action === 'add') {
                $this->action = 'add';
                // check whether name already entered:
                foreach ($existingData as $n => $rec) {
                    if ($n === '_') {
                        continue;
                    }
                    if ($rec['Name'] === $name) { // name already exists:
                        if ($existingData[$n]['EMail'] === $email) {   // with same email, so we let it pass:
                            $rec = &$existingData[$n];
                            $rec['Name'] = $name;
                            $rec['EMail'] = $email;
                            $rec['time'] = time();
//                            foreach ($this->customFieldsList as $i => $field) {
//                                $rec[$field] = $customFieldValues[$i];
//                            }
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
                    $i = sizeof($existingData) - 1; // -1 -> to take system field '_' into account
                    if ($i >= ($this->nNeeded + $this->nReserve)) {
                        return; // request probably tampered: attempt to add more records than allowed
                    }
                    $rec = &$existingData[ $i ];
                    $rec['Name'] = $name;
                    $rec['EMail'] = $email;
                    $rec['time'] = time();
//                    foreach ($this->customFieldsList as $i => $field) {
//                        $rec[$field] = $customFieldValues[ $i ];
//                    }
                }

            } elseif ($action === 'delete') {
                $this->action = 'delete';
                $found = false;
                $name = trim($name);

                if (isset($enrollData[ $id ])) {
                    $set = $enrollData[ $id ];
                } else {
                    return;
                }

                // if in 'initials' mode, try to find data rec based on email, check against initials of name:
                if (strpos($this->hideNames, 'init') === 0) {
                    $found = false;
                    foreach ($set as $rec) {
                        $initials = $this->getInitials($rec['Name']);
                        if (($name === $initials) && ($email === $rec['EMail'])) {
                            $name = $rec['Name'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
                        return;
                    }
                }


                foreach ($existingData as $n => $rec) {
                    if ($n === '_') {
                        continue;
                    }
                    if ($rec['Name'] === $name) {
                        if ($this->admin_mode) {       	// admin-mode needs no email and has no timeout
                            $found = true;
                            unset($existingData[$n]);
                            break;
                        }
                        if ($this->isInTime($rec)) {
                            if ($rec['EMail'] !== $email) {
                                writeLog("\tError: enroll email wrong [$name] [$email vs {$rec['EMail']}]");
                                $this->err_msg = '{{ enroll email wrong }}';
                                $this->name = $name;
                                $this->email = $email;
                                $this->focus = 'email';
                            } else {
                                unset($existingData[$n]);
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
                }
                if (!$found) {
                    writeLog("\tError: no enroll entry found [$name] [$email]");
                    $this->err_msg = '{{ no enroll entry found }}';
                    $this->name = $name;
                    $this->email = $email;
                }
            } else {
                return; // nothing to do
            }

            // update data:
            $nRequired = $existingData['_'];
            unset($existingData['_']);
            $enrollData[$id] = array_values($existingData);
            $enrollData[$id]['_'] = $nRequired;

            if ($this->notify) {
                $this->sendNotification($enrollData);
            }

            $ds->write($enrollData);
            $this->enrollLog($log);
        }
    } // handle $_POST




    public function render( $args = null )
    {
        $this->formAnnouncment = @$this->errorDescr[ $this->enrollInx ]['_announcement_'];
        $hash = $this->renderDialog();

        $ds = new DataStorage2($this->dataFile);
        $enrollData = $ds->read();

        if (!($enrollData)) {
            $enrollData[$this->enroll_list_id] = array();
        }
        $title = $this->header ? $this->header : $this->listname;
        if (!isset($enrollData[$this->enroll_list_id]['_'])) {
            $enrollData[$this->enroll_list_id]['_'] = "{$this->nNeeded} => $title";
            $ds->write($enrollData);
        }
        unset($enrollData[$this->enroll_list_id]['_']);
        $this->existingData = [];
        if (isset($enrollData[$this->enroll_list_id])) {
            $this->existingData = $enrollData[$this->enroll_list_id];
        }

        list($out, $hdr) = $this->renderTable();

//        $formHash = parent::getFormHash();
//        $attr = " data-source='$formHash:set$this->enrollInx'";
//        $enrollHash = '';
//        $enrollHash = ['dataSource' => $this->dataFile];
//        if ($this->ticketHash && $tck->ticketExists($this->ticketHash)) {
//            $tck->createHash(true);
//            $tck->updateTicket($this->ticketHash, $tickRec);
//        } else {
//        $tickRec = [
//            '_dataSource' => $this->dataFile,
//            '_dataKey' => "{$this->enroll_list_id},*",
//        ];
        $tickRec = [
            "set$this->enrollInx" => [
                    '_dataSource' => $this->dataFile,
                    '_dataKey' => "{$this->enroll_list_id},#",
//                    '_dataKey' => "{$this->enroll_list_id},*",
                ]
        ];
        $tck = new Ticketing(['defaultMaxConsumptionCount' => false, 'defaultType' => 'enroll']);
        $enrollHash = $tck->createTicket($tickRec, false);
//        $enrollHash = $tck->createTicket($tickRec, false, -1);
//        $enrollHash = $tck->createTicket(['_dataSource' => $this->dataFile], false, -1);

        $attr = " data-datasrc-ref='$enrollHash:set$this->enrollInx'";
//        $attr = " data-source='$enrollHash:set$this->enrollInx'";
        $cls = $this->customFields? ' lzy-enroll-auxfields': '';


        // assemble output:
        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list$cls' data-dialog-id='lzy-enroll-dialog-$hash'$attr>\n";
//        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list' data-dialog-id='{$this->enroll_list_id}'$attr>\n";
//        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list' data-dialog-title='$this->enroll_list_name' data-dialog-id='{$this->enroll_list_id}' data-dialog-inx='{$this->inx}'$attr>\n";

//        $formAnnouncment = $this->formAnnouncment;
//        if ($formAnnouncment) {
//            $out0 .= "\t\t<div class='lzy-enroll-form-message'>$formAnnouncment</div>\n";
//        }
//
        if ($this->header) {
            $out0 .= "\n\t  <div class='lzy-enroll-field lzy-enroll-header'>{$this->header}</div>\n";
        }
        if ($hdr) {
            $hdr = "\n\t\t<div class='lzy-enroll-row lzy-enroll-hdr'>\n\t\t\t<div class='lzy-enroll-field'>{{ lzy-enroll-hdr-name }}</div>$hdr\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
            $out0 .= $hdr;
        }
        $out0 .= $out;
        $out0 .= "\t</div> <!-- /lzy-enrollment-list -->\n  ";
        if ($this->err_msg) {
            $this->trans->page->addPopup($this->err_msg);
//            $this->trans->page->addMessage($this->err_msg);
        }

        if ($this->formAnnouncment) {
//            $msg = "\t\t<div class='lzy-enroll-form-message'>$this->formAnnouncment</div>\n";
            $this->lzy->page->addPopup($this->formAnnouncment);
        }

        return $out0;
    } // render



    private function sendNotification($enrollData)
    {
        $notifies = explodeTrim(',', $this->notify);
        foreach ($notifies as $notify) {
            if (preg_match('/\( (.*) \) (.*)/x', $notify, $m)) {
                $time = trim($m[1]);
                $to = trim($m[2]);
                if (preg_match('/(.*) :: (.*)/x', $time, $mm)) {
                    $time = $mm[1];
                    $freq = $mm[2];
                    $this->setupScheduler($time, $freq, $to);
                    continue;
                }
                list($from, $till) = $this->deriveFromTill($time);
                $now = time();
                if (($now > $from) && ($now < $till)) {
                    $this->_sendNotification($enrollData, $to);
                }
            } else {
                $this->_sendNotification($enrollData, $notify);
            }
        }
    } // sendNotification



    private function _sendNotification($enrollData, $to)
    {
        $msg = "{{ lzy-enroll-notify-text-1 }}\n";

        foreach ($enrollData as $listName => $list) {
            $n = sizeof($list) - 1;
            list($nRequired, $title) = isset($list['_']) ? explode('=>', $list['_']) : ['?', ''];
            $listName = $title ? $title : $listName;
            $listName = str_pad("$listName: ", 24, '.');
            $msg .= "$listName $n {{ of }} $nRequired\n";
        }
        $msg .= "{{ lzy-enroll-notify-text-2 }}\n";
        $msg = $this->trans->translate($msg);
        $msg = str_replace('\\n', "\n", $msg);
        $subject = $this->trans->translate('{{ lzy-enroll-notify-subject }}');

        require_once SYSTEM_PATH.'messenger.class.php';

        $mess = new Messenger($this->notifyFrom, $this->trans->lzy);
        $mess->send($to, $msg, $subject);
    } // _sendNotification



    private function setupScheduler()
    {
        if (!$this->notify) {
            return;
        }

        $newSchedule = [];
        $notifies = explode(',', $this->notify);
        foreach ($notifies as $notify) {
            if (preg_match('/\( (.*) \) (.*)/x', $notify, $m)) {
                $time = trim($m[1]);
                $to = trim($m[2]);
                if (preg_match('/(.*) :: (.*)/x', $time, $mm)) {
                    list($from, $till) = $this->deriveFromTill(trim($mm[1]));
                    $freq = trim(strtolower($mm[2]));
                    if ($freq === 'daily') {
                        $time = '****-**-** 08:00';
                    } elseif ($freq === 'weekly') {
                        $time = 'Mo 08:00';
                    } else {
                        die("Error: enroll() -> time pattern not recognized: '$freq'");
                    }
                    $newSchedule[] = [
                        'src' => $GLOBALS['globalParams']['pathToPage'],
                        'time' => $time,
                        'from' => date('Y-m-d H:i', $from),
                        'till' => date('Y-m-d H:i', $till),
                        'loadLizzy' => true,
                        'do' => $this->scheduleAgent,
                        'args' => [
                            'to' => $to,
                            'from' => $this->notifyFrom,
                            'dataFile' => $this->dataFile,
                        ]
                    ];
                }
            }
        }

        $file = SCHEDULE_FILE;
        $schedule = getYamlFile($file);

        // clean up existing schedule entries:
        $now = time();
        foreach ($schedule as $i => $rec) {
            $from = strtotime($rec['from']);
            $till = strtotime($rec['till']);
            if (($now < $from) || ($now > $till)) {
                unset($schedule[$i]);
            }
        }

        $schedule = array_merge($schedule, $newSchedule);
        writeToYamlFile($file, $schedule);
    } // setupScheduler



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
    } // enrollLog



    private function prepareLog()
    {
        if (!file_exists($this->logFile)) {
            $customFields = '';
//            foreach ($this->customFieldsList as $item) {
//                if (preg_match('/[\s,;]/', $item)) {
//                    $customFields .= "; '$item'";
//                } else {
//                    $customFields .= "; $item";
//                }
//            }
            if ($this->logAgentData) {
                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields; Client; IP\n");
            } else {
                file_put_contents($this->logFile, "Timestamp; Action; List; Name; Email$customFields\n");
            }
        }
    } // prepareLog



    private function hideName($name)
    {
        $hide = false;
        if ($this->hideNames && !$this->trans->lzy->auth->isAdmin()) {
            if ($this->unhideNamesForGroup) {
                if ($this->trans->lzy->auth->checkGroupMembership($this->unhideNamesForGroup)) {
                    $hide = false;
                } else {
                    $hide = true;
                }
            } else {
                $hide = true;
            }
        }
        if ($hide) {
            if (strpos($this->hideNames, 'init') === 0) {
                $name = $this->getInitials($name);
            } else {
                $name = '****';
                $this->freezeTime = 0;
            }
        } elseif ($this->hideNames && $this->trans->lzy->auth->isAdmin()) {
            $name = "<span class='lzy-enroll-admin-only' title='Visible to admins only'>$name</span>";
        }

        return $name;
    } // hideName



    private function isInTime($rec)
    {
        if (isset($rec['time'])) {
            $lastModified = $rec['time'];
        } elseif (isset($rec['_timestamp'])) {
            $lastModified = strtotime( $rec['_timestamp'] );
        } else {
            return false; // Error
        }
        if (($this->freezeTime === false) || $this->admin_mode) {
            $inTime = true;
        } else {
            $this->freezeTime = intval($this->freezeTime);
            if ($this->freezeTime < 0) {
                $inTime = (intval($lastModified) > time() + $this->freezeTime);
            } else {
                $inTime = (time() < $this->freezeTime);
            }
        }
        return $inTime;
    } // isInTime



    private function deriveFromTill($time)
    {
        $from = 0;
        $till = time() + 1000;

        if (preg_match('/^ (\d\d\d\d-\d\d-\d\d) (.*) /x', $time, $m)) {
            $from = strtotime(trim($m[1]));
            $time = $m[2];
        }
        if (preg_match('/- \s*(\d\d\d\d-\d\d-\d\d) /x', $time, $m)) {
            $till = strtotime('+1 day', strtotime(trim($m[1])));
        }
        return array($from, $till);
    } // deriveFromTill



    private function getInitials($name): string
    {
        $parts = explode(' ', $name);
        $name = strtoupper($parts[0][0]);
        if (sizeof($parts) > 1) {
            $name .= strtoupper($parts[sizeof($parts) - 1][0]);
        }
        return $name;
    } // getInitials



    private function renderTable()
    {
        $out = '';
        $existingData = $this->existingData;
        unset($existingData['_timestamp']);
        unset($existingData['_key']);
        $existingData = array_values( $existingData );

        $nn = $this->nNeeded + $this->nReserve;
        $new_field_done = false;

        // loop over list:
        for ($n = 0; $n < $nn; $n++) {
            $res = ($n >= $this->nNeeded) ? ' lzy-enroll-reserve-field' : '';
            $num = "<span class='lzy-num'>" . ($n + 1) . ":</span>";

            $rec = &$existingData[$n];

            if (isset($rec['Name'])) {    // Name exists -> delete
                $name = $rec['Name'];
                $email = @$rec['EMail'];
                if ($this->admin_mode) {
                    $name .= " &lt;$email>";
                }
                $name = $this->hideName($name);

                if ($this->customFields && $this->editable) {
//                    $targId = "#modifyDialog{$this->hash}";
                    $tooltip = '{{ lzy-enroll-modify-entry }}';
                    $icon = "<span class='lzy-enroll-modify'>&#9998;</span>";
                } else {
//                    $targId = "#lzy-enroll-delete-dialog{$this->hash}";
//                    $targId = "#delDialog{$this->hash}";
                    $tooltip = '{{ lzy-enroll-delete-entry }}';
                    $icon = "<span class='lzy-enroll-del'>−</span>";
                }
//                $targId = '#';
                if ($this->isInTime($rec)) {
                    $a = "<a href='#' title='$tooltip'>\n\t\t\t\t  <span class='lzy-enroll-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
//                    $a = "<a href='$targId' title='$tooltip'>\n\t\t\t\t  <span class='lzy-name lzy-col1'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
                    $class = 'lzy-enroll-del-field';
                } else {
                    $a = "<span class='lzy-enroll-name'>$name</span>";
                    $class = 'lzy-enroll-frozen-field';
//                    $class = 'lzy-enroll-frozen_field';
                }

            } else {            // add
                if (!$new_field_done) {
                    $name = '{{ lzy-enroll-add-text }}';
                    $icon = "<span class='lzy-enroll-add'>+</span>";
                    $a = "<a href='#' title='{{ lzy-enroll-new-name }}'>\n\t\t\t\t  <span class='lzy-enroll-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
//                    $a = "<a href='#lzy-enroll-add-dialog{$this->hash}' title='{{ lzy-enroll-new-name }}' data-rel='popup' data-position-to='window' data-transition='pop'>\n\t\t\t\t  <span class='lzy-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
                    $new_field_done = true;
                    $class = 'lzy-enroll-add-field';

                } else {        // free cell
                    $class = 'lzy-enroll-empty-field';
                    $a = "<span class='lzy-enroll-name'>&nbsp;</span>\n";
                }
            }

            if (@$rec[REC_KEY_ID]) {
                $recKey = " data-rec-key='{$rec[REC_KEY_ID]}'";
            } else {
                $recKey = '';
            }

            $rowContent = "\t\t\t<div class='lzy-enroll-field $class'>\n\t\t\t\t$num\n\t\t\t\t$a\n\t\t\t</div><!-- /$class -->";

            // assemble auxiliary fields:
            $aux = '';
            $hdr = '';
            $tooltipCls = ($this->tooltips)? 'tooltipster ': '';
            foreach ($this->enrollSpecificElems as $i => $custField) {
                if (!$custField) {
                    continue;
                }
                $cls = "{$tooltipCls}lzy-col".($i+3);
                $name = str_replace(' ', '_', $custField);
                $name = preg_replace("/[^[:alnum:]_-]/m", '', $name);	// remove any non-letters, except _ and -
                $val = isset($rec[$name]) && $rec[$name] ? $rec[$name] : '&nbsp;';
//                $val = isset($rec[$custField]) && $rec[$custField] ? $rec[$custField] : '&nbsp;';
                $aux .= "\n\t\t\t<div class='lzy-enroll-aux-field $cls' title='$val'>\n\t\t\t\t$val\n\t\t\t</div>";
//                $aux .= "\n\t\t\t<div class='lzy-enroll-aux-field $cls' data-class='lzy-enroll-aux-field$i'>\n\t\t\t\t$val\n\t\t\t</div>";
                $hdr .= "\n\t\t\t<div class='lzy-enroll-aux-field'>$custField</div>";
            }
            $out .= "\t\t<div class='lzy-enroll-row$res'$recKey>\n$rowContent$aux\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
//            $out .= "\t\t<div class='lzy-enroll-row$res'>\n$rowContent$aux\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
        }
        return array($out, $hdr);
    } // renderTable



    private function renderDialog()
    {
        list($dialog, $hash) = $this->_renderDialog();
        if (isset($GLOBALS['globalParams']['enroll_form_created'][$hash])) {
            return ''; // already rendered
        }
        $GLOBALS['globalParams']['enroll_form_created'][$hash] = true;

        $dialog .= $this->createErrorMsgs();
        $this->trans->page->addBodyEndInjections( $dialog );
        return $hash;
    } // renderDialog



    private function _renderDialog()
    {
        // parse args, split header-related from rest:
        $headArgs = [];
        $enrollSpecificArgs = [];
        $formElems = [];
        foreach ($this->args as $key => $value) {
            if ($this->isHeadAttribute( $key )) {
                $headArgs[$key] = $value;

            } elseif (strpos(ENROLLMENT_SPECIFIC_ELEMENTS, ",$key,") !== false) {
                $enrollSpecificArgs[$key] = $value;

            } else {
                if (isset($value[ 0 ])) {
                    if (strpos(SUPPORTED_TYPES, $value[ 0 ]) !== false) {
                        $value['type'] = $value[ 0 ];
                        unset( $value[ 0 ] );
                    }
                }
                $formElems[$key] = $value;
            }
        }
        $this->enrollSpecificElems = array_keys( $formElems );
        $this->customFields = (sizeof($formElems) > 0);
        // render form-head:
        $headArgs['type'] = 'form-head';
        $headArgs['file'] = '~/'.$this->dataFile;
        $headArgs['novalidate'] = false;
        $headArgs['translateLabels'] = true;
        $headArgs['skipConfirmation'] = true;
        $headArgs['suppressFormFeedback'] = true;
        $headArgs['dataKeyOverride'] = "enrollment-list{$this->enrollInx},#";
        // dataKeyOverride: '*' -> hash; '#' -> index
//        $headArgs['dataKeyOverride'] = "enrollment-list{$this->enrollInx},*";
        $headArgs['recModifyCheck'] = 'EMail';
//        $headArgs['dataKeyOverride'] = "enrollment-list1,*";
//        if (isset($headArgs['formFooter'])) {
//            $headArgs['formFooter'] = "<div class='lzy-enroll-comment'>{$headArgs['formFooter']}</div>";
//        } else {
//            $headArgs['formFooter'] = '<div class="lzy-enroll-comment">{{ lzy-enroll-add-comment }}</div>';
//        }

        if (isset($headArgs['formFooter'])) {
            $formFooter = $headArgs['formFooter'];
        } else {
            if ($this->freezeTime) {
                if (isset($headArgs['formFooter'])) {
                    $formFooter = $headArgs['formFooter'];
                } else {
                    if ($this->customFields) {
                        $formFooter = '{{ lzy-enroll-add2-comment }}';
                    } else {
                        $formFooter = '{{ lzy-enroll-add-comment }}';
                    }
                }

                $formFooter = $this->trans->translate($formFooter);
                $formFooter = str_replace('%freezetime%', $this->freezeTimeStr, $formFooter);

            } elseif ($this->freezeTime === false) {
                $formFooter = '{{ lzy-enroll-add-comment-no-freeze }}';
            } else {
                $formFooter = '';
            }
        }

        if ($formFooter) {
            $headArgs['formFooter'] = "<div class='lzy-enroll-comment'>$formFooter</div>";
        }
//        $headArgs['formFooter'] = $formFooter;

        $str = parent::render( $headArgs );

        if (!isset($headArgs['class'])) {
            $headArgs['class'] = 'lzy-enroll-form lzy-form lzy-form-colored lzy-encapsulated';
        }
        // render default fields: name, email:
        $defaultFields = [
            [
                'type' => 'hidden',
                'name' => '_lizzy-data-ref',
                'value' => '', // to be injected when form is opened
                'class' => 'lizzy-data-ref',
            ],
            [
                'type' => 'hidden',
                'name' => '_rec-key',
                'value' => '', // to be injected when form is opened
                'class' => 'lizzy-rec-key',
            ],
            [
                'type' => 'text',
                'label' => 'lzy-enroll-name',
                'name' => 'Name',
                'required' => true,
                'class' => 'lzy-enroll-name',
            ],
//            [
//                'type' => 'email',
//                'label' => 'lzy-enroll-email',
//                'name' => 'EMail',
//                'required' => true,
//                'class' => 'lzy-enroll-email',
//            ],
        ];
        foreach ($defaultFields as $arg) {
            $str .= parent::render($arg);
        }

        if ($this->freezeTime !== 0) {
            $str .= parent::render([
                'type' => 'email',
                'label' => 'lzy-enroll-email',
                'name' => 'EMail',
                'required' => true,
                'class' => 'lzy-enroll-email',
            ]);
        }

        // parse further arguments, interpret as form field definitions:
        $col = 3;
        $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];
        foreach ($formElems as $label => $arg) {
            if (is_string($arg)) {
                $arg = ['type' => $arg ? $arg : 'text'];
            }
            if (isset($arg[0])) {
                if ($arg[0] === 'required') {
                    $arg['required'] = true;
                    unset($arg[0]);
                } else {
                    $arg['type'] = $arg[0];
                }
            }
            if ($label === 'submit') {
                $buttons['label'] .= isset($arg['label']) ? $arg['label'].',': '{{ Submit }},';
                $buttons['value'] .= 'submit,';
                $arg['type'] = 'button';

            } elseif (($label === 'cancel') || ($label === 'reset')) {
                $buttons['label'] .= isset($arg['label']) ? $arg['label'].',': '{{ Cancel }},';
                $buttons['value'] .= 'cancel,';
                $arg['type'] = 'button';

            } elseif (strpos('formName,mailto,mailfrom,legend,showData', $label) !== false) {
                die(__FILE__. ' '.__LINE__.' Error: clause should be obsolete...');
                // nothing to do
            } elseif (is_bool($arg)) {
                if (isLocalhost()) {
                    exit("Enrollment(): unknown arg '$label' <br>(forgot to add it to ENROLLMENT_SPECIFIC_ELEMENTS?)");
                } else {
                    writeLog("Enrollment(): unknown arg '$label' (probably forgot to add it to ENROLLMENT_SPECIFIC_ELEMENTS)");
                    exit;
                }
            } else {
                $arg['label'] = $label;
                if (@$arg['class']) {
                    $arg['class'] .= " lzy-col$col";
                } else {
                    $arg['class'] = "lzy-col$col";
                }
                $col++;
                $str .= parent::render($arg);
            }
        }

        // add buttons, preset with default buttons if not defined:
        if (!$buttons['value']) {
            $buttons = [
                'option' => 'delete-rec',
                'options' => 'cancel,submit,delete',
                'label' => 'lzy-enroll-cancel,lzy-enroll-submit,lzy-enroll-delete-btn-short',
//                'options' => 'cancel,submit',
//                'label' => 'lzy-enroll-cancel,lzy-enroll-submit',
                'type' => 'button',
            ];
        }
        $str .= parent::render($buttons);

        $str .= parent::render([ 'type' => 'form-tail' ]);

        $hash = crc32( $str );

        $form = <<<EOT



    <!-- === Enroll Dialog =================== -->
    <div id="lzy-enroll-dialog-$hash" class="lzy-enroll-dialog" style='display:none;'>
      <div>
$str
<!--          <div class="lzy-enroll-comment">{{ lzy-enroll-add-comment }}</div>-->
          <div class="lzy-enroll-add-title" style="display: none">{{ lzy-enroll-add-title }}</div>
          <div class="lzy-enroll-delete-title" style="display: none">{{ lzy-enroll-delete-title }}</div>
          <div class="lzy-enroll-modify-title" style="display: none">{{ lzy-enroll-modify-title }}</div>
      </div>
    </div><!-- /lzy-enroll-dialog -->

EOT;
        return [$form, $hash];
    } // _renderDialog


} // class Enrollment


