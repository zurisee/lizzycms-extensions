<?php

define('RESERVATION_SPECIFIC_ELEMENTS', ',emailField,requireEmail,deadline,maxSeats,maxSeatsPerReservation,'.
    'waitingListLength,moreThanThreshold,notify,'.
    'notifyFrom,scheduleAgent,logAgentData,');

require_once SYSTEM_PATH.'forms.class.php';

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml", false), false, true );

define('RESERVATION_DATA_FILE','reservation.csv');
define('RESERVATION_LOG_FILE', 'reservation.log.txt');

$page->addModules('~ext/reservation/css/reservation.css');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $args = $this->getArgsArray($macroName);

    if (@$args[0] === 'help') {
        $this->compileMd = true;
        return renderReservationsHelp();
    }
    if (@$args[0] === 'info') {
        $out = $this->translateVariable('lzy-reservation-info', true);
        $this->compileMd = true;
        return $out;
    }

    $rsrv = new Reservation($this->lzy, $args);
    $out = $rsrv->render();
	return $out;
});




//=== class ====================================================================
class Reservation extends Forms
{
    protected $args, $dataFile, $deadline, $maxSeats, $maxSeatsPerReservation;
    protected $waitingListLength, $moreThanThreshold, $confirmationEmail, $emailField;
    protected $notify, $notifyFrom, $scheduleAgent, $reservationCallback;
    protected $formHash, $ds, $resTick, $resSpecificArgs, $requireEmail;

    public function __construct($lzy, $args)
    {
        $this->args = $args;

        $this->requireEmail =               isset($args['requireEmail'])? $args['requireEmail']: false;
        $this->dataFile =                   @$args['file'];
        $this->deadline =                   @$args['deadline'];
        if ($this->deadline && is_string($this->deadline)) {
            $this->deadline = strtotime($this->deadline);
        }

        $this->maxSeats =                   intval( @$args['maxSeats'] );
        $this->maxSeatsPerReservation =     intval( @$args['maxSeatsPerReservation'] );
    //        $this->waitingListLength = intval($args['waitingListLength']);
        $this->waitingListLength =          false;
        $this->moreThanThreshold =          intval( @$args['moreThanThreshold'] );
        $this->confirmationEmail =          @$args['confirmationEmail'];
        $this->emailField =                 @$args['emailField'] || $this->confirmationEmail;
    //        $this->notify = @$args['notify'];
    //        $this->notifyFrom = str_replace(['&#39;', '&#34;'], ["'", '"'], $args['notifyFrom']);
    //        $this->scheduleAgent = @$args['scheduleAgent'];
    //        $this->reservationCallback = @$args['reservationCallback'];
        $this->notify = false;
        $this->notifyFrom = false;
        $this->scheduleAgent = false;
        $this->reservationCallback = false;
    //        $this->logAgentData = $args['logAgentData'];

        parent::__construct($lzy, true);

        $this->formHash = parent::getFormHash();

        preparePath($this->dataFile);
        // $this->prepareLog();
        $this->ds = new DataStorage2($this->dataFile);
        $this->evaluateClientData();
    //        $this->setupScheduler();
    } // __construct




