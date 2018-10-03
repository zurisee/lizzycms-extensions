<?php
/*
 *	Lizzy - forms rendering module
*/

define('CSV_SEPARATOR', ';');
define('CSV_QUOTE', 	'"');
define('DATA_EXPIRATION_TIME', false);

mb_internal_encoding("utf-8");

require_once(SYSTEM_PATH.'form-def.class.php');



class Forms
{
	private $page = false;
	private $formDescr = [];		// FormDescriptor -> all info about all forms, stored in $_SESSION['lizzy']['formDescr']
	private $currFormIndex = null;	// index of current form within array of forms
	private $currForm = null;		// shortcut to $formDescr[ $currFormIndex ]
	private $currRecIndex = null;	// index of current element within array of form-elements
	private $currRec = null;		// shortcut to $currForm->formElements[ $currRecIndex ]
	private $currFormData = null;	// shortcut to previously entered form data in $formDescr[ <current form> ]->formData
	private $currRecData = null;	// shortcut data for current form-element: $formDescr[ <current form> ]->formData[ $currRecIndex ]

//-------------------------------------------------------------
	public function __construct($page, $transvar)
	{
		$this->transvar = $transvar;
		$this->page = $page;
		$this->inx = -1;
	} // __construct

    
//-------------------------------------------------------------
    public function render($args)
    {
        if (isset($args[0]) && ($args[0] == 'help')) {
            return false;
        }

        $this->inx++;
        $this->parseArgs($args); 
        
        switch ($this->currRec->type) {
            case 'form-head':
                return $this->formHead($args);
            
            case 'text':
                $elem = $this->renderTextInput();
                break;
            
            case 'password':
                $elem = $this->renderPassword();
                break;
            
            case 'email':
                $elem = $this->renderEMailInput();
                break;
            
            case 'textarea':
                $elem = $this->renderTextarea();
                break;
            
            case 'radio':
                $elem = $this->renderRadio();
                break;
            
            case 'checkbox':
                $elem = $this->renderCheckbox();
                break;
            
            case 'button':
                $elem = $this->renderButtons();
                break;

            case 'date':
                $elem = $this->renderDate();
                break;

            case 'month':
                $elem = $this->renderMonth();
                break;

            case 'number':
                $elem = $this->renderNumber();
                break;

            case 'range':
                $elem = $this->renderRange();
                break;

            case 'tel':
                $elem = $this->renderTel();
                break;

            case 'file':
                $elem = $this->renderFileUpload();
                break;

            case 'form-tail':
				return $this->formTail();

            default:
                $elem = "<p>Error: form type unknown: '{$this->type}'</p>\n";
        }

		if (isset($this->currRec->wrapperclass) && ($this->currRec->wrapperclass)) {
	        $class = " class='field-wrapper field-type-{$this->currRec->type} {$this->currRec->wrapperclass}'";
		} else {
	        $class = $this->getClass('field-wrapper field-type-'.$this->currRec->type);
		}
		$out = "\t\t<div $class>\n$elem\t\t</div><!-- /field-wrapper -->\n\n";
        return $out;
    } // render
    

//-------------------------------------------------------------
    private function formHead($args)
    {
		$this->currForm->class = $class = (isset($args['class'])) ? $args['class'] : translateToIdentifier($this->currForm->formName);
		$_class = " class='lzy-form $class'";
		
		$this->currForm->method = (isset($args['method'])) ? $args['method'] : 'post';
		$_method = " method='{$this->currForm->method}'";

		$this->currForm->action = (isset($args['action'])) ? $args['action'] : '';
		$_action = ($this->currForm->action) ? " action='{$this->currForm->action}'" : '';

		$this->currForm->mailto = (isset($args['mailto'])) ? $args['mailto'] : '';
		$this->currForm->mailfrom = (isset($args['mailfrom'])) ? $args['mailfrom'] : '';
		$this->currForm->process = (isset($args['process'])) ? $args['process'] : '';
		$this->currForm->action = (isset($args['action'])) ? $args['action'] : '';
		$this->currForm->class = (isset($args['class'])) ? $args['class'] : '';
		$this->currForm->next = (isset($args['next'])) ? $args['next'] : './';

		$time = time();

		$out = "<form$_class$_method$_action>\n";
		$out .= "\t\t<input type='hidden' name='lizzy_form' value='$class' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_time' name='lizzy_time' value='$time' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_next' value='{$this->currForm->next}' />\n";
		return $out;
	} // formHead


//-------------------------------------------------------------
	private function renderTextarea()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<textarea id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} >{$this->currRec->value}</textarea>\n";
        return $out;
    } // renderTextarea


