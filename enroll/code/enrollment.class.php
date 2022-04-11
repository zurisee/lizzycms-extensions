<?php

define('ENROLLMENT_SPECIFIC_ELEMENTS', ',nNeeded,nReserve,listname,header,tooltips,'.
    'file,globalFile,logAgentData,freezeTime,editable,hideNames,unhideNamesForGroup,notify,notifyFrom,'.
    'scheduleAgent,n_needed,n_reserve,');

require_once SYSTEM_PATH.'forms.class.php';
$GLOBALS['lizzy']['enrollCnt'] = 0;

class Enrollment extends Forms
{
    public function __construct($lzy, $inx, $args)
    {
        foreach ($args as $key => $arg) {
            if (is_integer($key)) {
                unset($args[ $key ]);
            }
        }
        $GLOBALS['lizzy']['enrollCnt']++;
        $this->enrollInx = $GLOBALS['lizzy']['enrollCnt'];
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

        // determine where to store data:
        if ($args['globalFile']) {
            $GLOBALS['lizzy']['enrollFile'] = $args['globalFile'];
        }
        if ($args['file']) {
            $this->file = $args['file'] ? $args['file'] : '~page/enroll.yaml';
        } elseif (isset($GLOBALS['lizzy']['enrollFile']) && $GLOBALS['lizzy']['enrollFile']) {
            $this->file = $GLOBALS['lizzy']['enrollFile'];
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

        $this->admin_mode = $GLOBALS['lizzy']['isAdmin'];
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
                $t =  time() + $this->freezeTime;
                $d = strftime('%x', $t);
                $h = substr(strftime('%X', $t), 0, -3);
                $this->freezeTimeStr = "$d, $h";
                $this->freezeTime = -$this->freezeTime;
            }
        }

        $this->err_msg = '';
        $this->name = '';
        $this->email = '';
        $this->action = '';
        $this->focus = '';
        $this->show_result = false;

        $this->enroll_list_id = translateToIdentifier($this->listname);
        $this->enroll_list_name = str_replace("'", '&prime;', $this->listname);

        $this->data_path = dir_name($this->file);
        $this->dataFile = resolvePath( $this->file, true );
        $this->logFile = resolvePath($this->data_path.ENROLL_LOG_FILE);

        preparePath($this->dataFile);

