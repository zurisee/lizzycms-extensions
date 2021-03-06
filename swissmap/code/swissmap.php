<?php

require_once (EXTENSIONS_PATH.'swissmap/code/mapsearch.class.php');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');

	$long = $this->getArg($macroName, 'long', "[float] Specifies longitude of center of map", '');

	if ($long === 'help') {
        $this->getArg($macroName, 'lat', "[float] Specifies latitude of center of map", '');
        $this->getArg($macroName, 'location', "Specifies what the map is to center on: either an address (e.g. 'bahnhofstr. 1, zürich' or coordinates (e.g. '47.36751, 8.53988'), see https://map.search.ch/api/help#geocoding", '');
        $this->getArg($macroName, 'metersPerPixel', "[512 .. 0.125] Defines the zoom level as 'meters per pixel'", '');
        $this->getArg($macroName, 'zoom', "Synonyme for 'metersPerPixel'", '');
        $this->getArg($macroName, 'id', "[string] Defines the ID to be applied to the map container", '');
        $this->getArg($macroName, 'mapType', "[street|satellite] Specifies in what way the map shall be displayed initially", '');
        $this->getArg($macroName, 'controls', "[zoom,type,ruler,all] Specifies which controls are active", '');
        $this->getArg($macroName, 'poigroups', "Specifies which points-of-interests are displayed, see https://map.search.ch/api/classref#poigroups", '');
        $this->getArg($macroName, 'customPOIs', "[comma seperated list on locations|'file:'] Defines points-of-interest to be displayed. A single POI may be supplied as a comma-separated-list; multiple POIs via a .csv file. Structure: 'location,title,description,icon'", '');
        $this->getArg($macroName, 'customPOIIcon', "Defines the default icon to represent custom locations.", '');
        $this->getArg($macroName, 'drawing', "Let's you add an overlay containing a drawing that has been defined beforehand. The drawing is identified by an ID that you get from https://map.search.ch", '');
        $this->getArg($macroName, 'marker', "[true,false] Specifies whether a marker is visible at the center of the map", '');
        $this->getArg($macroName, 'gestureHandling', "[cooperative|greedy|auto] Spedifies how scroll-events are being treated, see https://map.search.ch/api/classref#gestureHandling'", '');
        $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);
        return <<<EOT
    <p><strong>Note:</strong> this macro relies on the map provider <a href='https://map.search.ch/'>map.search.ch</a> 
    and thus only works for locations in Switzerland.</p>
    
EOT;
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    $args = $this->getArgsArray($macroName, false);

    $mapSearch = new MapSearch($this->lzy);

    $out = $mapSearch->render($args);

	return $out;
});

