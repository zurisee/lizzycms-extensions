<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	SCSS Compiler Adapter
 *
 *  Resulting CSS code is stored a) in individual file (e.g. '_style.css') and
 *  aggregated in file '_styles.css'.
 *  Aggregation is skipped if the scss filename starts with a non-apha character, e.g. '@special.css'.
 *  -> Thus it's possible to distribute CSS rules over category files.
*/
use Leafo\ScssPhp\Compiler;

class SCssCompiler
{
    private $cssDestPath;

    public function __construct( $lzy )
    {
        $this->config = $lzy->config;
        $this->page = $lzy->page;
        $this->fromFiles = $lzy->config->path_stylesPath;
        $this->sysCssFiles = $lzy->config->systemPath.'css/';
        $this->isPrivileged = $lzy->config->isPrivileged;
        $this->localCall = $lzy->localCall;
        $this->compiledStylesFilename = $lzy->config->site_compiledStylesFilename;
        $this->compiledSysStylesFilename = '_lizzy.css';
        $this->scss = false;

        if (isset($_GET['reset'])) {
            $this->deleteCache();
        }
    } // __construct




    public function compile($forceUpdate = false)
    {
        $this->forceUpdate = $forceUpdate;
        $namesOfCompiledFiles = '';

        // app specific styles:
        $compiledFilename = $this->fromFiles.$this->compiledStylesFilename;
        $files = getDir($this->fromFiles.'scss/*.scss');
        $mustCompile = $this->checkUpToDate($this->fromFiles, $files, $compiledFilename);
        if ($mustCompile) {
            file_put_contents($compiledFilename, '');
            foreach ($files as $file) {
                $namesOfCompiledFiles .= $this->doCompile($this->fromFiles, $file, $compiledFilename);
            }
        }

        // system styles:
        $compiledFilename = $this->sysCssFiles.$this->compiledSysStylesFilename;
        if (!is_writable($this->sysCssFiles.'scss/')) {
            $this->page->addMessage("Warning: unable to update system style files");
            if ($namesOfCompiledFiles) {
                writeLog("SCSS files compiled: " . rtrim($namesOfCompiledFiles, ', '));
            }
            writeLog("Warning: failed to update css files in ".$this->sysCssFiles.'scss/');
            return $namesOfCompiledFiles;
        }

        $files = getDir($this->sysCssFiles.'scss/*.scss');
        $mustCompile = $this->checkUpToDate($this->sysCssFiles, $files, $compiledFilename);
        if ($mustCompile) {
            file_put_contents($compiledFilename, '');
            foreach ($files as $file) {
                $namesOfCompiledFiles .= $this->doCompile($this->sysCssFiles, $file, $compiledFilename);
            }
        }

        if ($namesOfCompiledFiles) {
            writeLog("SCSS files compiled: ".rtrim($namesOfCompiledFiles, ', '));
            generateNewVersionCode();
        }
        return $namesOfCompiledFiles;
    } // compile


    
    
    private function checkUpToDate($path, $files, $compiledBundeledFilename)
    {
        if ($this->forceUpdate || !file_exists($compiledBundeledFilename)) {
            $this->forceUpdate = true;
            return true;
        }
        $t2 = filemtime($compiledBundeledFilename);
        foreach ($files as $file) {
            $baseName = basename($file, '.scss');
            if (preg_match('/^\W/', $baseName)) {
                $baseName = substr($baseName, 1);
            }
            $compiledFile = $path . '_' . $baseName . '.css';
            $t0 = filemtime($file);
            $t1 = (file_exists($compiledFile)) ? filemtime($compiledFile) : 0;
            if (($t0 > $t2) || ($t0 > $t1)) {
                $this->forceUpdate = true;
                return true;
            }
        }
        return false;
    } // checkUpToDate


        
    private function getFile($file)
    {
        $out = getFile($file);
        if ($this->localCall && $this->config->debug_compileScssWithLineNumbers) {
            $fname = basename($file);
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                if (preg_match('/^[^\/\*]+\{/', $l)) {
                    $l .= " /* content: '$fname:".($i+1)."'; */";
                }
                $out .= $l."\n";
            }
        } else {
            $out = removeCStyleComments($out);
        }
        return $out . "\n";
    } // getFile



    private function deleteCache()
    {
        $files = getDir($this->cssDestPath . "*.css");
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }



    private function doCompile($toPath, $file, $compiledFilename)
    {
        if (!$this->scss) {
            $this->scss = new Compiler;
        }
        $includeFile = true;
        $fname = basename($file, '.scss');
        if (preg_match('/^\W/', $fname)) {
            $fname = substr($fname,1);
            $includeFile = false;
        }
        $targetFile = $toPath . "_$fname.css";
        $t0 = filemtime($file);
        $scssStr = $this->getFile($file);
        $cssStr = '';
        try {
            $cssStr .= $this->scss->compile($scssStr);
        } catch (Exception $e) {
            fatalError("Error in SCSS-File '$file': " . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
        }

        if (!$this->compiledStylesFilename) {
            $cssStr = removeCStyleComments($cssStr);
            $cssStr = removeEmptyLines($cssStr);
        }
        $cssStr = "/**** auto-created from '$file' - do not modify! ****/\n\n$cssStr";

        file_put_contents($targetFile, $cssStr);
        touchFile($targetFile, $t0);

        if ($includeFile) {                 // assemble all generated CSS, unless its filename started with non-alpha char
            file_put_contents($compiledFilename, $cssStr . "\n\n\n", FILE_APPEND);
        }

        return basename($file).", ";
    } // doCompile




    public function compileStr($scssStr)
    {
        if (!$this->scss) {
            $this->scss = new Compiler;
        }
        try {
            $cssStr = $this->scss->compile($scssStr);
        } catch (Exception $e) {
            fatalError("Error in SCSS string: '$scssStr'.");
        }
        return $cssStr;
    } // compileStr

} // SCssCompiler