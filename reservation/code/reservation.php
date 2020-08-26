<?php

// @info:  Lets you set up enrollment lists where people can put their name to indicate that they intend to participate at some event, for instance.

require_once SYSTEM_PATH.'forms.class.php';

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml", false), false, true );

define('RESERVATION_DATA_FILE','reservation.csv');
define('RESERVATION_LOG_FILE', 'reservation.log.txt');

$page->addModules('~ext/reservation/js/reservation.js, ~ext/reservation/css/reservation.css');

resetScheduleFile();

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	// form related options:
    $h = $this->getArg($macroName, 'file', 'The file in which to store reservations.', RESERVATION_DATA_FILE);
    $this->getArg($macroName, 'mailfrom', '', '');
    $this->getArg($macroName, 'mailto', '', '');
    $this->getArg($macroName, 'legend', '', '');
    $this->getArg($macroName, 'class', '', '');
    $this->getArg($macroName, 'showData', '(optional) [false, true, loggedIn, privileged, localhost, {group}] Defines, to whom previously received data is presented (default: false).', false);

    // reservation related options:
    $this->getArg($macroName, 'maxSeats', 'Total number of seats available.', 1);
    $this->getArg($macroName, 'maxSeatsPerReservation', 'Max. number of seats per reservation', 4);
    $this->getArg($macroName, 'waitingListLength', 'Number of seats in the waiting list.', 0);
    $this->getArg($macroName, 'moreThanThreshold', 'If more seats are available, display as "More than X"', false);
    $this->getArg($macroName, 'confirmationEmail', 'If true, users receive a confirmation by E-Mail', true);
    $this->getArg($macroName, 'timeout', 'The time that a user can take to fill in the form. Note: during that time \'maxSeatsPerReservation\' seats are tentatively reserved.', 600);
    $this->getArg($macroName, 'notify', 'Activates notification of designated persons, either upon user interactions or in regular intervals. See <a href="https://getlizzy.net/macros/extensions/reservation/">documentation</a> for details.', false);
    $this->getArg($macroName, 'notifyFrom', 'See <a href="https://getlizzy.net/macros/extensions/reservation/">documentation</a> for details.', '');
    $this->getArg($macroName, 'scheduleAgent', 'Specifies user code to assemble and send notfications. See <a href="https://getlizzy.net/macros/extensions/reservation/">documentation</a> for details.', 'scheduleAgent.php');
    // $this->getArg($macroName, 'logAgentData', "[true,false] If true, logs visitor's IP and browser info (illegal if not announced to users)", false);

    if ($h === 'help') {
        return '';
    }
    if ($h === 'info') {
        $out = $this->translateVariable('lzy-reservation-info', true);
        $this->compileMd = true;
        return $out;
    }

    $args = $this->getArgsArray($macroName);

    $rsrv = new Reservation($this->lzy, $inx, $this, $args);
    $out = $rsrv->render();
	return $out;
});




//=== class ====================================================================
class Reservation
{
	public function __construct($lzy, $inx, $trans, $args)
	{
	    $this->lzy = $lzy;
	    $this->inx = $inx;
	    $this->args = $args;

        $this->file = $args['file'];
        $this->formName = "lzy-reservation-$inx";
        $this->mailfrom = $args['mailfrom'];
        $this->mailto = $args['mailto'];
        $this->class = $args['class'];
        $this->legend = $args['legend'];
        $this->showData = $args['showData'];

        $this->maxSeats = intval($args['maxSeats']);
        $this->maxSeatsPerReservation = intval($args['maxSeatsPerReservation']);
        $this->waitingListLength = intval($args['waitingListLength']);
        $this->moreThanThreshold = intval($args['moreThanThreshold']);
        $this->confirmationEmail = $args['confirmationEmail'];
        $this->timeout = $args['timeout'];
        $this->notify = $args['notify'];
        $this->notifyFrom = str_replace(['&#39;', '&#34;'], ["'", '"'], $args['notifyFrom']);
        $this->scheduleAgent = $args['scheduleAgent'];
//        $this->reservationCallback = $args['reservationCallback'];
//        $this->logAgentData = $args['logAgentData'];

		$this->response = [];
		$this->admin_mode = false;
		$this->trans = $trans;


		$this->dataFile = resolvePath($this->file, true);
		$this->logFile = resolvePath(RESERVATION_LOG_FILE, true);

        $this->form = new Forms($this->lzy);

        // Evaluate if form data received
        if (isset($_GET['lizzy_form']) || isset($_POST['lizzy_form'])) {	// we received data:
            $res = $this->form->evaluate(); // return value = err msg or false=ok
            if (!$res) {
                $this->response = [$res, false];
//            } elseif (is_array($res)) {
//                $errMsg = $res[0]; // rec already in DB
            }
        }

		preparePath($this->dataFile);
        // $this->prepareLog();
        $this->ds = new DataStorage2($this->dataFile);
        $this->handleClientData();
        $this->setupScheduler();

    } // __construct




