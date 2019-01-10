<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	SCSS Compiler Adapter
*/
use Leafo\ScssPhp\Compiler;

class SCssCompiler
{
    private $cssDestPath;

    public function __construct( $lzy )
    {
        // $this->config->path_stylesPath.'scss/*.scss', $this->config->path_stylesPath, $this->localCall
        $this->config = $lzy->config;
        $this->fromFiles = $lzy->config->path_stylesPath;
        $this->sysCssFiles = $lzy->config->systemPath.'css/';
//        $this->fromFiles = $lzy->config->path_stylesPath.'scss/*.scss';
//        $this->fromFiles = $lzy->config->systemPath.'css/scss/*.scss';
//        $this->toPath = $lzy->config->path_stylesPath;
        $this->isPrivileged = $lzy->config->isPrivileged;
        $this->localCall = $lzy->localCall;
        $this->compiledStylesFilename = $lzy->config->site_compiledStylesFilename;

        if (isset($_GET['reset'])) {
            $this->deleteCache();
        }
    } // __construct




    public function compile($forceUpdate = false)
    {
        if (!file_exists($this->fromFiles.$this->compiledStylesFilename) ||
            !file_exists($this->sysCssFiles.'_lizzy.css')) {
            $forceUpdate = true;
        }
        $this->forceUpdate = $forceUpdate;
        $compiled = false;
        $compiledCode = '';
        $this->scss = new Compiler;
        $files = getDir($this->fromFiles.'scss/*.scss');
        foreach ($files as $file) {
            $compiled = $this->doCompile($this->fromFiles, $file, $compiled, $compiledCode);
        }
        if ((sizeof($files) > 1) && $compiledCode) {
            $compiledCode = preg_replace("|\s*/\* .* \*/\n|m", '', $compiledCode);
            file_put_contents($this->fromFiles.'_compiled-styles.css', $compiledCode);
        }

        $compiledCode = '';
        $files = getDir($this->sysCssFiles.'scss/*.scss');
        foreach ($files as $file) {
            $compiled = $this->doCompile($this->sysCssFiles, $file, $compiled, $compiledCode);
        }
        if ($compiledCode) {
            $compiledCode = preg_replace("|\s+/\* .* \*/|m", '', $compiledCode);
            file_put_contents($this->sysCssFiles.'_lizzy.css', $compiledCode);
        }

        if ($compiled || $forceUpdate) {
            $compiled = preg_replace('/,\s$/', '', $compiled);
            generateNewVersionCode();
        }
        return $compiled;
    } // compile




    private function getFile($file)
    {
        $out = getFile($file);
        $out = zapFileEND($out);
        $out = removeCStyleComments($out);
        if ($this->localCall && $this->config->debug_compileScssWithLineNumbers) {
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                if (preg_match('/^[^\/\*]+\{/', $l)) {
                    $l .= " /* content: '".($i+1)."'; */";
                }
                $out .= $l."\n";
            }
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



    private function doCompile($toPath, $file, $compiled, &$compiledCode)
    {
        $targetFile = $toPath . '_' . basename($file, '.scss') . '.css';
        $t0 = filemtime($file);
        $t1 = (file_exists($targetFile)) ? filemtime($targetFile) : 0;
        if ($this->forceUpdate || ($t0 > $t1)) {
            $scssStr = $this->getFile($file);
            $cssStr = "/**** auto-created from '$file' - do not modify! ****/\n\n";
            try {
                $cssStr .= $this->scss->compile($scssStr);
            } catch (Exception $e) {
                fatalError("Error in SCSS-File '$file': " . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
            }
            file_put_contents($targetFile, $cssStr);
            $compiledCode .= $cssStr."\n\n\n";
            touchFile($targetFile, $t0);
            $compiled .= basename($file) . ", ";
        }
        return $compiled;
    } // deleteCache
}