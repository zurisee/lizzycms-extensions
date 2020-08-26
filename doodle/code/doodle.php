<?php

// @info: Renders a set of options and accepts the user's choice.

$macroName = basename(__FILE__, '.php');

$page->addCssFiles("~ext/$macroName/css/doodle.css");
$page->addJqFiles("~ext/$macroName/js/doodle.js");

$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml"), false, true);

if (isset($_POST['lzy-doodle-entry-name'])) {
    $name = get_post_data('lzy-doodle-entry-name');
    $type = get_post_data('lzy-doodle-type');
    if ($type == 'checkbox') {
        $n = get_post_data('lzy-doodle-number-of-options');
        $a = [];
        for ($i = 0; $i < $n; $i++) {
            $a[$i] = (get_post_data("lzy-doodle-entry-answer$i") == 'on') ? 1 : 0;
        }
    } else {
        $a = get_post_data('lzy-doodle-entry-answer');
    }
    $file0 = get_post_data('lzy-doodle-filename');
    $file = resolvePath($file0, true);
    $data = getYamlFile($file);
    $data[$name] = $a;
    writeToYamlFile($file, $data);
}




$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$options = $this->getArg($macroName, 'options', "List of doodle-options, separated by '|'.", '');
	$optionsFile = $this->getArg($macroName, 'optionsFile', "Source file containing list of doodle-options (one per line).", '');

	$multipleAnswers = $this->getArg($macroName, 'multipleAnswers', '[true,false] Defines whether multiple answers are permitted.', true);
	$allowOverwrite = $this->getArg($macroName, 'allowOverwrite', '[true,false] If true, previous entries can be overwritten by anybody.', false);

    if ($options === 'help') {
        return '';
    }

    $file0 = $this->getArg($macroName, 'output', '(optional) File in which to store data', "~page/doodle$inx.yaml");
    $options = ltrim($options, '[');
    $options = rtrim($options, ']');
    $options = explode('|', $options);

    if ($optionsFile) {
        $optionsFile = makePathRelativeToPage($optionsFile);
        $optionsFile = resolvePath($optionsFile);
        if (file_exists($optionsFile)) {
            $options = array_merge($options, file($optionsFile));
        }
    }

    $allowOverwrite = $allowOverwrite ? ' data-lzy-doodle-ow="true"' : '';

    $type =  ($multipleAnswers) ? 'checkbox' : 'radio';

    $optionsHeader = '';
    $newEntryForm = '';
    $sumRow = '<div class="lzy-doodle-name-elem"></div>';
    foreach ($options as $i => $option) {
        if (!$option) {
            continue;
        }
        if ($multipleAnswers) {
            $input = "\t\t\t\t<input id=\"lzy-doodle-entry-answeri$inx-$i\" class=\"i$inx-$i\" name=\"lzy-doodle-entry-answer$i\" type=\"checkbox\" />";
        } else {
            $input = "\t\t\t\t<input id=\"lzy-doodle-entry-answeri$inx-$i\" class=\"i$inx-$i\" name=\"lzy-doodle-entry-answer\" value='$i' type=\"radio\" />";
        }
        $option = trim($option);
        $optionsHeader .= "\t\t\t<div class=\"lzy-doodle-elem\">$option</div>\n";
        $newEntryForm .= <<<EOT
            <div class="lzy-doodle-elem lzy-doodle-answer">
                <label class="lzy-doodle-answer-label invisible" for="lzy-doodle-entry-answeri$inx-$i">{{ lzy-doodle-answer }}</label>
$input
            </div><!-- /lzy-doodle-elem -->


EOT;
        $sums[$i] = 0;
        $sumRow .= "\t\t\t<div id='i$inx-$i' class=\"lzy-doodle-elem\">0</div>\n";
    }


    $currUserName = '';
    if (isset($_SESSION['lizzy']['userDisplayName'])) {
        $currUserName = $_SESSION['lizzy']['userDisplayName'];
    } elseif (isset($_SESSION['lizzy']['user'])) {
        $currUserName = $_SESSION['lizzy']['user'];
    }
    $file1 = str_replace('~', '&#126;', $file0);
    $n = $i+1;
    $newEntryForm = <<<EOT
        <input type="hidden" name="lzy-doodle-number-of-options" value="$n" />
        <input type="hidden" name="lzy-doodle-filename" value="$file1" />
        <input type="hidden" name="lzy-doodle-type" value="$type" />
        <div class="lzy-doodle-row lzy-doodle-new-entry-row">
            <div class="lzy-doodle-elem lzy-doodle-name-elem lzy-doodle-answer-name">
                <label class="lzy-doodle-answer-name invisible" for="lzy-doodle-entry-name$inx">{{ lzy-doodle-name }}</label>
                <input id="lzy-doodle-entry-name$inx" class="lzy-doodle-entry-name" name="lzy-doodle-entry-name" type="text" value="$currUserName" />
            </div>
$newEntryForm
        </div> <!-- /lzy-doodle-row -->

EOT;

    $file = resolvePath($file0, true);
    $data = getYamlFile($file);
    $entries = '';
    $sums = array_fill(0, $n, 0);
    if ($data && is_array(($data))) {
         foreach ($data as $name => $rec) {
            $entry = "\t\t\t<div class=\"lzy-doodle-name-elem lzy-doodle-elem\">$name</div>\n";
            if (is_array($rec)) {
                foreach ($rec as $i => $answer) {
                    $check = '';
                    if ($answer) {
                        $check = ' checked="checked"';
                        $sums[$i]++;
                    }
                    $entry .= "\t\t\t<div class=\"lzy-doodle-elem\"><input type=\"$type\" disabled$check></div>\n";
                };
            } else {
                $selected = intval($rec);
                for ($i=0; $i<$n; $i++) {
                    $check = '';
                    if ($i == $selected) {
                        $check = ' checked="checked"';
                        $sums[$i]++;
                    }
                    $entry .= "\t\t\t<div class=\"lzy-doodle-elem\"><input type=\"$type\" disabled$check></div>\n";
                }
            }
            $entries .= <<<EOT
        <div class="lzy-doodle-row lzy-doodle-answer-row">
$entry
        </div><!-- /lzy-doodle-answer-row -->

EOT;
        }

        $sumRow = "\t\t\t<div class=\"lzy-doodle-name-elem\"></div>\n";
        for ($i=0; $i<$n; $i++) {
            $v = (isset($sums[$i]) && $sums[$i])? $sums[$i] : '0';
            $sumRow .= "\t\t\t<div id='i$inx-$i' class=\"lzy-doodle-elem\">$v</div>\n";
        }
    } else {

    }
    $sumRow = <<<EOT
        <div class="lzy-doodle-row lzy-doodle-sums-row">
$sumRow
        </div>
EOT;



    $str = <<<EOT
    <form class="lzy-doodle-answer-form" method="post">
      <div class="lzy-doodle lzy-doodle$inx"$allowOverwrite>
        
        <div class="lzy-doodle-row lzy-doodle-hdr">
           <div class="lzy-doodle-name-elem"></div>
$optionsHeader
        </div>
$entries
$newEntryForm
$sumRow
      </div> <!-- /lzy-doodle$inx -->
      <div class="lzy-doodle-row lzy-doodle-err-msg" style="display: none;"><output>{{ lzy-doodle-name-mandatory }}</output></div>
        <div class="lzy-doodle-submit">
            <input class="lzy-doodle-answer-submit lzy-button" type="submit" value="{{ submit }}">
        </div>
  </form>

EOT;

	return $str;
});