	//----------------------------------------------------------------------
	private function handleClientData()
    {
		if (!isset($_POST) || !$_POST) {
            return;
        }
        $userSuppliedData = $_POST;
        $hash = $userSuppliedData['_lzy-reservation-ticket'];
        $tick = new Ticketing();
        $rec = $tick->consumeTicket($hash);
        $inx = $rec['inx'];
        if ($inx !== $this->inx) {
            return;
        }

        if ($userSuppliedData['lizzy_next'] === '_delete_') {
            $this->deleteRec($hash);
            $this->response[$inx] = 'lzy-reservation-aborted';
            return;
        }

        $existingReservations = $this->countReservations();
        if ($existingReservations > $this->maxSeats) {
            $this->deleteRec($hash);
            $this->response[$inx] = 'lzy-reservation-full-error';
            return;
        }
        unset($_SESSION['lizzy']['reservation'][$inx]);

        $this->sendConfirmationMail( $userSuppliedData );

        $this->response[$inx] = 'lzy-reservation-success';
	} // handle $_POST




	//-----------------------------------------------------------------------------------------------
	public function render()
    {
        if (isset($this->response[$this->inx]) && $this->response[$this->inx]) {
            $response = $this->lzy->trans->translateVariable($this->response[$this->inx], true);
            return "\t<div class='lzy-reservation-response'>$response</div>\n";
        }

        $tick = new Ticketing([
            'defaultType' => 'pending-reservations',
            'defaultValidityPeriod' => $this->timeout,
            'defaultMaxConsumptionCount' => 1,
        ]);

        $pendingRes = $this->getPendingReservations($tick);

        $nReservations = $this->countReservations();
        $seatsAvailable = $this->maxSeats - $nReservations - $pendingRes;
        if (strpos($this->legend, '$seatsAvailable') !== 0) {
            $this->legend = str_replace('$seatsAvailable', $seatsAvailable, $this->legend);
        }

        if (($nReservations + $pendingRes) >= $this->maxSeats) {
            $out = $this->lzy->trans->translateVariable('lzy-reservation-full', true);
            return $out;
        }

        $ticket = false;
        if (isset($_SESSION['lizzy']['reservation'][$this->inx])) {
            $ticket = $_SESSION['lizzy']['reservation'][$this->inx];
            if (!$tick->findTicket($ticket)) {
                $ticket = false;
            }
        }
        if (!$ticket) {
            $rec = [
                'lizzy_form' => $this->formName,
                'inx' => $this->inx,
                'file' => $this->file,
                'nSeats' => $this->maxSeatsPerReservation,
            ];
            $ticket = $tick->createTicket($rec);
            $_SESSION['lizzy']['reservation'][$this->inx] = $ticket;
        }
        // create form head:
        $str = $this->form->render([
            'type' => 'form-head',
            'label' => $this->formName,
            'class' => $this->class,
            'mailto' => $this->mailto,
            'mailfrom' => $this->mailfrom,
            'file' => $this->file,
            'legend' => $this->legend,
            'validate' => true,
            'showData' => $this->showData,
//            'postprocess' => 'reservationPostprocess',
            'next' => './',
        ]);

        $wrapperClass = ($this->maxSeatsPerReservation === 1) ? 'dispno': '';
        $defaultFields = [
          [
              'type' => 'hidden',
              'label' => '_lzy-reservation-ticket',
              'shortlabel' => '_ticket',
              'value' => $ticket,
          ],
          [
              'type' => 'hidden',
              'label' => '_lzy-reservation-timeout',
              'shortlabel' => '_timeout',
              'value' => $this->timeout,
          ],
          [
              'type' => 'text',
//              'label' => $this->trans->translateVariable('First Name'),
              'label' => 'First Name',
          ],
          [
              'type' => 'text',
              'label' => 'Last Name',
              'required' => true,
          ],
          [
              'type' => 'email',
              'label' => 'E-Mail',
              'required' => true,
          ],
          [
              'type' => 'number',
              'label' => 'lzy-reservation-count-label',
              'name' => 'reservation-count',
              'labelInOutput' => 'reservation-count',
              'required' => true,
              'min' => 1,
              'max' => min($this->maxSeatsPerReservation, $seatsAvailable),
              'value' => 1,
              'wrapperClass' => $wrapperClass,
          ],
        ];

        foreach ($defaultFields as $arg) {
            $str .= $this->form->render($arg);
        }

        $reservedLabels = ',formName,file,mailfrom,mailto,class,legend,showData,maxSeats,maxSeatsPerReservation,'.
            'waitingListLength,moreThanThreshold,confirmationEmail,timeout,notify,notifyFrom,scheduleAgent,logAgentData,';

        $formFieldTypes = ['text','password','email','textarea','radio','checkbox','button','url','date','time',
            'datetime','month','number','range','tel','file','dropdown'];

        // parse further arguments, interpret as form field definitions:
        foreach ($this->args as $label => $arg) {
            if (strpos($reservedLabels, ",$label,") !== false) {
                continue;
            }
            if (is_string($arg)) {
                $arg = ['type' => $arg ? $arg : 'text'];
            } else {
                foreach ($arg as $k => $v) {
                    if (is_int($k)) {
                        if (in_array($v, $formFieldTypes)) {
                            $arg['type'] = $v;
                        } else {
                            $arg[$v] = true;
                        }
                        unset($arg[$k]);
                    }
                }
                if (!isset($arg['type'])) {
                    $arg['type'] = 'text';
                }
            }
            $arg['label'] = $label;
            $str .= $this->form->render($arg);
        }

        $lblSubmit = $this->lzy->trans->translateVariable('lzy-reservation-submit', true);
        $lblCancel = $this->lzy->trans->translateVariable('lzy-reservation-cancel', true);
        $str .= $this->form->render(          [
            'value' => 'cancel,submit',
            'label' => "$lblCancel,$lblSubmit",
            'type' => 'button',
        ]);
        $str .= "\t<div class=''>{{^ lzy-reservation-explanations }}</div>\n";
        $str .= $this->form->render([ 'type' => 'form-tail' ]);

        if ($this->inx === 1) {
            $popup = <<<EOT
        <div id='lzy-reservation-timed-out-msg' style="display: none;">
            <div class="lzy-reservation-timeout-pup">
{{ lzy-reservation-timed-out }}
                <button id="lzy-reservation-timed-out-btn" class="lzy-button">{{ lzy-reload }}</button>
            </div>
        </div>

EOT;
            $this->lzy->page->addBodyEndInjections($popup);
            $this->lzy->page->addModules( 'JS_POPUPS' );
        }

        return $str;
	} // render


