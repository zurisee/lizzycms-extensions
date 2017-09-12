/*
**    Editable field
*/

(function ( $ ) {

    $.fn.editable = function( options ) {
        var settings = $.extend({    // default values:
            'backend': systemPath+'_ajax_server.php',
            'touchMode': 'auto', // 'auto', true, false
        }, options );
        var invokingClass = $(this).attr('class');
        if (typeof invokingClass == 'undefined') {
            invokingClass = 'undefined';
        } else {
            if (invokingClass.match(/editable/)) {
                invokingClass = 'editable';
            } else {
                invokingClass = invokingClass.match(/^\w+/);
                invokingClass = (typeof invokingClass != 'undefined') ? invokingClass[0] : '';
            }
        }
        editableField(invokingClass, settings);
    };

}( jQuery ));

var editableObj = new Object();

function editableField(invokingClass, settings)
{
    var edObj = editableObj;
    edObj.currentRequest = null;
    edObj.invokingClass = invokingClass;
    edObj.isTouchDevice = 'ontouchstart' in document.documentElement;
    edObj.backend = settings.backend; // appRoot supplied by script snippet in header
    if (settings.touchMode == 'auto') {
        edObj.touchMode = edObj.isTouchDevice;
    } else {
        edObj.touchMode = (settings.touchMode == 'true');
    }
    edObj.loadOnly = settings.loadOnly;
    edObj.lastText = '';
    edObj.cycleTime =  1000; // [ms]    // -1 for debugging
    edObj.inpWidth = {};
    edObj.btnWidth = {};
    edObj.editing = false;
    edObj.appName = $('body').attr('class');
    if (typeof edObj.appName != 'undefined') {
        edObj.appName = edObj.appName.replace(/\s.*/, '');
        edObj.appName = edObj.appName.replace(/page_/, '');
    } else {
        edObj.appName = 'common_data';
    }

    initEditableFields();
    setupKeyHandler();
    if (!initializeConnection()) {
        return;
    }

    $(window).resize(function() {
        setWidths(edObj);
    });

    $(window).bind('beforeunload', function(){
        remoteLog('Client disconnected');
        endEdit(edObj, false, true);
    });

    myLog('Editable.js started for class ".'+edObj.invokingClass+'"'+ ((edObj.touchMode) ? ' in touch screen' : ' in desktop') + ' mode (backend: '+ edObj.backend+')');
    if (edObj.touchMode) {
        myLog('touchMode enabled!');
    }
} // editableField



//--------------------------------------------------------------
function initEditableFields()
{
    var edObj = editableObj;
    var $editable = $('.'+edObj.invokingClass);
    var parentId = $editable.parent().attr('id');
    if (typeof parentId == 'undefined') {
        parentId = 'editable_';
    } else {
        edObj.appName = parentId;
    }
    var h = $editable.height();

    $editable.each(function(inx, elem) {
        var $elem = $(elem);
        var id = $elem.attr('id');
        if (typeof id == 'undefined') {
            id = parentId + (parseInt(inx) + 1);
            $elem.attr('id', id);
        }
        var origText = $elem.text();
        if (!edObj.loadOnly) {
            $elem.text('');
            var _id = '_'+id;
            var label = $elem.attr('title');
            var buttons = '';
            if (edObj.touchMode) {
                buttons = "<div class='edit_buttons'><button class='ios_button submit'>&#10003;<span class='invisible'> Speichern</span></button><button class='ios_button cancel'>&#10007;<span class='invisible'> Abbrechen</span></button></div>";
            }
            if (typeof label == 'undefined') {
                $elem.html('<input id="'+_id+'" aria-live="polite" aria-relevant="all">'+buttons);
            } else {
                $elem.html('<label for="'+_id+'" class="invisible">'+label+'</label><input id="'+_id+'" aria-live="assertive" aria-relevant="all">'+buttons);
            }
            $('#'+_id).val(origText);
            setWidth(edObj, id);
        }
    }); // .each

    $('.edit_buttons button').height(h - 4);
    var $edInput = $('input', $editable);

    $edInput.focus(function(e) {
        var id = $(this).parent().attr('id');
        if (!$('#_'+id).hasClass('locked')) {
            initEdit(edObj, id);
        } else {
            myLog('focus: input field is locked ('+id+')');
            e.preventDefault();
        }
    });
    $edInput.blur(function(e) {
        var _id = $(this).attr('id');
        endEdit(edObj, _id, true);
    });
    $('.edit_buttons button.submit').click(function(e) {
        var id = $(this).parent().parent().attr('id');
        $('#_'+id).blur();
    });
    $('.edit_buttons button.cancel').click(function(e) {
        var id = $(this).parent().parent().attr('id');
        $('#_'+id).val(edObj.lastText);
    });
} // initEditableFields()



