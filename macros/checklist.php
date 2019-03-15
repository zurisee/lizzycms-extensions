<?php
// @info: -> one line description of macro <-

$jq = <<<'EOT'
var checklist = [];
var backend = systemPath+'_ajax_server.php';
EOT;
$this->page->addJq($jq);

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $selector = $this->getArg($macroName, 'selector', 'A CSS selector that identifies the associated checklist, e.g. "#mychkecklist".', '');
    $dataStorage = $this->getArg($macroName, 'dataStorage', 'Name of a file (local to current page) where to store checklist states, e.g. "mychecklist.json".', '');
    $dataStorage = resolvePath($dataStorage, true);
    $scope = $this->getArg($macroName, 'scope', '[all|user] Defines who will see changes, only the user or everybody visiting the web-page. (Default: all)', '');

    require_once SYSTEM_PATH.'ticketing.class.php';
    $ticketing = new Ticketing(['hashSize' => 6, 'defaultType' => 'checklist', 'defaultMaxConsumptionCount' => 999999999, 'defaultValidityPeriod' => 999999999]);

    $ticket = $ticketing->findTicket($selector, 'selector');
    if (!$ticket) {
        $rec = [
            'dataSrc' => $dataStorage,
            'scope' => $scope,
            'selector' => $selector,
        ];
        $ticket = $ticketing->createTicket($rec);
    }

    $jq = <<<EOT

checklist['$selector'] = '$ticket';
$('input', '$selector' ).removeAttr('onclick').removeAttr('onkeydown').removeAttr('disabled');

// on load:
$.ajax({
    type: "POST",
    url: backend + '?get-all',
    data: { ds: '$ticket' },
    success: function( json ) {
        updateElements( json );
    },
});

// on change:
$('input', '$selector' ).change(function() {
    var \$form = $( this ).closest('.lzy-checklist');
    var ticket = checklist['$selector'];
    var data = '';
    $('input', \$form).each(function() {
        var nam = $( this ).attr('name');
        var val = this.checked;
        data = data + '"' + nam + '":' + val + ','; 
    });
    data = "{" + data.substr(0, data.length - 1) + "}";
    
    $.ajax({
        type: "POST",
        url: backend + '?save-data',
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
        data: { ds: ticket, data: data },
        success: function( json ) {
            updateElements( json );
        },
    });
});

function updateElements( json ) {
// copy received values to checkbox elements
   if (json && json.match(/^\{/)) {
        json = json.replace(/(\{.*\}).*/, "$1");    // remove trailing #comment
        var data = JSON.parse(json);
        for (var id in data) {
            if (data[id]) {
                $('[name=' + id + ']').attr('checked', '');
            } else {
                $('[name=' + id + ']').removeAttr('checked');
            }
        }
    }
}

EOT;
    $this->page->addJq($jq);

    $this->optionAddNoComment = true;
	return '';
});
