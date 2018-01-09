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

    public function __construct($fromFiles, $toPath, $localCall = false)
    {
        $this->fromFiles = $fromFiles;
        $this->toPath = $toPath;
        $this->localCall = $localCall;
        if (isset($_GET['reset'])) {
            $this->deleteCache();
        }
    } // __construct




    public function compile($forceUpdate = false)
    {
        $compiled = false;
        $scss = new Compiler;
        $files = getDir($this->fromFiles);
        foreach ($files as $file) {
            $targetFile = $this->toPath . '_' . basename($file, '.scss') . '.css';
            $t0 = filemtime($file);
            $t1 = (file_exists($targetFile)) ? filemtime($targetFile) : 0;
            if ($t0 > $t1) {
                $scssStr = $this->getFile($file);
                $cssStr = "/**** auto-created from '$file' - do not modify! ****/\n\n";
                try {
                    $cssStr .= $scss->compile($scssStr);
                } catch (Exception $e) {
                    fatalError("Error in SCSS-File '$file': " . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
                file_put_contents($targetFile, $cssStr);
                touchFile($targetFile, $t0);
                $compiled .= basename($file) . ", ";
            } elseif ($t0 < $t1) {
                fatalError("Warning: compiled stylesheet newer than source: '$targetFile'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
            }
        }
        if ($compiled || $forceUpdate) {
            $compiled = preg_replace('/,\s$/', '', $compiled);
            generateNewVersionCode();
        }
        return $compiled;
    } // compile




    private function getFile($file)
    {
        $out = getFile($file, true);
        if ($this->localCall) {
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                $out .= $l;
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
    } // deleteCache
}