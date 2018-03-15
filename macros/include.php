<?php

// @info: Renders content of a file or all files in a folder.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $file = $this->getArg($macroName, 'file', 'Identifies the file to be included', '');
    $folder = $this->getArg($macroName, 'folder', 'Identifies the folder to be included', '');
    $wrapperTag = $this->getArg($macroName, 'wrapperTag', '(optional) HTML-tag in which to wrap the content of each included file', false);
    $wrapperClass = $this->getArg($macroName, 'wrapperClass', '(optional) class applied to each file-wrapper', false);
    $outerWrapperTag = $this->getArg($macroName, 'outerWrapperTag', '(optional) HTML-tag in which to wrap the set of included files', false);
    $outerWrapperClass = $this->getArg($macroName, 'outerWrapperClass', '(optional) class applied to the wrapper around all files', true);
    $compileMarkdown = $this->getArg($macroName, 'compileMarkdown', '(optional) Flag to inhibit MD-compilation of .md files', true);

    if ($wrapperClass) {
        $wrapperClass = " class='$wrapperClass'";
    }

    $allMD = true;
    $str = '';
    if ($file) {
        if (strtolower(fileExt($file)) != 'md') {
            $allMD = false;
        }
        $file = resolvePath($file, true);
        $str = getFile($file);
        if ($wrapperTag) {
            $str = "\n@@1@@\n$str\n@@2@@\n\n";
        }
    }

    if ($folder) {
        $folder = resolvePath(fixPath($folder), true);
        $files = getDir($folder.'*');
        foreach ($files as $file) {
            if (strtolower(fileExt($file)) != 'md') {
                $allMD = false;
            }
            $s = getFile($file);
            if ($wrapperTag) {
                $str .= "\n@@1@@\n$s\n@@2@@\n\n";
            } else {
                $str .= $s;
            }
        }
    }

    if ($compileMarkdown && $allMD) {
        $str = compileMarkdownStr($str);
    }

    if ($wrapperTag) {
        $str = str_replace('<p>@@1@@</p>', "\t\t<$wrapperTag$wrapperClass>\n", $str);
        $str = str_replace('<p>@@2@@</p>', "\t\t</$wrapperTag>\n", $str);
    }

    if ($outerWrapperClass) {
        $outerWrapperClass = " class='$outerWrapperClass'";
    }

    if ($outerWrapperTag) {
        $str = "\t<$outerWrapperTag$outerWrapperClass>\n$str\t</$outerWrapperTag>\n";
    }
	return $str;
});
