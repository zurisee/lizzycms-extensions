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

        $poigroups = isset($args['poiGroups']) ? $args['poiGroups'] : '';
        if ($poigroups) {
            $poigroups = "\tpoigroups: '$poigroups',\n";
        }

        $drawing = isset($args['drawing']) ? $args['drawing'] : '';
        if ($drawing) {
            $drawing = "\tdrawing: '$drawing',\n";
        }

        $marker = isset($args['marker']) ? ($args['marker']?'true': 'false') : 'true';
        $marker = "\tmarker: $marker,\n";

        $gestureHandling = isset($args['gestureHandling']) ? $args['gestureHandling'] : '';
        if ($gestureHandling) {
            $gestureHandling = "\tgestureHandling: '$gestureHandling',\n";
        }

        $lang = '';
        if ($GLOBALS["globalParams"]["lang"]) {
            $lang = "?lang={$GLOBALS["globalParams"]["lang"]}";
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

        $jq = <<<EOT

  var map{$this->inx} = new SearchChMap({
    container: '$id',
    center: $location,
    zoom: $zoom,
$mapType$route$controls$poigroups$drawing$marker$gestureHandling
  });
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
        if (preg_match('/^\s* \[? \s* (\d+\.?\d*) \s*,\s* (\d+\./?\d*) \s* \]? \s*$/x', $str, $m)) {
            $str = "[{$m[1]}, {$m[2]}]";
        } else {
            $str = "'$str'";
        }

        return $str;
    } // getLocation

}