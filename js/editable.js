/*
 *  Editable.js
*/


var editableObj = new Object();
editableObj.backend = systemPath+'_ajax_server.php';
editableObj.doSave = false;
editableObj.lastText = {};
editableObj.editing = false;
editableObj.keyTimeout = false;
editableObj.fieldIDs = [];



(function ( $ ) {

    $.fn.editable = function( options ) {
        if ( this.length == 0 ) {
            return;
        }
        var invokingClass = $(this).attr('class').replace(/\s.*/, '');
        if (typeof invokingClass == 'undefined') {
            invokingClass = 'lzy-editable';
        }
        initializeEditableField(invokingClass);
    };

    $( window ).bind('beforeunload', function(){
        endEdit(editableObj, false, true);
        serverLog('Client disconnected');
    });
}( jQuery ));




function initializeEditableField(invokingClass)
{
    var edObj = editableObj;
    edObj.editModeTimeout = 60000;  // automatically terminate editing after 20s of no key activity
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

    mylog('Editable.js started for class ".'+edObj.invokingClass+'" ('+ ((edObj.isTouchDevice) ? 'touch-device' : 'non-touch-device') + ', backend: '+ edObj.backend+')');
} // initializeEditableField




//--------------------------------------------------------------
function initializeConnection()
{
    var edObj = editableObj;
    var res = false;
    var json = JSON.stringify(edObj.fieldIDs);
    $('.lzy-editable input').addClass('lzy-wait');
    this.ajaxSend(edObj, 'conn', '', json, function(json) {
        mylog('Server connection established: ' + json);
        if (json.match(/failed/)) {
            mylog('Session aborted');
            $('main').html("<h1>Error connecting to server.</h1><p>Please try again with another browser or another computer.</p>");
            res = false;
        }
        res = true;
        $('.lzy-editable input').removeClass('lzy-wait');
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
        // showButton: in auto-mode on touch-device we need to add class:
        if ($elem.hasClass('lzy-editable-auto-show-button') && edObj.isTouchDevice) {
            $elem.addClass('lzy-editable-show-button');
            showButton = true;
        }

        if (typeof id == 'undefined') {
            id =  edObj.invokingClass + '_field' + (parseInt(inx) + 1);
            $elem.attr('id', id);
        }

        edObj.fieldIDs.push(id); // keep track of fieldIDs

        var origText = $elem.text();
        $elem.text('');
        var _id = '_'+id;
        var label = $elem.attr('title');
        var buttons = '';
        if (showButton) {
            buttons = "<button class='lzy-editable-submit-button lzy-button'>"+okSymbol+"<span class='invisible'> Save</span></button>";
        }
        if (typeof label == 'undefined') {
            $elem.html('<input id="'+_id+'" aria-live="polite" aria-relevant="all" />'+buttons);
        } else {
            $elem.html('<label for="'+_id+'" class="invisible">'+label+'</label><input id="'+_id+'" aria-live="polite" aria-relevant="all" />'+buttons);
        }
        $('#'+_id).val(origText);
    }); // .each


    var $edInput = $('input', $editable);
    
    // activate 'loading' indication
    // $editable.addClass('lzy-wait');

    $edInput.focus(function(e) {                // on focus()
        onFocus(edObj, this);
    });

    $edInput.blur(function(e) {                 // on blur
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
            mylog('### click on assumed button');
            onOkClick(edObj, this);
        }
    });
    $('.lzy-editable-submit-button').click(function(e) {    // on click on ✓ button
       e.stopPropagation();
        onOkClick(edObj, this);
    });

} // initEditableFields()




//--------------------------------------------------------------
function initEdit(edObj, id, dataSrc) {
    if (lockField(edObj, id, dataSrc)) {
        showButton(id);
    }
} // initEdit