//-------------------------------------------------------------
    private function renderEMailInput()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='email' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderEMailInput

//-------------------------------------------------------------
	private function checkEMailInput($value)
	{
		if (is_legal_email_address($value)) {
			$this->currRec->errMsg .= "{{ Syntax-Error in E-Mail }}";
			return false;
		}
		return true;
	}


//-------------------------------------------------------------
    private function renderRadio()
    {
        $values = ($this->currRec->value) ? preg_split('/\s*\|\s*/', $this->currRec->value) : [];
        $groupName = translateToIdentifier($this->currRec->label);
		$checkedElem = (isset($this->userSuppliedData[$groupName])) ? $this->userSuppliedData[$groupName] : false;
        $out = "\t\t\t<fieldset class='label radio-label'><legend>{$this->currRec->label}</legend>\n";
        foreach($values as $value) {
            $val = translateToIdentifier($value);
            $id = "fld_$val";
            
			$checked = ($checkedElem && ($val == $checkedElem)) ? ' checked' : '';
            $out .= "\t\t\t<div class='$id radio-elem choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='radio' name='$groupName' value='$val'$checked /><label for='$id'>$value</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio


//-------------------------------------------------------------
    private function renderCheckbox()
    {
        $values = ($this->currRec->value) ? preg_split('/\s*\|\s*/', $this->currRec->value) : [];
        $groupName = translateToIdentifier($this->currRec->label);
        $out = "\t\t\t<fieldset class='label checkbox-label'><legend>{$this->currRec->label}</legend>\n";
		
		$data = isset($this->userSuppliedData[$groupName]) ? $this->userSuppliedData[$groupName] : [];
        foreach($values as $value) {
            $val = translateToIdentifier($value);
            $id = "fld_{$groupName}_$val";
			
			$checked = ($data && in_array($value, $data)) ? ' checked' : '';
            $out .= "\t\t\t<div class='$id checkbox-elem choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='checkbox' name='{$groupName}[]' value='$value'$checked /><label for='$id'>$value</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio


//-------------------------------------------------------------
    private function renderTextInput()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='text' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderTextInput


//-------------------------------------------------------------
    private function renderPassword()
    {
        $input = "\t\t\t<input type='password' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} aria-invalid='false' aria-describedby='password-hint' value='{$this->currRec->value}' />\n";
        $hint = <<<EOT
            <div id="password-hint">{{password-hint}}</div>
            <label class='pw-toggle' for="showPassword"><input type="checkbox" id="showPassword"> {{ show password }}</label>
EOT;
        $out = $this->getLabel();
        $out .= $input . $hint;
        return $out;
    } // renderPassword


//-------------------------------------------------------------
    private function renderDate()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='date' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderDate


//-------------------------------------------------------------
    private function renderMonth()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='month' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderMonth

//-------------------------------------------------------------
    private function renderNumber()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='number' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderNumber


//-------------------------------------------------------------
    private function renderRange()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='range' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderRange


//-------------------------------------------------------------
    private function renderTel()
    {
        $out = $this->getLabel();
        $out .= "\t\t\t<input type='tel' id='fld_{$this->currRec->name}'{$this->currRec->inpAttr} value='{$this->currRec->value}' />\n";
        return $out;
    } // renderTel


//-------------------------------------------------------------
    private function renderFileUpload()
    {
        $inx = $this->inx;
		$id = isset($this->args['id']) ? $this->args['id'] : $this->currRec->name.$inx;
		$server = isset($this->args['server']) ? $this->args['server'] : '~sys/file-upload/_upload_server.php';

        $targetPath = fixPath($this->currRec->uploadPath);
        $targetPath1 = resolvePath($targetPath);
        $pagePath = $GLOBALS['globalParams']['pagePath'];
        if (!isset($_SESSION['lizzy'][$pagePath]['uploadPath'])) {
            $_SESSION['lizzy'][$pagePath]['uploadPath'] = '';
        }
        if (strpos($_SESSION['lizzy'][$pagePath]['uploadPath'], $targetPath1) === false) {
            $_SESSION['lizzy'][$pagePath]['uploadPath'] .= ",$targetPath1,";
        }


        $list = "\t<div>{{ Uploaded file list }}</div>\n";  // assemble list of existing files
        $list .= "<ul>";
        $dispNo = ' style="display:none;"';
		if (isset($this->currRec->showexisting) && $this->currRec->showexisting) {
			$files = getDir($targetPath1.'*');
			foreach ($files as $file) {
				if (is_file($file)) {
					$file = basename($file);
					if (preg_match("/\.(jpg|gif|png)$/i", $file)) {
						$list .= "<li><span>$file</span><span><img src='{$targetPath}thumbnail/$file'></span></li>";
					} else {
						$list .= "<li><span>$file</span></li>";
					}
				}
			}
            $dispNo = '';
        }
        $list .= "</ul>\n";
		if ($this->currRec->label) {
		    $label = $this->currRec->label;
        } else {
            $label = '{{ Upload File(s) }}';
        }
		$out = '';
        $out .= <<<EOT
        
            <input type="hidden" name="form-upload-path" value="$targetPath1" />
            <label class="$id lzy-form-file-upload-label lzy-button" for="$id">$label<input id="$id" class="lzy-form-file-upload" type="file" name="files[]" data-url="$server" multiple /></label>

			<div class='progress-indicator progress-indicator$inx' style="display: none;">
				<progress id="progressBar$inx" class="progressBar" max='100' value='0'>
					<!-- Fallback -->
					<div id="progressBarFallback1-$inx"><span id="progressBarFallback2-$inx">&#160;</span></div>
				</progress>
				<div><span aria-live="polite" id="progressPercent$inx"></span></div>
			</div>

			<div id='uploaded$inx' class='uploaded'$dispNo >$list</div>

EOT;

		$jq = <<<EOT

	var d = new Date();
	var t = d.getTime();
	$('#$id').fileupload({
		dataType: 'json',
		
		progressall: function (e, data) {
		    mylog('processing upload');
		    $('.progress-indicator$inx').show();
			var progress = parseInt(data.loaded / data.total * 100, 10);
			$('#progressBar$inx').val(progress);
			var d = new Date();
			t1 = d.getTime();
			if (((t1 - t) > 3000) && (progress < 100)) {
				t = t1;
				$('#progressPercent$inx').text( progress + '%' );
			}
			if (progress == 100) {
				$('#progressPercent$inx').text( progress + '%' );
			}
		},

		done: function (e, data) {
		    mylog('upload accomplished');
			$.each(data.result.files, function (index, file) {
				if (file.name.match(/\.(jpg|gif|png)$/i)) {
					var img = '<img src="{$targetPath}thumbnail/' + file.name + '" />';
				} else {
					var img = '';
				}
				var line = '<li><span>' + file.name + '</span><span>' + img + '</span></li>';
				$('#uploaded$inx').show();
				$('#uploaded$inx ul').append(line);
			});
		}
	});

EOT;
		$this->page->addJq($jq);

		if (!isset($this->fileUploadInitialized)) {
			$this->fileUploadInitialized = true;

			$this->page->addJqFiles(['~sys/third-party/jquery-upload/js/vendor/jquery.ui.widget.js',
								'~sys/third-party/jquery-upload/js/jquery.iframe-transport.js',
								'~sys/third-party/jquery-upload/js/jquery.fileupload.js']);
		}
		
        return $out;
    } // renderFileUpload



//-------------------------------------------------------------
    private function renderButtons()
    {
        $indent = "\t\t";
		$label = $this->currRec->label;
		$value = (isset($this->currRec->value) && $this->currRec->value) ? $this->currRec->value : $label;
		$out = '';
		$class = $this->getClass('form-button');
		if (strpos($value, ',') === false) {
			$id = 'btn_'.$this->currForm->formId.'_'.translateToIdentifier($value);
			$out .= "$indent<output id='{$this->currForm->formId}_error-msg'  aria-live='polite' aria-relevant='additions'></output>\n";
			$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";

		} else {
			$types = explode(',', $value);
			$labels = explode(',', $label);
			
			foreach ($types as $i => $type) {
				$id = 'btn_'.$this->currForm->formId.'_'.translateToIdentifier($type);
				$label = (isset($labels[$i])) ? $labels[$i] : $type;
				if (stripos($type, 'submit') !== false) {
					$out .= "$indent<output id='{$this->currForm->formId}_error-msg'  aria-live='polite' aria-relevant='additions'></output>\n";
					$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";
					
				} elseif (stripos($type, 'reset') !== false) {
					$out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
					
				} else {
					$out .= "$indent<input type='button' id='$id' value='$label' $class />\n";
				}
			}
		}

        return $out;
    } //renderButtons

	
//-------------------------------------------------------------
	private function formTail()
    {
		$this->saveFormDescr();
		if (isset($this->page->formEvalResult)) {
			return "</form>\n".$this->page->formEvalResult;
		} else {
			return "</form>\n";
		}
	} // formTail

    
//-------------------------------------------------------------
    private function getLabel($id = false)
    {
		$id = ($id) ? $id : "fld_{$this->currRec->name}";
        $requiredMarker = $this->getRequiredMarker();
		$label = $this->currRec->label;
		if ($this->translateLabel) {
		    $hasColon = (strpos($label, ':') !== false);
		    $hasAsterisk = (strpos($label, '*') !== false);
            $label = trim(str_replace([':', '*'], '', $label));
            $label = "{{ $label }}";
            if ($hasColon) {
                $label .= ':';
            }
            if ($hasAsterisk) {
                $label .= ' *';
            }
        } else {
            if ($requiredMarker && (strpos($label, ':') !== false)) {
                $label = rtrim($label, ':').' '.$requiredMarker.':';
            } else {
                $label .= ' '.$requiredMarker;
            }
        }

        return "\t\t\t<label for='$id'>$label</label>\n";
    } // getLabel


//-------------------------------------------------------------
    private function getRequiredMarker()
    {
		$required = $this->currRec->required;
        return ($required) ? "<span class='required-marker' aria-hidden='true'>{$this->currRec->requiredMarker}</span>" : '';
    } // getRequiredMarker


//-------------------------------------------------------------
    private function getClass($class = '')
    {
        $out = ($this->currRec->class . $class) ? " class='".trim($this->currRec->class .' '. $class). "'" : '';
        return trim($out);
    } // getClass
    
    
//-------------------------------------------------------------
    private function parseArgs($args)
    {
		if (!$this->currForm) {	// first pass -> must be type 'form-head' -> defines formId
			if ($args['type'] != 'form-head') {
                fatalError("Error: syntax error \nor form field definition encountered without previous element of type 'form-head'", 'File: '.__FILE__.' Line: '.__LINE__);
			}
            $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form'.($this->inx + 1);
	        $formId = (isset($args['class'])) ? $args['class'] : translateToIdentifier($label);

			$this->formDescr[ $formId ] = new FormDescriptor;
			$this->currForm = &$this->formDescr[ $formId ];
			$this->currForm->formId = $formId;
			
			$this->currForm->formName = $label;

			$this->currForm->formData['labels'] = [];
			$this->currForm->formData['names'] = [];
			$this->userSuppliedData = $this->getUserSuppliedData($formId);

		} else {
            $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form-Elem'.($this->inx + 1);
            $this->translateLabel = (isset($args['translateLabel'])) ? $args['translateLabel'] : false;
			$formId = $this->currForm->formId;
			$this->currForm = &$this->formDescr[ $formId ];
		}
		

		$type = (isset($args['type'])) ? $args['type'] : 'text';
		if ($args['type'] == 'form-tail') {	// end-element is exception, doesn't need a label
			$label = 'form-tail';
		}
		if ($type == 'form-head') {
			$this->currRec = new FormElement;
			$this->currRec->type = 'form-head';
			return;
		}

		
		$elemId = translateToIdentifier($label);
		
		$this->currForm->formElements[ $elemId ] = new FormElement;
		$this->currRec = &$this->currForm->formElements[ $elemId ];
		$rec = &$this->currRec;
		
		$rec->type = $type;

		if (strpos($label, '*')) {
			$label = trim(str_replace('*', '', $label));
			$args['required'] = true;
		}
		$rec->label = $label;

		if (isset($args['name'])) {
            $rec->name = $name = $args['name'];
        } else {
            $rec->name = $name = translateToIdentifier($label);
        }
        $_name = " name='$name'";


		if (isset($args['required'])) {
            $rec->required = true;
            $rec->requiredMarker = (is_bool($args['required'])) ? '*' : $args['required'];
            $required = " required aria-required='true'";
        } else {
            $rec->required = false;
            $required = '';
        }
	
        if (isset($args['value'])) {
			$rec->value = $args['value'];

		} elseif (isset($this->userSuppliedData[$rec->name])) {
			$rec->value = $this->userSuppliedData[$rec->name];

		} else {
			$rec->value = '';
		}

        if (isset($args['path'])) {
		    $rec->uploadPath = $args['path'];
        } else {
            $rec->uploadPath = '~/upload/';
        }


            if ($type == 'form-head') {
			$this->currForm->formData['labels'][0] = 'Date';
			$this->currForm->formData['names'] = [];
		} elseif (($type != 'button') && ($type != 'form-tail')) {
			$rec->shortLabel = (isset($args['shortlabel'])) ? $args['shortlabel'] : $label;
			if ($type == 'checkbox') {
				$checkBoxLabels = ($rec->value) ? preg_split('/\s*\|\s*/', $rec->value) : [];
				array_unshift($checkBoxLabels, $rec->shortLabel);
				$this->currForm->formData['labels'][] = $checkBoxLabels;
			} else {
				$this->currForm->formData['labels'][] = $rec->shortLabel;
			}
			$this->currForm->formData['names'][] = $name;
		}

        $rec->placeholder = (isset($args['placeholder'])) ? $args['placeholder'] : '';
        $rec->class = (isset($args['class'])) ? $args['class'] : '';
        
        $inpAttr = '';
        foreach (['min', 'max', 'pattern', 'value', 'placeholder'] as $attr) {
            if (isset($args[$attr])) {
                if (($type == 'checkbox') || ($type == 'radio')) {
                    continue;
                }
                if (($type == 'textarea') && ($attr == 'value')) {
                    continue;
                }
                $inpAttr .= " $attr='{$args[$attr]}'";
            }
        }
        $rec->inpAttr = $_name.$inpAttr.$required;
		
		foreach($args as $key => $arg) {
			if (!isset($rec->$key)) {
				$rec->$key = $arg;
			}
		}
    } // parseArgs


//-------------------------------------------------------------
	private function extractArgs($args)
	{// strip superfluous chars and json-decode data

        $args = trim(html_entity_decode($args));	// translate html special chars
        $args = preg_replace(['/\s*,+$/', '/^"/', '/"$/'], '', $args);// remove blanks, leading and trailing quotes
        $args = preg_replace("/,\s*\}/", '}', $args);	// remove trailing ','
        $args = preg_replace("/'/", '"', $args);	// make sure data is quoted with " (according to json standard)
        $args = $this->args = json_decode($args, true); // json-decode
		
		return $args;
	} // extractArgs
	
	
//-------------------------------------------------------------
	private function saveFormDescr()
	{
		$formId = $this->currForm->formId;
		$_SESSION['lizzy'][$formId] = serialize($this->formDescr);
	} // saveFormDescr


//-------------------------------------------------------------
	private function restoreFormDescr($formId)
	{
		return (isset($_SESSION['lizzy'][$formId])) ? unserialize($_SESSION['lizzy'][$formId]) : null;
	} // restoreFormDescr


//-------------------------------------------------------------
	private function saveUserSuppliedData($formId, $userSuppliedData)
	{
		$_SESSION['lizzy'][$formId.'_userData'] = serialize($userSuppliedData);
	} // saveUserSuppliedData


//-------------------------------------------------------------
	private function getUserSuppliedData($formId)
	{
		return (isset($_SESSION['lizzy'][$formId.'_userData'])) ? unserialize($_SESSION['lizzy'][$formId.'_userData']) : null;
	} // getUserSuppliedData


//-------------------------------------------------------------
    private function prepareFieldValue()
    {
		if ((!$this->value) && (isset($this->data['$this->name']))) {
			$this->value = $this->data['$this->name'];
		}
	} // prepareFieldValue


//-------------------------------------------------------------
    public function evaluate()
    {
    	$this->userSuppliedData = $userSuppliedData = (isset($_GET['lizzy_form'])) ? $_GET : $_POST;
		if (isset($userSuppliedData['lizzy_form'])) {
			$this->formId = $formId = $userSuppliedData['lizzy_form'];
		} else {
			$this->clearCache();
			return false;
            fatalError("ERROR: unexpected value received from browser", 'File: '.__FILE__.' Line: '.__LINE__);
		}
		$dataTime = (isset($userSuppliedData['lizzy_time'])) ? $userSuppliedData['lizzy_time'] : 0;
		
		if ($dataTime > 0) {
			$this->saveUserSuppliedData($formId, $userSuppliedData);
		} else {
			$this->clearCache();
			return false;
		}
		$formDescr = $this->restoreFormDescr($formId);
		$currFormDescr = &$formDescr[$formId];

        $str = '';
		$next = $currFormDescr->next;
		
		$userEval = isset($currFormDescr->process) ? $currFormDescr->process : '';
		if ($userEval) {
			$result = $this->transvar->doUserCode($userEval);
			if ($result) {
				$str = evalForm($userSuppliedData, $currFormDescr);
			} else {
                fatalError("Warning: executing code '$userEval' has been blocked; modify 'config/config.yaml' to fix this.", 'File: '.__FILE__.' Line: '.__LINE__);
			}
		} else {
			$str = $this->defaultFormEvaluation($currFormDescr);
		}
		
		$this->page->addCss(".$formId { display: none; }");
		
		$str .= "<div class='form-continue'><a href='{$next}'>{{ form-continue }}</a></div>\n<form class='$formId'>";
		return $str;
    } // evaluate


//-------------------------------------------------------------
	private function defaultFormEvaluation($currFormDescr)
	{
		$formId = $this->formId;

		$formName = $currFormDescr->formName;
		$mailto = $currFormDescr->mailto;
		$mailfrom = $currFormDescr->mailfrom;
		$formData = &$currFormDescr->formData;
		$labels = &$formData['labels'];
		$names = &$formData['names'];
		$userSuppliedData = $this->userSuppliedData;
		
		$str = "$formName\n===================\n\n";
		foreach($names as $i => $name) {
			if (is_array($labels[$i])) {
				$label = $labels[$i][0];
			} else {
				$label = $labels[$i];
			}
			$value = (isset($userSuppliedData[$name])) ? $userSuppliedData[$name] : '';
			if (is_array($value)) {
				$value = implode(', ', $value);
			} else {
				$value = str_replace("\n", "\n\t\t\t", $value);
			}
			$str .= mb_str_pad($label, 22, '.').": $value\n\n";
		}
		$str = trim($str, "\n\n");
		$this->saveCsv($currFormDescr);
		
		$serverName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost';
		$remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
		$localCall = (($serverName == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));

		if ($localCall) {
			$out = "<div class='form-response'>\n<p>{{ form-data-received-ok }}</p>\n<hr /><pre>\n$str\n</pre>\n</div>\n";
		} elseif ($mailto) {
			$subject = "[$formName] user data received";
			$this->sendMail($mailto, $mailfrom, $subject, $str);
			$out = '{{ form-data-received-ok }}';
		} else {
			$out = '{{ form-data-received-ok }}';
		}
		
		return $out;
	} // defaultFormEvaluation()


//-------------------------------------------------------------
	private function saveCsv($currFormDescr)
	{
		$cvsHead = '';
		$cvs = '';
		$quoteChar = CSV_QUOTE;
		$formId = $currFormDescr->formId;
		$cvsHead = "{$quoteChar}Timestamp$quoteChar".CSV_SEPARATOR;;

		$fileName = resolvePath("~page/{$formId}_data.csv");
		$labels = $currFormDescr->formData['labels'];
		$names = $currFormDescr->formData['names'];
		$userSuppliedData = $this->userSuppliedData;
		if (!file_exists($fileName) || (file_get_contents($fileName) == '')) {
			foreach($labels as $label) {
				if (is_array($label)) {
					for ($i=1;$i<sizeof($label); $i++) {
						$l = $label[$i];
						$cvsHead .= "$quoteChar$l$quoteChar".CSV_SEPARATOR;
					}
				} else {
					$label = trim($label, ':');
					$cvsHead .= "$quoteChar$label$quoteChar".CSV_SEPARATOR;
				}
			}
			$cvsHead = rtrim($cvsHead, CSV_SEPARATOR);
			file_put_contents($fileName, $cvsHead."\n", FILE_APPEND);
		}
		
		$cvs.= $quoteChar.timestamp().$quoteChar.CSV_SEPARATOR;
		foreach($names as $i => $name) {
			$value = (isset($userSuppliedData[$name])) ? $userSuppliedData[$name] : '';
			if (is_array($value)) {
				$labs = $labels[$i];
				for ($j=1; $j<sizeof($labs); $j++) {
					$l = $labs[$j];
					$val = (in_array($l, $value)) ? '1' : '0';
					$cvs .= "$quoteChar$val$quoteChar".CSV_SEPARATOR;
				}
			} else {
				$cvs .= "$quoteChar$value$quoteChar".CSV_SEPARATOR;
			}
		}
		$cvs = rtrim($cvs, CSV_SEPARATOR);
		file_put_contents($fileName, $cvs."\n", FILE_APPEND);
	} // saveCsv


//-------------------------------------------------------------
	private function sendMail($to, $from, $subject, $message)
	{
		$from  = ($from) ? $from : 'host@domain.com';
		$headers = "From: $from\r\n" .
			'X-Mailer: PHP/' . phpversion();
		
		if (!mail($to, $subject, $message, $headers)) {
            fatalError("Error: unable to send e-mail", 'File: '.__FILE__.' Line: '.__LINE__);
		}
	} // sendMail
	
//-------------------------------------------------------------
	public function clearCache()
	{
		if (isset($this->formId)) {
			unset($_SESSION['lizzy'][$this->formId.'_userData']);
		}
	}
	
} // Forms


