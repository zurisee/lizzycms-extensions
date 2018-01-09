<?php

$page->addCssFiles('DOODLE_CSS');
$page->addJqFiles('DOODLE');

$macroName = basename(__FILE__, '.php');

if (isset($_POST['doodle_entry_name'])) {
    $name = get_post_data('doodle_entry_name');
    $n = get_post_data('doodle_number_of_options');
    $a = [];
    for ($i=0; $i<$n; $i++) {
        $a[$i] = (get_post_data("doodle_entry_answer$i") == 'on') ? 1 : 0;
    }
    $file0 = get_post_data('doodle_filename');
    $file = resolvePath($file0, true);
    $data = getYamlFile($file);
    $data[$name] = $a;
    writeToYamlFile($file, $data);
}




$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $options = $this->getArg($macroName, 'options', 'List of options', '');
    $type = $this->getArg($macroName, 'type', 'List of options', '');

    $file0 = $this->getArg($macroName, 'file', '(optional) file to store data', "~page/doodle$inx.yaml");
    $options = ltrim($options, '[');
    $options = rtrim($options, ']');
    $options = explode('|', $options);

    $optionsHeader = '';
    $newEntryForm = '';
    $sumRow = '<div class="doodle-name-elem"></div>';
    foreach ($options as $i => $option) {
        $option = trim($option);
        $optionsHeader .= "\t\t\t<div class=\"doodle-elem\">$option</div>\n";
        $newEntryForm .= <<<EOT
        <div class="doodle-elem doodle_answer">
            <label class="doodle_answer_label invisible" for="doodle_entry_answeri$inx-$i">Answer</label>
            <input id="doodle_entry_answeri$inx-$i" class="i$inx-$i" name="doodle_entry_answer$i" type="checkbox" />
        </div>

EOT;
        $sums[$i] = 0;
        $sumRow .= "\t\t\t<div id='i$inx-$i' class=\"doodle-elem\">0</div>\n";
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
        <input type="hidden" name="doodle_number_of_options" value="$n" />
        <input type="hidden" name="doodle_filename" value="$file1" />
        <div class="doodle-row doodle-new-entry-row">
            <div class="doodle-elem doodle-name-elem doodle_answer_name">
                <label class="doodle_answer_name invisible" for="doodle_entry_name$inx">Name</label>
                <input id="doodle_entry_name$inx" class="doodle_entry_name" name="doodle_entry_name" type="text" value="$currUserName" />
            </div>
$newEntryForm
        </div>

EOT;

    $file = resolvePath($file0, true);
    $data = getYamlFile($file);
    $entries = '';
    if (is_array(($data))) {
         foreach ($data as $name => $rec) {
            $entry = "\t\t\t<div class=\"doodle-name-elem doodle-elem\">$name</div>\n";
            foreach ($rec as $answer) {
                $check = ($answer) ? ' checked="checked"' : '';
                $entry .= "\t\t\t<div class=\"doodle-elem\"><input type=\"checkbox\" disabled$check></div>\n";
            };
            $entries .= <<<EOT
        <div class="doodle-row">
$entry
        </div>

EOT;
        }

        $sumRow = "\t\t\t<div class=\"doodle-name-elem\"></div>\n";
        foreach ($data as $name => $rec) {
            foreach ($rec as $i => $answer) {
                if (!isset($sums[$i])) {
                    $sums[$i] = 0;
                }
                if ($answer) {
                    $sums[$i]++;
                }
            }
        }
        foreach (array_shift($data) as $i => $answer) {
            $sumRow .= "\t\t\t<div id='i$inx-$i' class=\"doodle-elem\">{$sums[$i]}</div>\n";
        }
    } else {

    }
    $sumRow = <<<EOT
        <div class="doodle-row doodle-sums-row">
$sumRow
        </div>
EOT;



    $str = <<<EOT
    <form class="doodle-answer-form" method="post">
    <div class="doodle doodle$inx">
        
        <div class="doodle-row doodle-hdr">
           <div class="doodle-name-elem"></div>
$optionsHeader
        </div>
$entries
$newEntryForm
$sumRow
    </div> <!-- /doodle$inx -->
        <div class="doodle-submit">
            <input class="doodle_answer_submit ios_button" type="submit" value="{{ submit }}">
        </div>
    </form>

EOT;

	return $str;
});