    public function render( $args = null )
    {
        $args = $this->args;

        if ($this->deadline && ($this->deadline < time())) {
            return "<p class='lzy-reservtion-deadline-passed'>{{ lzy-reservation-deadline-passed }}</p>";
        }

        $this->resTick = new Ticketing([
            'defaultType' => 'pending-reservations',
            'defaultValidityPeriod' => 900, // 15 min
            'defaultMaxConsumptionCount' => 1,
        ]);

        $pendingRes = $this->getPendingReservations();


        $nReservations = $this->countReservations();
        $seatsAvailable = $this->maxSeats - $nReservations - $pendingRes;
        if ($this->moreThanThreshold && ($seatsAvailable > $this->moreThanThreshold)) {
            $seatsAvailable = $this->lzy->trans->translateVariable('lzy-registration-more-than');
            $seatsAvailable = str_replace('$moreThanThreshold', $this->moreThanThreshold, $seatsAvailable);
        }

        if (!$this->responseToClient && ($seatsAvailable <= 0)) {
            return $this->lzy->trans->translateVariable('lzy-reservation-full', true);
        }


        // separate arguments for header and fields:
        $headArgs = [];
        $resSpecificArgs = [];
        $formElems = [];
        $formHint = '';
        $formFooter = '';
        $emailFound = false;
        foreach ($args as $key => $value) {
            if ($key === 'formHint') {
                $formHint = $value;

            } elseif ($key === 'formFooter') {
                $formFooter = $value;

            } elseif ($this->isHeadAttribute( $key )) {
                $headArgs[$key] = $value;

            } elseif (strpos(RESERVATION_SPECIFIC_ELEMENTS, ",$key,") !== false) {
                $resSpecificArgs[$key] = $value;

            } else {
                if (isset($value[ 0 ])) {
                    if (strpos(SUPPORTED_TYPES, $value[ 0 ]) !== false) {
                        $value['type'] = $value[ 0 ];
                        unset( $value[ 0 ] );
                    }
                }
                if (@$value['type'] === 'email') {
                    $emailFound = true;
                }
                $formElems[$key] = $value;
            }
        }
        $this->resSpecificArgs = $resSpecificArgs;

        $headArgs['formHeader'] = isset($headArgs['formHeader'])? $headArgs['formHeader']: '';
        if (strpos($headArgs['formHeader'], '{seatsAvailable}') !== 0) {
            $headArgs['formHeader'] = str_replace('{seatsAvailable}', $seatsAvailable, $headArgs['formHeader']);
        }

        if ($formHint) {
            $headArgs['options'] = @$headArgs['options']? $headArgs['options'].' norequiredcomment': 'norequiredcomment';
        }


        // create form head:
        $headArgs['type'] = 'form-head';
        if (!isset($headArgs['class'])) {
            $headArgs['class'] = 'lzy-reservation-form lzy-form lzy-form-colored lzy-encapsulated';
        }
        $str = parent::render( $headArgs );

        if (!$this->skipRenderingForm) {
            $resTickRec = [
                'formHash' => parent::getFormHash(),
                'file' => $this->dataFile,
                'deadline' => $this->deadline,
                'nSeats' => $this->maxSeatsPerReservation,
            ];
            $ticket = $this->resTick->createTicket($resTickRec);
        } else {
            $ticket = '';
        }
        // create form buttons:
        $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];

        $defaultFields = [
            [
                'type' => 'hidden',
                'label' => 'lzy-form lzy-reservation-ticket',
                'name' => '_res-ticket',
                'value' => $ticket,
            ],
            [
                'type' => 'text',
                'label' => '-lzy-reservation-first-name',
                'name' => 'first_name',
                'required' => true,
            ],
            [
                'type' => 'text',
                'label' => '-lzy-reservation-last-name',
                'name' => 'last_name',
                'required' => true,
            ],
        ];

//        if ($this->confirmationEmail && !$emailFound) {
        if ($this->emailField && !$emailFound) {
            $defaultFields[] =
                [
                    'type' => 'email',
                    'label' => '-lzy-reservation-email',
                    'name' => 'e-mail',
                    'required' => $this->requireEmail,
                    'info' => '-lzy-form-email-confirmation-info',
                ];
        }
        if ($this->maxSeatsPerReservation === 1) {
            $defaultFields[] =
                [
                    'type' => 'hidden',
                    'label' => '-lzy-reservation-count-label',
                    'name' => 'reservation-count',
                    'labelInOutput' => '-lzy-reservation-count-output-label',
                    'value' => 1,
                ];

        } else {
            $defaultFields[] =
                [
                    'type' => 'number',
                    'label' => '-lzy-reservation-count-label',
                    'name' => 'reservation-count',
                    'labelInOutput' => 'lzy-reservation-count-output-label',
                    'required' => true,
                    'min' => 1,
                    'max' => min($this->maxSeatsPerReservation, $seatsAvailable),
                    'value' => 1,
                ];
        }

        foreach ($defaultFields as $arg) {
            $str .= parent::render($arg);
        }