        parent::__construct($lzy);
    } // __construct




    public function render( $args = null )
    {
        $this->formAnnouncement = @$this->errorDescr[ $this->enrollInx ]['_announcement_'];
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

        $tickRec = [
            "set$this->enrollInx" => [
                    '_dataSource' => $this->dataFile,
                    '_dataKey' => "{$this->enroll_list_id},#",
                ]
        ];
        $tck = new Ticketing(['defaultMaxConsumptionCount' => false, 'defaultType' => 'enroll']);
        $enrollHash = $tck->createTicket($tickRec, false);

        $attr = " data-datasrc-ref='$enrollHash:set$this->enrollInx'";
        $cls = $this->customFields? ' lzy-enroll-auxfields': '';


        // assemble output:
        $out0 = "\n\t<div class='{$this->enroll_list_id} lzy-enrollment-list$cls' data-dialog-ref='$hash'$attr>\n";

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
        }

        if ($this->formAnnouncement) {
            $this->lzy->page->addPopup($this->formAnnouncement);
        }

        return $out0;
    } // render



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

        // in admin-mode add extra column showing EMail (which is otherwise hidden):
        if ($this->admin_mode) {
            array_unshift($this->enrollSpecificElems, 'EMail');
        }

        // loop over list:
        for ($n = 0; $n < $nn; $n++) {
            $res = ($n >= $this->nNeeded) ? ' lzy-enroll-reserve-field' : '';
            $num = "<span class='lzy-num'>" . ($n + 1) . ":</span>";

            $rec = &$existingData[$n];

            if (isset($rec['Name'])) {    // Name exists -> delete
                $name = $rec['Name'];
                // $email = @$rec['EMail'];
                if (!$this->admin_mode) {
                    $name = $this->hideName($name);
                }

                if ($this->customFields && $this->editable) {
                    $tooltip = '{{ lzy-enroll-modify-entry }}';
                    $icon = "<span class='lzy-enroll-modify'>&#9998;</span>";
                } else {
                    $tooltip = '{{ lzy-enroll-delete-entry }}';
                    $icon = "<span class='lzy-enroll-del'>−</span>";
                }
                if ($this->admin_mode || $this->isInTime($rec)) {
                    $a = "<a href='#' title='$tooltip'>\n\t\t\t\t  <span class='lzy-enroll-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
                    $class = 'lzy-enroll-del-field';
                } else {
                    $a = "<span class='lzy-enroll-name'>$name</span>";
                    $class = 'lzy-enroll-frozen-field';
                }

            } else {            // add
                if (!$new_field_done) {
                    $name = '{{ lzy-enroll-add-text }}';
                    $icon = "<span class='lzy-enroll-add'>+</span>";
                    $a = "<a href='#' title='{{ lzy-enroll-new-name }}'>\n\t\t\t\t  <span class='lzy-enroll-name'>$name</span>\n\t\t\t\t  $icon\n\t\t\t\t</a>";
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
                $cls0 = "{$tooltipCls}lzy-col".($i+3);
                $cls = "{$tooltipCls}$cls0";
                $name = str_replace(' ', '_', $custField);
                $name = preg_replace("/[^[:alnum:]_-]/m", '', $name);	// remove any non-letters, except _ and -
                $val = isset($rec[$name]) && $rec[$name] ? $rec[$name] : '&nbsp;';
                $aux .= "\n\t\t\t<div class='lzy-enroll-aux-field $cls' title='$val'>\n\t\t\t\t$val\n\t\t\t</div>";
                $hdr .= "\n\t\t\t<div class='lzy-enroll-aux-field $cls0'>$custField</div>";
            }
            $out .= "\t\t<div class='lzy-enroll-row$res'$recKey>\n$rowContent$aux\n\t\t</div><!-- /lzy-enroll-row -->\n\n";
        }
        return array($out, $hdr);
    } // renderTable



    private function renderDialog()
    {
        list($dialog, $hash) = $this->_renderDialog();
        if (isset($GLOBALS['lizzy']['enroll_form_created'][$hash])) {
            return $hash; // already rendered
        }
        $GLOBALS['lizzy']['enroll_form_created'][$hash] = true;

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

        if ($this->freezeTime === 0) {
            $hash = 0;
        } else {
            $hash = 1;
        }
        if ($this->customFields) {
            $hash += crc32(json_encode($formElems));
        }
        $this->formHash = $hash;
        if (isset($GLOBALS['lizzy']['enroll_form_created'][$hash])) {
            return ['', $hash]; // already rendered
        }


        // render form-head:
        $args = [
            'type' => 'form-head',
            'file' => '~/'.$this->dataFile,
            'id'   => "lzy-enroll-form-$hash",
            'novalidate' => false,
            'responseViaSideChannels' => true,
            'translateLabels' => true,
            'dataKeyOverride' => "enrollment-list{$this->enrollInx},#",
            'recModifyCheck' => ($this->admin_mode? 'EMail':false),
            'is2Ddata' => false,
        ];
        $headArgs = array_merge($args, $headArgs);

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
                // replace %freezetime%:
                $formFooter = str_replace('%freezetime%', $this->freezeTimeStr, $formFooter);

            } elseif ($this->freezeTime === false) {
                $formFooter = '{{ lzy-enroll-add-comment-no-freeze }}';
            } else {
                $formFooter = '';
            }
            $formFooter = "<div class='lzy-enroll-comment lzy-enroll-add-comment' style='display: none'>$formFooter</div>\n";
            $formFooter .= "<div class='lzy-enroll-comment lzy-enroll-delete-comment' style='display: none'>{{ lzy-enroll-delete-comment }}</div>\n";
            $formFooter .= "<div class='lzy-enroll-comment lzy-enroll-modify-comment' style='display: none'>{{ lzy-enroll-modify-comment }}</div>\n";
        }

        if ($formFooter) {
            $headArgs['formFooter'] = $formFooter;
        }

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
        ];
        foreach ($defaultFields as $arg) {
            $str .= parent::render($arg);
        }

        if ($this->freezeTime !== 0) {
            $str .= parent::render([
                'type' => 'email',
                'label' => 'lzy-enroll-email',
                'name' => 'EMail',
                'required' => !$this->admin_mode,
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
                'options' => 'cancel,submit,delete',
                'label' => 'lzy-enroll-cancel,lzy-enroll-submit,lzy-enroll-delete-btn-short',
                'type' => 'button',
            ];
        }
        $str .= parent::render($buttons);

        $str .= parent::render([ 'type' => 'form-tail' ]);

        $form = <<<EOT



    <!-- === Enroll Dialog =================== -->
    <div id="lzy-enroll-dialog-$hash" class="lzy-enroll-dialog" style='display:none;'>
      <div>
$str
          <div class="lzy-enroll-add-title" style="display: none">{{ lzy-enroll-add-title }}</div>
          <div class="lzy-enroll-delete-title" style="display: none">{{ lzy-enroll-delete-title }}</div>
          <div class="lzy-enroll-modify-title" style="display: none">{{ lzy-enroll-modify-title }}</div>
      </div>
    </div><!-- /lzy-enroll-dialog -->

EOT;

        return [$form, $hash];
    } // _renderDialog


} // class Enrollment


