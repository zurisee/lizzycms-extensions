<?php

use EndorphinStudio\Detector\Detector;


class UaDetector extends Detector
{
    public function __construct($collectBrowserSignatures = false)
    {
        $this->debug_collectBrowserSignatures = $collectBrowserSignatures;
        $this->ua = $this->analyse();
        $this->uaStr = $_SERVER['HTTP_USER_AGENT'];

        $this->browserName = str_replace('N\A', 'N/A', $this->ua->Browser->getName());
        $this->browserVersion = str_replace('N\A', 'N/A', $this->ua->Browser->getVersion());
        $this->osName = str_replace('N\A', 'N/A', $this->ua->OS->getName());
        $this->osVersion = str_replace('N\A', 'N/A', $this->ua->OS->getVersion());

        $this->fixUnknownSignatures();
    }



    private function fixUnknownSignatures()
    {
        if (preg_match('/OS ([\d_]+) like Mac OS X\)/', $this->uaStr, $m)) {
            if (preg_match('|AppleWebKit/([\d\.]+)|', $this->uaStr, $mm)) {
                $this->browserName = 'Safari';
                $this->browserVersion = $mm[1];
            }
            $this->osVersion = str_replace('_', '.', $m[1]);
            if (strpos($this->uaStr, 'iPhone') !== false) {
                $this->osName = "iPhone/iOS";
            } elseif (strpos($this->uaStr, 'iPad') !== false) {
                $this->osName = "iPad/iOS";
            } else {
                $this->osName = "iOS";
            }
            return;
        }

        if (preg_match('|Chrome/([\d\.]+)|', $this->uaStr, $m)) {   //Chrome/63.0.3239.111
            $this->browserName = 'Chrome';
            $this->browserVersion = $m[1];
            return;
        }

        if (preg_match( '|Trident/7\.0;.*rv:11\.0|', $this->uaStr)) { // IE11-bug workaround
            $this->browserName = 'IE';
            $this->browserVersion = '11.0';
            return;
        }

        $botPatterns = [
            'facebook' => 'Facebook Bot',
            'uptime' => 'Uptime Bot',
            'WhatsApp' => 'WhatsApp Bot',
            'Googlebot' => 'Google Bot',
            'DomainStats' => 'DomainStats Bot',
            'Mail.RU_Bot' => 'Mail.RU Bot',
            'Yahoo! Slurp' => 'Yahoo! Bot',
            'bingbot' => 'Bing Bot',
        ];
        foreach ($botPatterns as $botPattern => $name) {
            if (strpos($this->uaStr, $botPattern) !== false) {
                $this->browserName = $name;
                $this->browserVersion = '';
                $this->osName = '';
                $this->osVersion = '';
                break;
            }
        }
    } // fixUnknownSignatures



    public function get()
    {
        $this->browserSignature = "{$this->browserName} {$this->browserVersion} on {$this->osName} {$this->osVersion}";
        $this->storeBrowserSignature();
        return $this->browserSignature;
    } // get



    public function isLegacyBrowser()
    {
        if ((strpos($this->osName, 'N/A') !== false) ||
            (strpos($this->osVersion, 'N/A') !== false) ||
            (strpos($this->browserName, 'N/A') !== false) ||
            (strpos($this->browserVersion, 'N/A') !== false) ||
            $this->isOlderBrowserThan('IE', '10') ||
            $this->isOlderBrowserThan('Safari', '5.1') ||
            $this->isOlderOSThan('Android', '5') ||
            $this->isOlderOSThan('Windows', '7') ||
            $this->isOlderOSThan('Mac OS X', '10.8')
           ) {
            return true;
        }
        return false;
    } // isLegacyBrowser



    private function isOlderBrowserThan($product, $version)
    {
        if ($this->browserName != $product) {
            return false;
        }
        $thisVer = explode('.', $this->browserVersion.'.0.0.0.0');
        foreach (explode('.', $version) as $i => $e) {
            if (intval($e) < intval($thisVer[$i])) {
                return false;
            }
        }
        return true;
    }



    private function isOlderOSThan($product, $version)
    {
        if ($this->osName != $product) {
            return false;
        }
        $thisVer = explode('.', $this->osVersion.'.0.0.0.0');
        foreach (explode('.', $version) as $i => $e) {
            if (intval($e) < intval($thisVer[$i])) {
                return false;
            }
        }
        return true;
    }



    private function storeBrowserSignature()
    {
        $signature = "{$this->browserSignature}\t\t({$this->uaStr})";

        if ($this->debug_collectBrowserSignatures) {
            if (file_exists(BROWSER_SIGNATURES_FILE)) {
                $signatures = file(BROWSER_SIGNATURES_FILE);
                foreach ($signatures as $sig) {
                    if (strpos($sig, $signature) !== false) {
                        return; // already recorded
                    }
                }
            }
            if (!file_exists(BROWSER_SIGNATURES_FILE)) {
                preparePath(BROWSER_SIGNATURES_FILE);
                touch(BROWSER_SIGNATURES_FILE);
            }
            file_put_contents(BROWSER_SIGNATURES_FILE, $signature . "\n", FILE_APPEND);
        }

        if (strpos($this->browserSignature, 'N/A') !== false) {
            file_put_contents(UNKNOWN_BROWSER_SIGNATURES_FILE, $signature."\n", FILE_APPEND);
        }
    } // storeBrowserSignature
} // UaDetector

