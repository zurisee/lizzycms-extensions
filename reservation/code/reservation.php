<?php

require_once SYSTEM_PATH.'forms.class.php';

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml", false), false, true );

define('RESERVATION_DATA_FILE','reservation.csv');
define('RESERVATION_LOG_FILE', 'reservation.log.txt');

$page->addModules('~ext/reservation/js/reservation.js, ~ext/reservation/css/reservation.css');


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
    protected $waitingListLength, $moreThanThreshold, $confirmationEmail;
    protected $timeout, $notify, $notifyFrom, $scheduleAgent, $reservationCallback;
    protected $formHash, $ds, $resTick, $resSpecificArgs;

    public function __construct($lzy, $args)
    {
        $this->args = $args;

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
        $this->timeout =                    @$args['timeout'];
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
        $this->handleClientData();
    //        $this->setupScheduler();
    } // __construct




    public function render( $args = null )
    {
        $args = $this->args;

        $this->resTick = new Ticketing([
            'defaultType' => 'pending-reservations',
            'defaultValidityPeriod' => $this->timeout,
            'defaultMaxConsumptionCount' => 1,
        ]);

        $pendingRes = $this->getPendingReservations();


        $nReservations = $this->countReservations();
        $seatsAvailable = $this->maxSeats - $nReservations - $pendingRes;
        if ($this->moreThanThreshold && ($seatsAvailable > $this->moreThanThreshold)) {
            $seatsAvailable = $this->lzy->trans->translateVariable('lzy-registration-more-than');
            $seatsAvailable = str_replace('$moreThanThreshold', $this->moreThanThreshold, $seatsAvailable);
        }

        if (($nReservations + $pendingRes) >= $this->maxSeats) {
            return $this->lzy->trans->translateVariable('lzy-reservation-full', true);
        }


        // additional reservation specific args:
        $resSpecificElements = ',deadline,maxSeats,maxSeatsPerReservation,'.
            'waitingListLength,moreThanThreshold,timeout,notify,'.
            'notifyFrom,scheduleAgent,logAgentData,';

        // separate arguments for header and fields:
        $headArgs = [];
        $resSpecificArgs = [];
        $formElems = [];
        $formHint = '';
        $formFooter = '';
        foreach ($args as $key => $value) {
            if ($key === 'formHint') {
                $formHint = $value;

            } elseif ($key === 'formFooter') {
                $formFooter = $value;

            } elseif ($this->isHeadAttribute( $key )) {
                $headArgs[$key] = $value;

            } elseif (strpos($resSpecificElements, ",$key,") !== false) {
                $resSpecificArgs[$key] = $value;

            } else {
                $formElems[$key] = $value;
            }
        }
        $this->resSpecificArgs = $resSpecificArgs;

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

        $resTickRec = [
            'formHash' => parent::getFormHash(),
            'file' => $this->dataFile,
            'deadline' => $this->deadline,
            'nSeats' => $this->maxSeatsPerReservation,
        ];
        if (!$this->skipRenderingForm) {
        $ticket = $this->resTick->createTicket($resTickRec);
        } else {
            $ticket = '';
        }
        // create form buttons:
        $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];

        $wrapperClass = ($this->maxSeatsPerReservation === 1) ? 'dispno': '';
        $defaultFields = [
            [
                'type' => 'hidden',
                'label' => 'lzy-form lzy-reservation-ticket',
                'name' => '_res-ticket',
                'value' => $ticket,
            ],
            [
                'type' => 'hidden',
                'label' => 'lzy-reservation-timeout',
                'class' => 'lzy-reservation-timeout',
                'name' => '_timeout',
                'value' => $this->timeout,
            ],
            [
                'type' => 'text',
                'label' => 'First Name',
            ],
            [
                'type' => 'text',
                'label' => 'Last Name',
                'required' => true,
            ],
        ];

        if ($this->confirmationEmail) {
            $defaultFields[] =
                [
                    'type' => 'email',
                    'label' => 'E-Mail',
                    'required' => true,
                ];
        }
        $defaultFields[] =
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
            ];

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

            } elseif ($label === 'cancel') {
                $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Cancel }},';
                $buttons["value"] .= 'cancel,';
                $arg['type'] = 'button';

            } elseif ($label === 'reset') {
                $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Reset }},';
                $buttons["value"] .= 'reset,';
                $arg['type'] = 'button';

            } elseif (strpos('formName,mailto,mailfrom,legend,showData', $label) !== false) {
                // nothing to do
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
    private function handleClientData()
    {
        if (!isset($_POST) || !$_POST) {
            return;
        }
        $userSuppliedData = $_POST;
        $cmd = @$userSuppliedData['_lizzy-form-cmd'];
        if ($cmd === '_clear_') {     // _clear_ -> clear reservation ticket
            $hash = $_SESSION["lizzy"][ $_SESSION["lizzy"]["pathToPage"] ]["tickets"]["pending-reservations"];
            $resTick = new Ticketing([
                'defaultType' => 'pending-reservations',
                'defaultValidityPeriod' => $this->timeout,
                'defaultMaxConsumptionCount' => 1,
            ]);
            $resTick->deleteTicket( $hash );
            exit;
        }

        $resHash = $userSuppliedData['_res-ticket_1'];

        $tick = new Ticketing();
        $rec = $tick->consumeTicket($resHash);

        $formHash = $rec["formHash"];
        $currForm = parent::restoreFormDescr( $formHash );

        if ($rec['deadline'] && ($rec['deadline'] < time())) {
            $this->errorDescr[$currForm->formId]['_announcement_'] = 'lzy-reservation-deadline-exceeded';
            $this->skipRenderingForm = true;
            $tick->deleteTicket($resHash);
            return;
        }

        if ($userSuppliedData['_lizzy-form-cmd'] === '_delete_') {
            $this->errorDescr[$currForm->formId]['_announcement_'] = 'lzy-reservation-aborted';
            $this->skipRenderingForm = true;
            $tick->deleteTicket($resHash);
            return;
        }

        $existingReservations = $this->countReservations();
        if ($existingReservations > $this->maxSeats) {
            $this->errorDescr[$currForm->formId]['_announcement_'] = 'lzy-reservation-full-error';
            $this->skipRenderingForm = true;
            $tick->deleteTicket($resHash);
            return;
        }

        $tick->deleteTicket($resHash);
        $this->evaluateUserSuppliedData();
    } // handle $_POST



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
        $hash = $_SESSION["lizzy"][ $_SESSION["lizzy"]["pathToPage"] ]["tickets"]["pending-reservations"];
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