        // parse further arguments, interpret as form field definitions:
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
                $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Submit }},';
                $buttons["value"] .= 'submit,';
                $arg['type'] = 'button';

            } elseif (($label === 'cancel') || ($label === 'reset')) {
                $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Cancel }},';
                $buttons["value"] .= 'cancel,';
                $arg['type'] = 'button';

            } elseif (strpos('formName,mailto,mailfrom,legend,showData', $label) !== false) {
                die(__FILE__. ' '.__LINE__.' Error: clause should be obsolete...');
                // nothing to do
            } elseif (is_bool($arg)) {
                writeLog("Reservation(): unknown arg '$label' (probablt forgot to add it to RESERVATION_SPECIFIC_ELEMENTS)");
                exit;
            } else {
                $arg['label'] = $label;
                $str .= parent::render($arg);
            }
        }

        // inject formHint:
        if ($formHint && !$this->skipRenderingForm) {
            if (!preg_match('/<.+>/', $formHint)) {
                $formHint = "\t<div class='lzy-form-footer'>$formHint</div>\n";
            }
            $str .= str_replace(['&#39;','&#34;'], ['"', "'"], $formHint );
        }

        // add buttons, preset with default buttons if not defined:
        if (!$buttons["label"]) {
            $buttons = [ 'label' => 'Send,Cancel', 'type' => 'button', 'options' => 'submit,cancel' ];
        }
        $buttons["value"] = rtrim($buttons["value"], ',');
        $str .= parent::render($buttons);

        // inject formFooter:
        if ($formFooter && !$this->skipRenderingForm) {
            if (!preg_match('/<.+>/', $formFooter)) {
                $formFooter = "\t<div class='lzy-form-footer'>$formFooter</div>\n";
            }
            $str .= str_replace(['&#39;','&#34;'], ['"', "'"], $formFooter );
        }

        $str .= parent::render([ 'type' => 'form-tail' ]);

        return $str;
    } // render


    //----------------------------------------------------------------------
    private function evaluateClientData()
    {
        if (!isset($_POST) || !$_POST) {
            return;
        }
        $userSuppliedData = $_POST;
        $this->userSuppliedData = $userSuppliedData;
        $resHash = @$userSuppliedData['_res-ticket'];
        $tick = new Ticketing();
        $tick->deleteTicket($resHash);

        if (@$userSuppliedData['_lizzy-form-cmd'] === '_clear_') {     // _clear_ -> just clear reservation ticket
            exit;
        }


        $currForm = parent::restoreFormDescr( $userSuppliedData['_lizzy-form'] );
        $formId = $currForm? $currForm->formId: 'generic';

        // deadline for reservations: reject if deadline has passed:
        if ($this->deadline && ($this->deadline < time())) {
            $this->errorDescr[ $formId ]['_announcement_'] = '{{ lzy-reservation-deadline-passed }}';
            $this->skipRenderingForm = true;
            return;
        }

        $requestedSeats = $this->getUserSuppliedValue("reservation-count");
        if ($requestedSeats > $this->maxSeatsPerReservation) {
            // this case is likely result of tampering, so react as if fully booked...
            $this->errorDescr[ $formId ]['_announcement_'] = '{{ lzy-reservation-full-error }}';
            $this->skipRenderingForm = true;
            return;
        }

        $existingReservations = $this->countReservations();
        if (($existingReservations + $requestedSeats) > $this->maxSeats) {
            $this->errorDescr[ $formId ]['_announcement_'] = '{{ lzy-reservation-full-error }}';
            $this->skipRenderingForm = true;
            return;
        }

        $this->evaluateUserSuppliedData();
    } // evaluateClientData




    private function countReservations()
    {
        $data = $this->ds->read();
        if (!$data) {
            return 0;
        }

        $countInx = false;  // index of nSeats field
        $count = 0;
        foreach ($data as $key => $rec) {
            if ($key === '_meta_') {
                continue;
            }
            if (!$countInx) {
                foreach ($rec as $k => $v) {
                    if (strpos($k, 'reservation-count') === 0) {
                        $countInx = $k;
                        break;
                    }
                }
                if (!$countInx) {
                    die("Error Macro Reservation: elem 'reservation-count' not found in reservation data");
                }
            }
            if (isset($rec[$countInx])) {   // nSeats field
                $count += intval($rec[$countInx]);
            }
        }
        return $count;
    } // countReservations



    private function getPendingReservations()
    {
        $hash = @$_SESSION["lizzy"][ $_SESSION["lizzy"]["pathToPage"] ]["tickets"]["pending-reservations"];
        $pendingRes = $this->resTick->sum('nSeats', $hash);
        // if reloading page one ticket is pending for ourself, so discount it:
        if (isset($_SESSION['lizzy']['reservation'][$this->inx])) {
            $pendingRes = max(0, $pendingRes - $this->maxSeatsPerReservation);
        }
        return $pendingRes;
    } // getPendingReservations

} // class reservation




function renderReservationsHelp()
{

    return <<<'EOT'

## Help on macro reservation()

Macro ``reseravtion()`` accepts all arguments that ``formhead()`` and ``formelem()`` do.

### Deviations:

formHeader:
: Text rendered above the form. Patter ``{seatsAvailable}`` will be replaced with the actual value.
: Will be hidden upon successful completion of form entry.

{{ vgap }}

{{ formhead( help ) }}
{{ vgap }}
{{ formelem( help ) }}

EOT;

}