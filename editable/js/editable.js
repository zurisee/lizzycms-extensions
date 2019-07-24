/*
 *  Editable.js
*/

var editableObj = new Object();
editableObj.backend = systemPath+'extensions/editable/backend/_editable_backend.php';
editableObj.doSave = false;
editableObj.lastText = {};
editableObj.editing = false;
editableObj.keyTimeout = false;
editableObj.fieldIDs = [];
editableObj.dataRef = [];

var instanceCounter = 0;


(function ( $ ) {

    $.fn.editable = function( options ) {
        if ( this.length === 0 ) {
            return;
        }
        instanceCounter++;
        var invokingClass = $(this).attr('class').replace(/\s.*/, '');
        if (typeof invokingClass === 'undefined') {
            invokingClass = 'lzy-editable';
        }
        initializeEditableField(invokingClass);
    };

    $( window ).bind('beforeunload', function(){
        endEdit(editableObj, false, true);
    });
}( jQuery ));




function initializeEditableField(invokingClass)
{
    var edObj = editableObj;
    edObj.editModeTimeout = 60000;  // automatically terminate editing after given time of no key activity
    edObj.invokingClass = invokingClass;
    if ($('body').hasClass('touch')) {
        edObj.isTouchDevice = true;
    } else if ($('body').hasClass('not-touch')) {
        edObj.isTouchDevice = false;
    } else {
        edObj.isTouchDevice = 'ontouchstart' in document.documentElement;
    }

    initEditableFields();
    setupKeyHandler();
    initializeConnection();
    updateFieldContents(edObj);

    console.log('Editable.js started for class ".'+edObj.invokingClass+'" ('+ ((edObj.isTouchDevice) ? 'touch-device' : 'non-touch-device') + ', backend: '+ edObj.backend+')');
} // initializeEditableField




//--------------------------------------------------------------
function initializeConnection()
{
    var edObj = editableObj;
    var res = false;
    var json = '';

    $('.' + edObj.invokingClass + ' input').addClass('lzy-wait');
    this.ajaxSend(edObj, 'conn', '', json, function(json) {
        console.log('Server connection established: ' + json);
        if (json.match(/^</)) {
            console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
            return;
        }
        if (json.match(/failed/)) {
            console.log('Session aborted');
            $('main').html("<h1>Error connecting to server.</h1><p>Please try again with another browser or another computer.</p>");
            res = false;
        }
        res = true;
        $('.' + edObj.invokingClass + ' input').removeClass('lzy-wait');
    });
    return res;
} // initializeConnection





//--------------------------------------------------------------
function initEditableFields()
{
    var edObj = editableObj;
    var $editable = $('.'+edObj.invokingClass);
    var h = $editable.height();

    if ($('body').hasClass('legacy')) {
        var okSymbol = '&radic;';
    } else {
        var okSymbol = '&#10003;';
    }
    
    $editable.each(function(inx, elem) {
        var $elem = $(elem);
        var id = $elem.attr('id');

        var showButton = $elem.hasClass('lzy-editable-show-button');
        if ($elem.hasClass('lzy-editable-auto-show-button') && edObj.isTouchDevice) {
            $elem.addClass('lzy-editable-show-button');
            showButton = true;
        }

        if (typeof id === 'undefined') {
            id =  edObj.invokingClass + '-field_' + (parseInt(inx) + 1);
            $elem.attr('id', id);
        }

        edObj.fieldIDs.push(id); // keep track of fieldIDs
        edObj.dataRef = $('[data-lzy-editable]').attr('data-lzy-editable').replace(/:.*/, '');

        var origText = $elem.text();
        $elem.text('');
        var _id = '_'+id;
        var label = $elem.attr('title');
        var buttons = '';
        if (showButton) {
            buttons = "<button class='lzy-editable-submit-button lzy-button'>"+okSymbol+"<span class='invisible'> Save</span></button>";
        }
        if (typeof label === 'undefined') {
            $elem.html('<input id="'+_id+'" aria-live="polite" aria-relevant="all" />'+buttons);
        } else {
            $elem.html('<label for="'+_id+'" class="invisible">'+label+'</label><input id="'+_id+'" aria-live="polite" aria-relevant="all" />'+buttons);
        }
        $('#'+_id).val(origText);
    }); // .each


    var $edInput = $('input', $editable);
    
    $edInput.focus(function() {        // on focus()
        onFocus(edObj, this);
    });

    $edInput.blur(function() {                 // on blur
        onBlur(edObj, this);
    });


    $editable.click(function(e) {               // on click on ✓ button
       e.stopPropagation();
        var $this = $(this);
        if (!$('input', $this ).hasClass('lzy-editable-active')) {
            return;
        }
        var width = $('.lzy-editable-submit-button', $this).width();
        var x = $(e.target).width() - e.offsetX;    // width of submit button
        if (x < width) {
            console.log('### click on assumed button');
            onOkClick(edObj, this);
        }
    });
    $('.lzy-editable-submit-button').click(function(e) {    // on click on ✓ button
       e.stopPropagation();
        onOkClick(edObj, this);
    });
} // initEditableFields()




