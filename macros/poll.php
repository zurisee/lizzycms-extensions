<?php

// @info: Lets you create simple surveys or votes.


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	// how to get access to macro arguments:
    $options = $this->getArg($macroName, 'options', 'A string containing a list of options. Separeate options by vertical bars ("|").', '');
    $optionsFile = $this->getArg($macroName, 'optionsFile', 'As an alternative to "options", you can provide a file name. The file should contain one option per line.', false);
    $multAnswers = $this->getArg($macroName, 'multipleAnswers', 'Allow multiple answers per poll.', false);
    $resultFile = $this->getArg($macroName, 'resultFile', 'File in which to store results. (default: "poll-results.yaml"', "poll-results.yaml");
    $showResults = $this->getArg($macroName, 'showResults', 'Immediately show number of votes per option.', false);
    $limitVotesPerUser = $this->getArg($macroName, 'limitVotesPerUser', '[integer] Monitor who has already voted and exclude this user, if limit is reached.', false);
    $votesCountFile = resolvePath("~page/.#votesCount.yaml");


    $options = determineOptions($optionsFile, $options);

    $pollName = "lizzy-poll$inx";

    $data = getPreviousResults($resultFile, $pollName, $limitVotesPerUser, $votesCountFile, $multAnswers);

    $str = renderOutput($pollName, $multAnswers, $options, $showResults, $data);

	return $str;
});


/**
 * @param $optionsFile
 * @param $options
 * @return array|bool|null|string|string[]
 */
function determineOptions($optionsFile, $options)
{
    if ($optionsFile) {
        $optionsFile = resolvePath("~page/$optionsFile");
        if (file_exists($optionsFile)) {
            $options = file($optionsFile);
        } else {
            die("Error in macro poll(): file '$optionsFile' not found.");
        }
        if (!$options) {
            return '';
        }

    } else {

        $options = preg_replace('/^\[/', '', $options);
        $options = preg_replace('/\]$/', '', $options);
        if (!$options) {
            return '';
        }

        $options = explode('|', $options);
    }
    return $options;
}




/**
 * @param $resultFile
 * @param $pollName
 * @param $limitVotesPerUser
 * @param $votesCountFile
 * @param $multAnswers
 * @return array|mixed|null
 */
function getPreviousResults($resultFile, $pollName, $limitVotesPerUser, $votesCountFile, $multAnswers)
{
    $resultFile = resolvePath($resultFile, true);
    $data = getYamlFile($resultFile);
    if ($data == null) {
        $data = [];
    }
    if (isset($_POST['lzy-poll']) && ($_POST['lzy-poll'] == $pollName) && isset($_POST['lzy-poll-option'])) {
        $ignoreVote = false;
        if ($limitVotesPerUser) {
            if (file_exists($votesCountFile)) {
                $votes = getYamlFile($votesCountFile);
            } else {
                $votes = [];
            }
            $userSig = md5($_SERVER['SERVER_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
            if (isset($votes[$userSig])) {
                $votes[$userSig]++;
            } else {
                $votes[$userSig] = 1;
            }
            writeToYamlFile($votesCountFile, $votes);
            if ($votes[$userSig] > intval($limitVotesPerUser)) {
                $ignoreVote = true;
            }
        }

        $fieldName = $_POST['lzy-poll'];
        $response = $_POST['lzy-poll-option'];
        if (!$ignoreVote) {
            if ($multAnswers) {
                foreach ($response as $r => $resp) {
                    if (isset($data[$fieldName]["$fieldName-$resp"])) {
                        $data[$fieldName]["$fieldName-$resp"]++;
                    } else {
                        $data[$fieldName]["$fieldName-$resp"] = 1;
                    }
                }

            } else {
                if (isset($data[$fieldName][$response])) {
                    $data[$fieldName][$response]++;
                } else {
                    $data[$fieldName][$response] = 1;
                }
            }
            writeToYamlFile($resultFile, $data);
        }
    }
    return $data;
}




/**
 * @param $pollName
 * @param $multAnswers
 * @param $options
 * @param $showResults
 * @param $data
 * @return string
 */
function renderOutput($pollName, $multAnswers, $options, $showResults, $data)
{
    $str = "\t<form method='post' class='lzy-poll $pollName'>\n";
    $str .= "\t\t<input type='hidden' name='lzy-poll' value='$pollName' />\n";

    if ($multAnswers) {

        foreach ($options as $i => $option) {
            $val = '';
            if ($showResults) {
                if (isset($data[$pollName]["$pollName-$i"])) {
                    $v = $data[$pollName]["$pollName-$i"];
                } else {
                    $v = 0;
                }
                $val = "<span class='lzy-poll-result'>{{^ poll-result-before }}$v{{^ poll-result-after }}</span>";
            }
            $option = trim($option);
            $str .= "\t\t<div class='lzy-poll-option'><input type='checkbox' id='lzy-poll-option$i' name='lzy-poll-option[]' value='$i' /> <label for='lzy-poll-option$i'>$option</label>$val</div>\n";
        }
    } else {
        foreach ($options as $i => $option) {
            $val = '';
            if ($showResults) {
                if (isset($data[$pollName][$i])) {
                    $v = $data[$pollName][$i];
                } else {
                    $v = 0;
                }
                $val = "<span class='lzy-poll-result'>{{^ poll-result-before }}$v{{^ poll-result-after }}</span>";
            }
            $option = trim($option);
            $str .= "\t\t<div class='lzy-poll-option'><input type='radio' id='lzy-poll-option$i' name='lzy-poll-option' value='$i' /> <label for='lzy-poll-option$i'>$option</label>$val</div>\n";
        }
    }
    $str .= "\t\t<input type='submit' class='lzy-button' value='{{ Submit }}' />\n";
    $str .= "\t</form>\n";
    return $str;
}
