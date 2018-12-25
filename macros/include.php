<?php

// @info: Renders content of a file or all files in a folder.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $file = $this->getArg($macroName, 'file', 'Identifies the file to be included', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', "If set, Lizzy attempts to retrieve content from that source. '#id' -> from within page: 'url' -> from other webpage", '');
    $id = $this->getArg($macroName, 'id', 'ID to be used for the target element', '');
    $folder = $this->getArg($macroName, 'folder', 'Identifies the folder to be included', '');
    $reverseOrder = $this->getArg($macroName, 'reverseOrder', '[true,false] If true, renders included objects in reverse order.', false);
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

    if ($contentFrom) {
        $id = $id ? $id : "include$inx";
        $jq = '';
        // . or # -> jq, else .load
        if (($contentFrom{0} == '.') || ($contentFrom{0} == '#')) {
            $jq = "$('#$id').html( $('$contentFrom').html() );";
        } else {
            $jq = "$('#$id').load( '$contentFrom' );";
        }
        $this->page->addJq($jq);
        $str = "<div id='$id'></div>\n";
    }

    if ($folder) {
        $folder = resolvePath(fixPath($folder), true);
        $files = getDir($folder.'*');
        if ($reverseOrder) {
            rsort($files);
        }
        foreach ($files as $file) {
            if (strtolower(fileExt($file)) != 'md') {
                $allMD = false;
            }
            $s = getFile($file, true);
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