//--------------------------------------------------------------
function initEdit(edObj, id) {
    if (lockField(edObj, id)) {
        showButton(id);
    }
} // initEdit




//--------------------------------------------------------------
function lockField(edObj, id)
{
    console.log( 'lockField: '+id );
    var url = edObj.backend + "?lock=" + id + "&ds=" + getDataRef(id);
    var $editableField = $('#' + id);
    var $inputField = $('#_' + id);
    edObj.lastText[id] = $inputField.val();
    var dataIndex = $editableField.attr('data-lzy-cell');
    if (typeof dataIndex !== 'undefined') {
        url = url + '&ref=' + dataIndex;
    }

    $inputField.addClass('lzy-editable-active').addClass('lzy-wait');

    $.get( url, function(json) {
        if (!json) {
            return;
        }
        if (json.match(/^</)) { // syntax error on host
            console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
            return;
        }
        if (json.match(/^restart/)) {      // reload request from host
            lzyReload();

        } else if (json.match(/^failed/)) {      // field already locked
            $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
            console.log( 'field was locked: '+id );
            setTimeout(function() {$inputField.removeClass('lzy-locked'); }, 60000);
            return false;

        } else if (json.match(/^protected/)) {      // field is protected
            $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
            lzyReload();
            return false;

        } else if (json.match(/^frozen/)) {      // field already locked
            $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
            console.log( 'field has been frozen: '+id );
            return false;

        } else {
            if (json.match(/^</)) {
                console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
                return;
            } else {
                $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
                if (json.match(/\#lock:ok/)) {
                    if (edObj.keyTimeout) {
                        clearTimeout(edObj.keyTimeout);
                    }
                    edObj.keyTimeout = setTimeout(timeoutAction, edObj.editModeTimeout);
                    updateUi(edObj, json);
                }
            }
        }
    });
    return true;
} // lockField




//--------------------------------------------------------------
function endEdit(edObj, _id, sendToServer)
{
    if (edObj.keyTimeout) {
        clearTimeout(edObj.keyTimeout);
        edObj.keyTimeout = false;
    }

    if (!_id) {
        _id = $('.lzy-editable-active').attr('id');
    }
    if (typeof _id === 'undefined') {
        console.log('Error: endEdit -> no lzy-editable-active found');
        return;
    }
    var $edInput = $('#'+_id);
    $edInput.removeClass('lzy-editable-active');

    if (edObj.value === false) {
        edObj.value = $edInput.val();
        console.log('WARNING: edObj.value was not properly set in endEdit()');
    }

    var id = _id.substr(1);
    if (sendToServer && (edObj.value !== edObj.lastText[id])) {
        saveAndUnlockField(edObj, id, edObj.value);
    } else {
        unlockField(edObj, id);
        $('#'+_id).val(edObj.lastText[id]);     // restore original value

    }
    edObj.lastText[id] = '';
    edObj.editing = false;
    edObj.value = false;
    edObj.doSave = false;

    hideButton(_id);
} // endEdit



//--------------------------------------------------------------
function saveAndUnlockField(edObj, id, val)
{
    console.log('saveAndUnlockField '+id+' => '+val);
    this.ajaxSend(edObj, 'save', id, val, false);
} // saveAndUnlockField



//--------------------------------------------------------------
function unlockField(edObj, id)
{
    console.log('unlockField '+id);
    this.ajaxSend(edObj, 'unlock', id, '', false);
} // unlock



//--------------------------------------------------------------
function showButton(id)
{
    if ($('#' + id + '.lzy-editable-show-button').length) {
        var w = $('#' + id).width() - $('#' + id + ' button').width() - 4;
        $('#_' + id).animate({width: w + 'px'}, 150);
    }
} // showButton



//--------------------------------------------------------------
function hideButton(_id)
{
    if ($('#' + _id).parent().hasClass('lzy-editable-show-button')) {
        $('#'+_id).animate({width: '100%'}, 150);
    }
} // hideButton



//--------------------------------------------------------------
function updateFieldContents(edObj)
{
    this.ajaxSend(edObj, 'get', '_all', '', false);
} // updateFieldContents



//--------------------------------------------------------------
function ajaxSend(edObj, cmd, id, data, onSuccess)
//  ds => ticket hash identifying db
//  ref => data index in case of 2D data
{
    if (id === '_all') {
        var method = 'GET';
        var postData = '';
        var arg = 'get=_all&ds=' + edObj.dataRef + ':0';
        _ajaxSend(edObj, arg, method, postData, onSuccess);
        return;
    }

    var dataRef = '';
    if (id) {
        $('#_' + id).addClass('lzy-wait');

        // 1. data-lzy-editable -> defines datasource
        dataRef = '&ds=' + getDataRef(id);

        // 2. data-lzy-cell -> optional cell index
        var dataIndex = $('#' + id).attr('data-lzy-cell');
        if (typeof dataIndex !== 'undefined') {
            dataRef = dataRef + '&ref=' + dataIndex;
        }
    }

    if (cmd === 'conn') {
        data = encodeURIComponent(data);
        var method = 'POST';
        var arg = 'conn' + dataRef;
        var postData = 'ids='+data;

    } else if (cmd === 'save') {
        data = encodeURIComponent(data);
        var method = 'POST';
        var arg = 'save=' + id + dataRef;
        var postData = 'text='+data;

    } else {
        var method = 'GET';
        var arg = cmd+'=' + id + dataRef;
        var postData = '';
    }
    _ajaxSend(edObj, arg, method, postData, onSuccess);
} // ajaxSend



//--------------------------------------------------------------
function _ajaxSend(edObj, arg, method, postData, onSuccess)
{
    console.log('- request: ' + arg);
    $.ajax({
        url: edObj.backend + '?' + arg,
        type: method,
        data: postData,
        cache: false,
        success: function (json) {
            if (json.match(/^</)) {
                console.log('- response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
                return;
            }
            console.log('- response: ' + json);
            if ((json != 'failed') && onSuccess) {
                onSuccess(json);
            }
            updateUi(edObj, json);
        }
    });
} // _ajaxSend



//--------------------------------------------------------------
function updateUi(edObj, json)
{
    if (json && json.match(/^[\[{]/)) { // it's an array
        json = json.replace(/(.*[\]}])\#.*/, "$1");    // remove trailing #comment
        var data = JSON.parse(json);
        if (data) {
            if (typeof data[0] !== 'undefined') {   // array data:
                for (var i in data) {
                    var srcRef = edObj.dataRef + ':' + i;
                    var $area = $('[data-lzy-editable="' + srcRef + '"]');
                    for (var r in data[i]) {
                        for (var c in data[i][r]) {
                            var val = data[i][r][c];
                            var ref = c + ',' + r;
                            $('[data-lzy-cell="' + ref + '"] input', $area).val(val);
                        }
                    }
                }

            } else {    // data identified by ids:
                for (var id in data) {
                    if ((id === 'undefined') || !id || (id.substr(0,1) == '_')) {
                        continue;
                    }
                    var $input = $('#'+id+' input');
                    if (typeof data[id] != 'undefined') {
                        var value = data[id];
                        console.log('-- ' + id + ': ' + value);
                        if (value && value.match(/\*\*LOCKED\*\*/)) {
                            value = data[id].replace('**LOCKED**', '');
                            $input.addClass('lzy-locked').attr('title', 'Currently being editied by another user.').attr('disabled','');

                        } else if (value && value.match(/\*\*FROZEN\*\*/)) {
                            value = data[id].replace('**FROZEN**', '');
                            $input.addClass('lzy-editable-frozen').attr('title', 'Editing time expired.').attr('disabled','');

                        } else if ($input.hasClass('lzy-locked')) {
                            $input.removeClass('lzy-locked').removeAttr('title').removeAttr('disabled');
                            $('.lzy-edit_buttons', '#'+id).show();
                        }
                        $input.val(value);
                    }
                }

            }
        }

    } else if (json.match(/^restart/)) {
        lzyReload();
    } else if (json.match(/^frozen/)) {
        var editingField = $('.lzy-editable-active').attr('id');
        $('#' + editingField).addClass('lzy-editable-frozen');
    }

    $('.lzy-wait').removeClass('lzy-wait'); // de-activate 'wait' indication

} // updateUi



//--------------------------------------------------------------
function getDataRef(id) {
    var dataRef = $('#' + id).attr('data-lzy-editable');
    if (typeof dataRef === 'undefined') {
        dataRef = $('#' + id).closest('[data-lzy-editable]').attr('data-lzy-editable');
    }
    return dataRef;
} // getDataRef




//--------------------------------------------------------------
function setupKeyHandler()
{
    var edObj = editableObj;
    $('.'+edObj.invokingClass+' input').keydown(function (e) {
        var key = e.which;
        if (key == 9) {                  // Tab
            onKeyTab(e);
        }
    });

    $('.'+edObj.invokingClass+' input').keypress(function (e) {
        if ($(e.target).hasClass('lzy-wait')) {
        // if ($(e.target).parent().hasClass('lzy-wait')) {
            e.preventDefault();

        } else {                // reset editing mode timeout
            if (edObj.keyTimeout) {
                clearTimeout(edObj.keyTimeout);
            }
            var _id = $(e.target).attr('id');
            edObj.keyTimeout = setTimeout(timeoutAction, edObj.editModeTimeout);
        }
    });
    $('.'+edObj.invokingClass+' input').keyup(function (e) {
        var key = e.which;
        e.preventDefault();
        if (key == 13) {             // Enter
            onKeyEnter(e);
        }
        if(key == 27) {                // ESC
            onKeyEsc(e);
        }
    });
} // setupKeyHandler



//=== Event Handlers ===========================================
function onFocus(edObj, that)
{
    var id = $(that).parent().attr('id');
    initEdit(edObj, id);
}



function onBlur(edObj, that)
{
    var _id = $(that).attr('id');
    edObj.value = $('#' + _id).val();

    // if button not shown, all blurs save values:
    if (!$('#' + _id).parent().hasClass('lzy-editable-show-button')) {
        edObj.doSave = true;
    }

    setTimeout(function() {
        endEdit(edObj, _id, edObj.doSave);
    }, 150);
}



function onOkClick(edObj, that)
{
    var id = $(that).parent().attr('id');
    edObj.doSave = true;
    if (!edObj.value) {
        edObj.value = $('#_' + id).val();
    }
}



function onKeyEnter(e)
{
    var edObj = editableObj;
    var _id = $(e.target).attr('id');
    edObj.doSave = true;
    $('#'+_id).blur();
}



function onKeyTab(e)
{
    var edObj = editableObj;
    var _id = $(e.target).attr('id');
    e.preventDefault();
    edObj.doSave = true;
    $('#'+_id).blur();

    // find next editable input field and put focus there:
    var inx = edObj.fieldIDs.indexOf(_id.substr(1));
    if (inx >= edObj.fieldIDs.length) {
        return;
    }
    var next = edObj.fieldIDs[inx + 1];
    var $next = $('#'+next + ' input');
    if ($next.length) {
        setTimeout(function () {
            $next.focus();
        }, 300);
    }
}



function onKeyEsc(e)
{
    var edObj = editableObj;
    var _id = $(e.target).attr('id');
    var id = _id.substr(1);
    $('#'+_id).val(edObj.lastText[id]);
    $('#'+_id).blur();
    return;
}




function timeoutAction()
{
    console.log('edit mode timed out');
    $('.lzy-editable-active').blur();
}