//--------------------------------------------------------------
function lockField(edObj, id, dataSrc)
{
    mylog( 'lockField: '+id );
    var url = edObj.backend + "?lock=" + id + "&ds=" + dataSrc +'&pg='+pagePath;
    var $editableField = $('#' + id);
    var $inputField = $('#_' + id);
    edObj.lastText[id] = $inputField.val();

    $inputField.addClass('lzy-editable-active').addClass('lzy-wait');

    $.get( url, function(json) {
        if (!json) {
            return;
        }
        if (json == 'restart') {
            lzyReload();

        } else if (json == 'failed') {      // field already locked
            $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
            alert(lzy_editable_msg);
            mylog( 'field was locked: '+id );
            return false;

        } else if (json == 'frozen') {      // field already locked
            $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
            mylog( 'field has been frozen: '+id );
            return false;

        } else {
            json = json.replace(/(\{.*\}).*/, "$1");    // remove trailing #comment
            var data = JSON.parse(json);
            $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
            if (data.res == 'ok') {
                $inputField.val(data.val);
                edObj.lastText[id] = data.val;

                if (edObj.keyTimeout) {
                    clearTimeout(edObj.keyTimeout);
                }
                edObj.keyTimeout = setTimeout(timeoutAction, edObj.editModeTimeout);
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
    if (typeof _id == 'undefined') {
        mylog('Error: endEdit -> no lzy-editable-active found');
        return;
    }
    var $edInput = $('#'+_id);
    $edInput.removeClass('lzy-editable-active');

    if (edObj.value === false) {
        edObj.value = $edInput.val();
        mylog('WARNING: edObj.value was not properly set in endEdit()');
    }

    var id = _id.substr(1);
    if (sendToServer && (edObj.value != edObj.lastText[id])) {
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
    mylog('saveAndUnlockField '+id+' => '+val);
    this.ajaxSend(edObj, 'save', id, val, false);
} // saveAndUnlockField



//--------------------------------------------------------------
function unlockField(edObj, id)
{
    mylog('unlockField '+id);
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
        // var w = $('#' + _id.substr(1)).width();
        $('#'+_id).animate({width: '100%'}, 150);
    }
} // hideButton



//--------------------------------------------------------------
function ajaxSend(edObj, cmd, id, data, onSuccess, synchronous)
{
    if (id) {
        $('#_' + id).addClass('lzy-wait');
    }
    if (cmd == 'conn') {
        data = encodeURIComponent(data);
        var method = 'POST';
        var arg = 'conn'+'&pg='+pagePath;
        var postData = 'ids='+data;

    } else if (cmd == 'save') {
        data = encodeURIComponent(data);
        var method = 'POST';
        // var arg = 'save='+id;
        var arg = 'save='+id+'&pg='+pagePath;
        var postData = 'text='+data;

    } else {
        var method = 'GET';
        // var arg = cmd+'='+id;
        var arg = cmd+'='+id+'&pg='+pagePath;
        var postData = '';
    }
    $.ajax({
        url: edObj.backend + "?" + arg,
        type: method,
        data: postData,
        cache: false,
        success: function (json) {
            if ((json != 'failed') && onSuccess) {
                onSuccess(json);
            }
            updateUi(edObj, json);
        }
    });
} // ajaxSend


//--------------------------------------------------------------
function updateUi(edObj, json)
{
    if (json && json.match(/^\{/)) {
        json = json.replace(/(\{.*\}).*/, "$1");    // remove trailing #comment
        var data = JSON.parse(json);
        if (data) {
            var editingField = $('.lzy-editable-active').attr('id');
            for (var id in data) {
                if ((id == 'undefined') || !id || (id.substr(0,1) == '_')) {
                    continue;
                }

                if (editingField == '_'+id) {  // curr editing field is editing field
                    continue;   // do not update editing field
                }

                var $input = $('#'+id+' input');
                if (typeof data[id] != 'undefined') {
                    var value = data[id];
                    if (value.match(/\*\*LOCKED\*\*/)) {
                        value = data[id].replace('**LOCKED**', '');
                        $input.addClass('lzy-locked').attr('title', 'Currently being editied by another user.').attr('disabled','');

                    } else if ($input.hasClass('lzy-locked')) {
                        $input.removeClass('lzy-locked').removeAttr('title').removeAttr('disabled');
                        $('.lzy-edit_buttons', '#'+id).show();
                    }
                    $input.val(value);
                }
            }
        }
    } else if (json == 'restart') {
        lzyReload();
    } else if (json == 'frozen') {
        var editingField = $('.lzy-editable-active').attr('id');
        $('#' + editingField).addClass('lzy-editable-frozen');
    }

    $('.lzy-wait').removeClass('lzy-wait'); // de-activate 'loading' indication

} // updateUi




//===================================================

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
    var dataSrc = $('#' + id).attr('data-storage-path');
    initEdit(edObj, id, dataSrc);
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