//--------------------------------------------------------------
function setWidths(edObj)
{
    var $editable = $('.'+this.invokingClass);
    var w = $editable.width();
    var h = $editable.height();
    var parentId = $editable.parent().attr('id');
    if (typeof parentId == 'undefined') {
        parentId = 'editable_';
    }
    $editable.each(function(inx, elem) {
        var id = $(elem).attr('id');
        setWidth(edObj, id);
    });
} // setWidths



function setWidth(edObj, id)
{
    var _id = '_'+id;
    var $elem = $('#'+id);
    var $inpElem = $('#'+_id);
    var height = $elem.height();
    var width = $elem.width() - parseInt($inpElem.css('padding-right')) - parseInt($inpElem.css('padding-left')) - 2;
    var btnWidth = height * 1.5;
    $inpElem.width(width).height(height-2);
    $('button', $elem).width(btnWidth).height(height);
    edObj.inpWidth[_id] = $elem.width();
    edObj.btnWidth[_id] = 2 * (btnWidth + 4);
} // setWidths



//--------------------------------------------------------------
function setupKeyHandler()
{
    var edObj = editableObj;
    $('.editable input').keypress(function (e) {
        if ($(e.target).hasClass('wait')) {
            e.preventDefault();
        }
    });
    $('.editable input').keyup(function (e) {
        var key = e.which;
        var _id = $(e.target).attr('id');
        if (key == 13) {             // Enter
            myLog('keypress: Enter');
            e.preventDefault();
            $('#'+_id).blur();
            return false;
        }
        if(key == 27) {                // ESC
            myLog('keyup: Esc ('+_id+') ['+edObj.lastText+']');
            $('#'+_id).val(edObj.lastText);
            $('#'+_id).blur();
            return false;
        }
    });
} // setupKeyHandler



//--------------------------------------------------------------
function initEdit(edObj, id)
{
    this.lock(id, edObj);
    if (edObj.touchMode) {
        var _id = '_'+id;
        var shrunkWidth = edObj.inpWidth[_id] - edObj.btnWidth[_id] - 4;
        $('#'+_id).animate({'width': shrunkWidth}, 250);
    }
} // initEdit



//--------------------------------------------------------------
function endEdit(edObj, _id, sendToServer)
{
    if (!_id) {
        _id = $('.editable_active').attr('id');
    }
    if (typeof _id == 'undefined') {
        myLog('Error: endEdit -> no editable_active found');
        return;
    }
    var $edInput = $('#'+_id);
    if (edObj.touchMode) {
        $edInput.animate({'width': edObj.inpWidth[_id]}, 250);
    }
    $edInput.removeClass('editable_active');
    var val = $edInput.val();
    if (typeof val != 'undefined') {
        if (sendToServer && (val != edObj.lastText)) {
            ajaxSend(edObj, 'save', _id.substr(1), val, false);
        } else {
            unlock(_id.substr(1), edObj);
            $('#'+_id).val(edObj.lastText);
        }
        edObj.lastText = '';
        edObj.editing = false;
    } else {
        myLog('Error: endEdit -> no value available');
    }
} // endEdit



//--------------------------------------------------------------
function lock(id, edObj)
{
    this.ajaxSend(edObj, 'lock', id, '', function(json) {
        var _id = '_'+id;
        edObj.editing = id;
        var $edInput = $('#'+_id);
        edObj.lastText = $edInput.val();
        $edInput.addClass('editable_active').select();
    });
} // lock



//--------------------------------------------------------------
function unlock(id, edObj)
{
    this.ajaxSend(edObj, 'unlock', id, '', false);
} // unlock