    //------------------------------------
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



    //------------------------------------
    private function _sendNotification($enrollData, $to)
    {
        $msg = "{{ lzy-reservation-notify-text-1 }}\n";

        foreach ($enrollData as $listName => $list) {
            $n = sizeof($list) - 1;
            list($nRequired, $title) = isset($list['_']) ? explode('=>', $list['_']) : ['?', ''];
            $listName = $title ? $title : $listName;
            $listName = str_pad("$listName: ", 24, '.');
            $msg .= "$listName $n {{ of }} $nRequired\n";
        }
        $msg .= "{{ lzy-reservation-notify-text-2 }}\n";
        $msg = $this->trans->translate($msg);
        $msg = str_replace('\\n', "\n", $msg);
        $subject = $this->trans->translate('{{ lzy-reservation-notify-subject }}');

        require_once SYSTEM_PATH.'messenger.class.php';

        $mess = new Messenger($this->notifyFrom, $this->trans->lzy);
        $mess->send($to, $msg, $subject);
    } // _sendNotification




    //------------------------------------
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
                        die("Error: reservation() -> time pattern not recognized: '$freq'");
                    }
                    $newSchedule[] = [
                        'src' => $GLOBALS["globalParams"]["pathToPage"],
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




    //------------------------------------
    private function createErrorMsgs()
    {
        $errMsg = <<<EOT

<!-- Enrollment Dialog error messages: -->
<div class="dispno">
    <div class="lzy-reservation-name-required">{{ lzy-reservation-name-required }}:</div>
    <div class="lzy-reservation-email-required">{{ lzy-reservation-email-required }}:</div>
    <div class="lzy-reservation-email-invalid">{{ lzy-reservation-email-invalid }}:</div>
</div>


EOT;
        return $errMsg;
    } // createErrorMsgs



    //------------------------------------
//    private function registrationLog($out)
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



    //------------------------------------
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
//    } // prepareLog





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



    private function countReservations()
    {
        $data = $this->ds->read();
        if (!$data) {
            return 0;
        }
        $countInx = 3;  // index of nSeats field
        $count = 0;
        foreach ($data as $rec) {
            if (isset($rec[$countInx])) {   // nSeats field
                $count += intval($rec[$countInx]);
            }
        }
        return $count;
    } // countReservations



    private function sendConfirmationMail( $rec )
    {
        if (!$this->confirmationEmail) {
            return;
        }
        $isHtml = false;
        foreach ($rec as $key => $value) {
            if ((strpos($key, 'lizzy_') === 0) || (strpos($key, 'lzy-') === 0)) {
                continue;
            }
            $this->trans->addVariable("$key-value", $value);
        }
        $to = $rec['e-mail'];
        if ($this->confirmationEmail === true) {
            $subject = '{{ lzy-confirmation-response-subject }}';
            $message = '{{ lzy-confirmation-response-message }}';

        } else {
            $file = resolvePath($this->confirmationEmail, true);
            if (!file_exists($file)) {
                $this->response = 'lzy-reservation-email-template-not-found';
                return;
            }

            $ext = fileExt($file);
            $tmpl = file_get_contents($file);

            if (stripos($ext, 'md') !== false) {
                $page = new Page();
                $tmpl = $page->extractFrontmatter($tmpl);
                $css = $page->get('css');
                $fm = $page->get('frontmatter');
                $subject = isset($fm['subject']) ? $fm['subject'] : '';
                if ($css) {
                    $css = <<<EOT
	<style>
$css
	</style>

EOT;
                }
                $message = compileMarkdownStr($tmpl);
                $message = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
$css
</head>
<body>
$message
</body>
</html>

EOT;
                $isHtml = true;

            } elseif ((stripos($ext, 'htm') !== false)) {
                $page = new Page();
                $tmpl = $this->lzy->page->extractHtmlBody($tmpl);
                $css = $page->get('css');
                $fm = $page->get('frontmatter');
                $subject = isset($fm['subject']) ? $fm['subject'] : '';
                $message = extractHtmlBody($tmpl);
                $isHtml = true;

            } else {    // .txt
                if (preg_match('/subject: (.*?)\n(.*)/ims', $tmpl, $m)) {
                    $subject = $m[1];
                    $message = $m[2];
                } else {
                    $this->response = 'lzy-reservation-email-template-syntax-error';
                    return;
                }
            }
        }
        $subject = $this->trans->translate($subject);
        $message = translateUmlauteToHtml( ltrim($this->trans->translate($message)) );

        $this->lzy->sendMail($to, $subject, $message, $isHtml);
        return false;
    } // sendConfirmationMail




    private function deleteRec( $hash )
    {
        $data = $this->ds->read();
        $data = array_reverse($data);
        foreach ($data as $key => $rec) {
            if ($rec[0] === $hash) {
                $this->ds->deleteRecord( $key );
                return true;
            }
        }
        return false;
    } // deleteRec




    private function renderErrorReply($msg)
    {
        $out = <<<EOT
{{ $msg }}
EOT;
        $out = $this->lzy->trans->translate($out);
        return $out;
    } // renderErrorReply



    private function getPendingReservations(Ticketing $tick)
    {
        $pendingRes = $tick->sum('nSeats');
        // if reloading page one ticket is pending for ourself, so discount it:
        if (isset($_SESSION['lizzy']['reservation'][$this->inx])) {
            $pendingRes -= $this->maxSeatsPerReservation;
        }
        return $pendingRes;
    } // getPendingReservations
} // class reservation




function resetScheduleFile()
{
    $thisSrc = $GLOBALS["globalParams"]["pathToPage"];
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



//function reservationPostprocess()
//{
//    return '';
//}

