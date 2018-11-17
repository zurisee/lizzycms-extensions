<?php
// @info: From a selection of files determines the last modified and returns the timestamp


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $files = $this->getArg($macroName, 'files', 'Files that will be checked to determine the newest. Use "glob syntax", e.g. "~/data/*". Separate multiple elements by comma.', '~page/*');
    $format = $this->getArg($macroName, 'format', 'Format in which the output will be rendered (see http://php.net/manual/en/function.date.php)', 'Y-m-d');

    if (preg_match('/^\[(.*)\]$/', $files, $m)) {
        $files = $m[1];
    }
    $filePaths = explode(',', $files);

    $newest = 0;
    foreach ($filePaths as $path) {
        $path = resolvePath($path);
        if (is_dir($path)) {
            $path = fixPath($path).'*';
        }
        $dir = glob($path);
        foreach ($dir as $file) {
            $fileDate = filemtime($file);
            if ($fileDate > $newest) {
                $newest = $fileDate;
            }
        }
    }
    if ($newest == 0) {
        $filedate = '{{ unknown }}';
    } else {
        $filedate = date($format, $newest);
    }
	return $filedate;
});