//--------------------------------------------------------------
function initializeConnection()
{
    var edObj = editableObj;
    var res = false;
    this.ajaxSend(edObj, 'conn', '', '', function(json) {
        myLog('Connecting server: ' + json);
        if (json.match(/failed/)) {
            myLog('Session aborted');
            $('main').html("<h1>Error connecting to server.</h1><p>Please try again with another browser or another computer.</p>");
            res = false;
        }
        res = true;
    }, true);   // synchronous request
    return res;
} // initializeConnection



//--------------------------------------------------------------
function update()
{
    var edObj = editableObj;
    this.ajaxSend(edObj, 'upd', edObj.isTouchDevice, '', false);
} // update



//--------------------------------------------------------------
function updateUi(edObj, json)
{
    if (json && json.match(/^\{/)) {
        myLog('updateUi: ['+json+']');
        json = json.replace(/\#.*/, '');
        var data = JSON.parse(json);
        if (data) {
            for (var id in data) {
                if ((id == 'undefined') || !id || (id.substr(0,1) == '_')) {
                    continue;
                }

                var $input = $('#'+id+' input');
                if (this.editing == id) {                       // curr editing field
                    if (typeof data[id] == 'undefined') {    // lost its lock -> end editing
                        myLog('updateUi: editing mode timed out');
                        $input.val(edObj.lastText).blur();
                    }
                    continue;   // inhibit updating editing field
                }

                if (typeof data[id] != 'undefined') {
                    var value = data[id];
                    if (value.match(/\*\*LOCKED\*\*/)) {
                        value = data[id].replace('**LOCKED**', '');
                        $input.addClass('locked').attr('title', 'Derzeit von anderem Benutzer bearbeitet').attr('disabled','');

                    } else if ($input.hasClass('locked')) {
                        $input.removeClass('locked').removeAttr('title').removeAttr('disabled');
                        $('.edit_buttons', '#'+id).show();
                    }
                    $input.val(value);
                }
            }
        }
    }
} // updateUi



//--------------------------------------------------------------
function myLog( text )
{
    if (typeof myLog !== "undefined") {
        mylog(text);
    } else {
        console.log(text);
    }
} // myLog



//--------------------------------------------------------------
function remoteLog(text)
{
    if (!text) {
        return;
    }
    // var edObj = edObj;
    var edObj = editableObj;
    $.ajax({
        url: edObj.backend+'?log='+text,
        type: 'GET',
        data: '',
        async: true,
        cache: false,
    }).fail(function() {
        myLog('Error: no backend found');
    });
} // remoteLog()



//--------------------------------------------------------------
function ajaxSend(edObj, cmd, id, data, onSuccess, synchronous)
{
    if (cmd != 'save') {
        var method = 'GET';
        var arg = cmd+'='+id;
        var postData = '';
    } else {
        var method = 'POST';
        var arg = 'save='+id;
        var postData = 'text='+data;
    }
    if (cmd == 'upd') {
        edObj.currentRequest = $.ajax({
            url: edObj.backend+"?"+arg,
            type: method,
            data: postData,
            async: true,
            cache: false,
            beforeSend : function()    {
                if(edObj.currentRequest != null) {
                    edObj.currentRequest.abort();
                }
            },
            success: function (json) {
                updateUi(edObj, json);
                update();
            }
        });

    } else {
        if (id) {
            $('#_'+id).addClass('wait');
        }
        if ((typeof synchronous == 'undefined') || !synchronous) {     // normal synchronous request:
            $.ajax({
                url: edObj.backend + "?" + arg,
                type: method,
                data: postData,
                async: true,
                cache: false,
                beforeSend: function () {
                    if (edObj.currentRequest != null) {
                        edObj.currentRequest.abort();
                    }
                },
                success: function (json) {
                    myLog(cmd + ': ' + id + ' -> ' + json);
                    if ((json != 'failed') && onSuccess) {
                        onSuccess(json);
                    }
                    if (id) {
                        $('#_' + id).removeClass('wait');
                    }
                    updateUi(edObj, json);
                    update();
                }
            });
        } else {    // async request:
            $.ajax({
                url: edObj.backend + "?" + arg,
                type: method,
                data: postData,
                async: false,
                cache: false,
                success: onSuccess,
            });

        }
    }
} // ajaxSend
