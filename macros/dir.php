<?php
// @info: Reads a folder and renders a list of files to be downloaded or opened.

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $pattern = $this->getArg($macroName, 'pattern', 'The search-pattern with which to look for files (-> \'glob style\')', '*');
    $path0 = $this->getArg($macroName, 'path', 'Selects the folder to be read', '~page/');
    $deep = $this->getArg($macroName, 'deep', 'Whether to recursively search sub-folders', false);
    $class = $this->getArg($macroName, 'class', 'class to be applied to the enclosing li-tag', '');
    $target = $this->getArg($macroName, 'target', '"target" attribute to be applied to the a-tag', '');
    $exclude = $this->getArg($macroName, 'exclude', 'pattern to be excluded (-> \'glob style\'), default is \'*.md\'', '');

    $path0 = fixPath($path0);
    $path1 = resolvePath($path0, true);
    $path = $path1 . $pattern;
    $exclPath = $path1 . $exclude;
    if ($deep) {
        $dir = getDirDeep($path);
        $exclDir = getDirDeep($exclPath);

    } else {
        $dir = getDir($path);
        $exclDir = getDir($exclPath);
    }

    if ($target) {
        $target = " target='$target'";
    }

    if ($class) {
        $class = " class='$class'";
    }

    $str = '';
    foreach ($dir as $file) {
        if ($exclDir && in_array($file, $exclDir)) {
            continue;
        }
        $name = base_name($file);
        $file = resolvePath($path0.basename($file), true, true);
        $str .= "\t\t<li><a href='$file'$target>$name</a></li>\n";
    }
    $str = <<<EOT

    <ul$class>
$str   
    </ul>
EOT;

	return $str;
});
