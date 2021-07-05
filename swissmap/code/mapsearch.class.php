<?php

if (!isset($GLOBALS["mapSearchInx"])) {
    $GLOBALS["mapSearchInx"] = 1;
}


class MapSearch
{
    public function __construct($lzy)
    {
        $this->page = $lzy->page;
    }



    public function render($args)
    {
        $this->inx = $GLOBALS["mapSearchInx"]++;

        if (isset($args['id'])) {
            $id = $args['id'];
        } else {
            $id = "lzy-swissmap-container{$this->inx}";
        }

        $location = isset($args['location']) ? $args['location'] : '';
        $long = isset($args['long']) ? $args['long'] : '';
        $lat = isset($args['lat']) ? $args['lat'] : '';

        if ($long && ($l = $this->getLocation($long))) {
            $location = $l;
        } elseif ($long && $lat) {
            $location = "[$long, $lat]";
        } else {
            $location = $this->getLocation($location);
        }

        if (isset($args['metersPerPixel'])) {
            $args['zoom'] = $args['metersPerPixel'];
        }
        $zoom = isset($args['zoom']) ? $args['zoom'] : '0';

        if (isset($args['mapType']) && (strpos(",aerial,street,satellite,", ",{$args['mapType']},") !== false)) {
            if ($args['mapType'] === 'satellite') {
                $args['mapType'] = 'aerial';
            }
            $mapType = "\ttype: '{$args['mapType']}',\n";

        } else {
            $mapType = '';
        }

        $from = isset($args['from']) ? $args['from'] : '';
        $to = isset($args['to']) ? $args['to'] : '';
        $route = '';
        if ($from && $to) {
            $from = $this->getLocation($from);
            $to = $this->getLocation($to);
            $route = <<<EOT
        from: $from,
        to: $to,

EOT;
        }

        $controls = isset($args['controls']) ? $args['controls'] : '';
        if ($controls) {
            $controls = "\tcontrols: '$controls',\n";
        }

        $poigroups = isset($args['poigroups']) ? $args['poigroups'] : '';
        if ($poigroups) {
            $poigroups = "\tpoigroups: '$poigroups',\n";
        }

        $this->customPOIIcon = isset($args['customPOIIcon']) ? $args['customPOIIcon'] : '';
        $customPOIs = isset($args['customPOIs']) ? $args['customPOIs'] : '';
        if ($customPOIs) {
            $customPOIs = $this->handleCustomPOIs($customPOIs);
        }

        $drawing = isset($args['drawing']) ? $args['drawing'] : '';
        if ($drawing) {
            $drawing = "\tdrawing: '$drawing',\n";
        }

        $marker = isset($args['marker']) ? ($args['marker']?'true': 'false') : 'true';
        $marker = "marker: $marker,\n";

        $gestureHandling = isset($args['gestureHandling']) ? $args['gestureHandling'] : '';
        if ($gestureHandling) {
            $gestureHandling = "\tgestureHandling: '$gestureHandling',\n";
        }

        $lang = '';
        if ($GLOBALS['globalParams']['lang']) {
            $lang = "?lang={$GLOBALS['globalParams']['lang']}";
        }

        $out = "\t<div id='$id' class='lzy-swissmap-container'></div>\n";

        if ($this->inx == 1) {
            $this->page->addModules("https://map.search.ch/api/map.js$lang");
        }

        $minHight = isset($args['minHeight']) ? $args['minHeight'] : '200px';
        $cssRules = "min-height: $minHight;";
        if (isset($args['height'])) {
            $cssRules .= "height: {$args['height']};";
        }
        $this->page->addCss("#$id { $cssRules }");

        $map = "map{$this->inx}";

        $jq = <<<EOT

$map = new SearchChMap({
    container: '$id',
    center: $location,
    zoom: $zoom,
    $marker$mapType$route$controls$poigroups$drawing$gestureHandling
});$customPOIs


EOT;

        $this->page->addJq($jq);

        return $out;
    } // render




    private function getLocation($str)
    {
        // 'street number zip city'
        // '1.00, 2.00'
        // '[1.00, 2.00]'
        //
        if (preg_match('/^\s* \[? \s* (\d+\.?\d*) \s*,\s* (\d+\.?\d*) \s* \]? \s*$/x', $str, $m)) {
            $str = "[{$m[1]}, {$m[2]}]";
        } else {
            $str = "'$str'";
        }

        return $str;
    } // getLocation



    private function handleCustomPOIs($customPOIs)
    {
        $jq = '';
        $map = "map{$this->inx}";

        if (preg_match('/^(\w+):(.*)/', $customPOIs, $m)) {
            if ($m[1] === 'file') {
                $file = resolvePath($m[2], true);
                if (file_exists($file)) {
                    $db = new DataStorage2(['dataFile' => $file]);
                    $recs = $db->read();
                    $structure = $db->getRecStructure();
                    foreach ($recs as $rec) {
                        $elemKeys = array_keys($structure['elements']);
                        if ($elemKeys[0] === $rec[0]) {
                            continue;
                        }
                        $location = trim($this->getLocation($rec[0]),'"\'');
                        $title = trim($rec[1],'"\'');
                        $description = trim($rec[2],'"\'');
                        $poiIcon = isset($rec[3]) ? trim($rec[3],'"\''): $this->customPOIIcon;
                        $jq .= <<<EOT

    $map.addPOI(new SearchChPOI({ 
        center:'$location', 
        title:'$title', 
        html:'$description', 
        icon:'$poiIcon' 
    }));

EOT;
                    }
                }
            }
        }

        return $jq;

    } // handleCustomPOIs
}