/*
 *  Editable.js
*/

var editableObj = new Object();
editableObj.backend = systemPath+'extensions/editable/backend/_editable_backend.php';
editableObj.doSave = false;
editableObj.lastText = {};
editableObj.editing = false;
editableObj.ignore = false;
editableObj.keyTimeout = false;
editableObj.fieldIDs = [];
editableObj.dataRef = [];

var instanceCounter = 0;


// (function ( $ ) {
//
//     $.fn.editable = function( options ) {
//         if ( this.length === 0 ) {
//             return;
//         }
//         instanceCounter++;
//         var invokingClass = $(this).attr('class').replace(/\s.*/, '');
//         if (typeof invokingClass === 'undefined') {
//             invokingClass = 'lzy-editable';
//         }
//         initializeEditableField(invokingClass);
//     };
//
//     // $('.lzy-editable').editable();
//
//     $( window ).bind('beforeunload', function(){
//         endEdit(editableObj, false, true);
//     });
// }( jQuery ));

(function ( $ ) {
    $('.lzy-editable').attr('tabindex', 0);

    $('.lzy-editable').click(function () {
        mylog('make editable...');
        $this = $( this );
        initEditableField( $this );
    });


}( jQuery ));



function initEditableField( $elem )
{
    var edObj = editableObj;
    var okSymbol = '&#10003;';

    if ($('body').hasClass('legacy')) {
        okSymbol = '&radic;';
    }
    var id = $elem.attr('id');
    edObj.dataRef = $elem.data('lzyDataRef');

    if (lockField(edObj, id)) {
        revealButton(id);
    }

    var showButton = $elem.hasClass('lzy-editable-show-button');
    if ($elem.hasClass('lzy-editable-auto-show-button') && edObj.isTouchDevice) {
        $elem.addClass('lzy-editable-show-button');
        showButton = true;
    }

    if (typeof id === 'undefined') {
        id =  edObj.invokingClass.replace(/\s.*/, '') + '-' + (parseInt(inx) + 1);
        $elem.attr('id', id);
    }

    var origText = $elem.text();
    var newText = origText;
    var _id = '_'+id;
    var buttons = '';
    if (showButton) {
        buttons = "<button class='lzy-editable-submit-button lzy-button'>"+okSymbol+"<span class='invisible'> Save</span></button>";
    }
    $elem.removeClass('lzy-editable').addClass('lzy-editable-active');
    $elem.html('<input id="'+_id+'" aria-live="polite" aria-relevant="all" value="' + origText + '"/>'+buttons);
    $('#' + _id)
        .click(function (e) { e.stopPropagation(); })
        .focus()
        .blur(function () {
            newText = $(this).val();
            saveAndUnlockField(edObj, id, newText);
            $elem.text( newText ).removeClass('lzy-editable-active').addClass('lzy-editable');
        });
} // initEditableField



//--------------------------------------------------------------
function lockField(edObj, id)
{
    console.log( 'lockField: '+id );
    var url = edObj.backend + '?lock=' + id + '&ds=' + getDataRef(id);
    var $editableField = $('#' + id);
    var $inputField = $('#_' + id);
    // edObj.lastText[id] = $inputField.val();
    // var dataIndex = $editableField.attr('data-lzy-cell');
    // if (typeof dataIndex !== 'undefined') {
    //     url = url + '&ref=' + dataIndex;
    // }

    $inputField.addClass('lzy-wait');
    // $inputField.addClass('lzy-editable-active').addClass('lzy-wait');

    $.ajax({
        url: url,
        type: 'get',
        // data: {text: newValue},
        cache: false,
        success: function (json) {
            updateUi(edObj, json);
        }
    });

    // $.get( url, function(json) {
    //     if (!json) {
    //         return;
    //     }
    //     if (json.match(/^</)) { // syntax error on host
    //         console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
    //         return;
    //     }
    //     if (json.match(/^restart/)) {      // reload request from host
    //         lzyReload();
    //
    //     } else if (json.match(/^failed/)) {      // field already locked
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         console.log( 'field was locked: '+id );
    //         setTimeout(function() {$inputField.removeClass('lzy-locked'); }, 60000);
    //         return false;
    //
    //     } else if (json.match(/^protected/)) {      // field is protected
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         lzyReload();
    //         return false;
    //
    //     } else if (json.match(/^frozen/)) {      // field already locked
    //         $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
    //         console.log( 'field has been frozen: '+id );
    //         return false;
    //
    //     } else {
    //         $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
    //         json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
    //         var data = JSON.parse(json);
    //         var txt = '';
    //         var $el = null;
    //         if ((data.result === 'ok') && (typeof data.data === 'object')) {
    //             for (var tSel in data.data) {
    //                 txt = data.data[ tSel ];
    //                 $el = $( tSel + ' input' );
    //                 if ($el.length) {   // it's the active input field:
    //                     edObj.lastText = txt;
    //                     $el.val( txt );
    //                     $el[0].setSelectionRange(txt.length, txt.length); // place cursor at end of string
    //
    //                 } else {
    //                     $( tSel ).text( txt );
    //                 }
    //             }
    //         }
    //     }
    // });
    return true;
} // lockField



//--------------------------------------------------------------
function saveAndUnlockField(edObj, id, newValue)
{
    if (edObj.lastText === newValue) {
        console.log('unchanged, not saving');
        return;
    }
    console.log( 'save: '+id );
    // var txt = $(id + ' input').val();
    var url = edObj.backend + '?save=' + id + '&ds=' + getDataRef(id);
    // var $editableField = $('#' + id);
    // var $inputField = $('#_' + id);

    $.ajax({
        url: url,
        type: 'post',
        data: { text: newValue },
        cache: false,
        success: function (json) {
            updateUi(edObj, json);
        }
    });

    // edObj.lastText[id] = $inputField.val();
    // var dataIndex = $editableField.attr('data-lzy-cell');
    // if (typeof dataIndex !== 'undefined') {
    //     url = url + '&ref=' + dataIndex;
    // }
    //
    // $inputField.addClass('lzy-wait');
    // $inputField.addClass('lzy-editable-active').addClass('lzy-wait');
    //
    // $.get( url, function(json) {
    //     if (!json) {
    //         return;
    //     }
    //     if (json.match(/^</)) { // syntax error on host
    //         console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
    //         return;
    //     }
    //     if (json.match(/^restart/)) {      // reload request from host
    //         lzyReload();
    //
    //     } else if (json.match(/^failed/)) {      // field already locked
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         console.log( 'field was locked: '+id );
    //         setTimeout(function() {$inputField.removeClass('lzy-locked'); }, 60000);
    //         return false;
    //
    //     } else if (json.match(/^protected/)) {      // field is protected
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         lzyReload();
    //         return false;
    //
    //     } else if (json.match(/^frozen/)) {      // field already locked
    //         $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
    //         console.log( 'field has been frozen: '+id );
    //         return false;
    //
    //     } else {
    //         // $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
    //         json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
    //         var data = JSON.parse(json);
    //         var txt = '';
    //         var $el = null;
    //         if ((data.result === 'ok') && (typeof data.data === 'object')) {
    //             for (var tSel in data.data) {
    //                 txt = data.data[ tSel ];
    //                 $el = $( tSel + ' input' );
    //                 if ($el.length) {   // it's the active input field:
    //                     edObj.lastText = txt;
    //                     $el.val( txt );
    //                     $el[0].setSelectionRange(txt.length, txt.length); // place cursor at end of string
    //
    //                 } else {
    //                     $( tSel ).text( txt );
    //                 }
    //             }
    //         }
    //     }
    // });
    return true;
} // saveAndUnlockField



//--------------------------------------------------------------
function unlockField(edObj, id)
{
    console.log( 'unlockField: '+id );
    var url = edObj.backend + '?unlock=' + id + '&ds=' + getDataRef(id);
    $.ajax({
        url: url,
        type: 'get',
        cache: false,
        success: function (json) {
            updateUi(edObj, json);
        }
    });

    // var $editableField = $('#' + id);
    // var $inputField = $('#_' + id);
    //
    // edObj.lastText[id] = $inputField.val();
    // var dataIndex = $editableField.attr('data-lzy-cell');
    // if (typeof dataIndex !== 'undefined') {
    //     url = url + '&ref=' + dataIndex;
    // }

    // $inputField.addClass('lzy-wait');
    // $inputField.addClass('lzy-editable-active').addClass('lzy-wait');

    // $.get( url, function(json) {
    //     if (!json) {
    //         return;
    //     }
    //     if (json.match(/^</)) { // syntax error on host
    //         console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
    //         return;
    //     }
    //     if (json.match(/^restart/)) {      // reload request from host
    //         lzyReload();
    //
    //     } else if (json.match(/^failed/)) {      // field already locked
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         console.log( 'field was locked: '+id );
    //         setTimeout(function() {$inputField.removeClass('lzy-locked'); }, 60000);
    //         return false;
    //
    //     } else if (json.match(/^protected/)) {      // field is protected
    //         $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
    //         lzyReload();
    //         return false;
    //
    //     } else if (json.match(/^frozen/)) {      // field already locked
    //         $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
    //         console.log( 'field has been frozen: '+id );
    //         return false;
    //
    //     } else {
    //         // $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
    //         json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
    //         var data = JSON.parse(json);
    //         var txt = '';
    //         var $el = null;
    //         if ((data.result === 'ok') && (typeof data.data === 'object')) {
    //             for (var tSel in data.data) {
    //                 txt = data.data[ tSel ];
    //                 $el = $( tSel + ' input' );
    //                 if ($el.length) {   // it's the active input field:
    //                     edObj.lastText = txt;
    //                     $el.val( txt );
    //                     $el[0].setSelectionRange(txt.length, txt.length); // place cursor at end of string
    //
    //                 } else {
    //                     $( tSel ).text( txt );
    //                 }
    //             }
    //         }
    //     }
    // });
    return true;
} // unlockField



//--------------------------------------------------------------
function revealButton(id)
{
    if ($('#' + id + '.lzy-editable-show-button').length) {
        var w = $('#' + id).width() - $('#' + id + ' button').width() - 4;
        $('#_' + id).animate({width: w + 'px'}, 150);
    }
} // revealButton



//--------------------------------------------------------------
function hideButton(_id)
{
    if ($('#' + _id).parent().hasClass('lzy-editable-show-button')) {
        $('#'+_id).animate({width: '100%'}, 150);
    }
} // hideButton


//--------------------------------------------------------------
function getDataRef(id) {
    var dataRef = $('#' + id).attr('data-lzy-data-ref');
    if (typeof dataRef === 'undefined') {
        dataRef = $('#' + id).closest('[data-lzy-data-ref]').attr('data-lzy-data-ref');
    }
    // var dataRef = $('#' + id).attr('data-lzy-editable');
    // if (typeof dataRef === 'undefined') {
    //     dataRef = $('#' + id).closest('[data-lzy-editable]').attr('data-lzy-editable');
    // }
    return dataRef;
} // getDataRef





// //--------------------------------------------------------------
// function ajaxSend(edObj, cmd, id, data, onSuccess)
// //  ds => ticket hash identifying db
// //  ref => data index in case of 2D data
// {
//     if (id === '_all') {
//         var method = 'GET';
//         var postData = '';
//         var arg = 'get=_all&ds=' + edObj.dataRef;
//         // var arg = 'get=_all&ds=' + edObj.dataRef + ':0';
//         _ajaxSend(edObj, arg, method, postData, onSuccess);
//         return;
//     }
//
//     var dataRef = '';
//     if (id) {
//         $('#_' + id).addClass('lzy-wait');
//
//         // 1. data-lzy-editable -> defines datasource
//         dataRef = '&ds=' + getDataRef(id);
//
//         // 2. data-lzy-cell -> optional cell index
//         var dataIndex = $('#' + id).attr('data-lzy-cell');
//         if (typeof dataIndex !== 'undefined') {
//             dataRef = dataRef + '&ref=' + dataIndex;
//         }
//     }
//
//     if (cmd === 'conn') {
//         data = encodeURIComponent(data);
//         var method = 'POST';
//         var arg = 'conn' + dataRef;
//         var postData = 'ids='+data;
//
//     } else if (cmd === 'save') {
//         data = encodeURIComponent(data);
//         var method = 'POST';
//         var arg = 'save=' + id + dataRef;
//         var postData = 'text='+data;
//
//     } else {
//         var method = 'GET';
//         var arg = cmd+'=' + id + dataRef;
//         var postData = '';
//     }
//     _ajaxSend(edObj, arg, method, postData, onSuccess);
// } // ajaxSend
//
//
//
// //--------------------------------------------------------------
// function _ajaxSend(edObj, arg, method, postData, onSuccess)
// {
//     console.log('- request: ' + arg);
//     $.ajax({
//         url: edObj.backend + '?' + arg,
//         type: method,
//         data: postData,
//         cache: false,
//         success: function (json) {
//             if (json.match(/^</)) {
//                 console.log('- response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
//                 return;
//             }
//             console.log('- response: ' + json);
//             if ((json !== 'failed') && onSuccess) {
//                 onSuccess(json);
//             }
//             updateUi(edObj, json);
//         }
//     });
// } // _ajaxSend



function updateUi(edObj, json)
{
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
        // $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
        console.log( 'field was locked: '+id );
        setTimeout(function() {$inputField.removeClass('lzy-locked'); }, 60000);
        return false;

    } else if (json.match(/^protected/)) {      // field is protected
        // $inputField.val(edObj.lastText[id]).blur().addClass('lzy-locked');
        lzyReload();
        return false;

    } else if (json.match(/^frozen/)) {      // field already locked
        // $editableField.addClass('lzy-editable-frozen').text(edObj.lastText[id]);
        console.log( 'field has been frozen: '+id );
        return false;

    } else {
        // $inputField.removeClass('lzy-wait').removeClass('lzy-locked');
        json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        var data = JSON.parse(json);
        var txt = '';
        var $el = null;
        if ((data.result === 'ok') && (typeof data.data === 'object')) {
            for (var tSel in data.data) {
                txt = data.data[ tSel ];
                $el = $( tSel + ' input' );
                if ($el.length) {   // it's the active input field:
                    edObj.lastText = txt;
                    $el.val( txt );
                    $el[0].setSelectionRange(txt.length, txt.length); // place cursor at end of string

                } else {
                    $( tSel ).text( txt );
                }
            }
        }
    }
    // handleAjaxResponse(json);
} // updateUi


function timeoutAction()
{
    console.log('edit mode timed out');
// $('.lzy-editable-active').blur();
} timeoutAction



// function initializeEditableField(invokingClass)
// {
//     var edObj = editableObj;
//     edObj.editModeTimeout = 60000;  // automatically terminate editing after given time of no key activity
//     edObj.invokingClass = invokingClass;
//     if ($('body').hasClass('touch')) {
//         edObj.isTouchDevice = true;
//     } else if ($('body').hasClass('not-touch')) {
//         edObj.isTouchDevice = false;
//     } else {
//         edObj.isTouchDevice = 'ontouchstart' in document.documentElement;
//     }
//
//     initEditableFields();
//     setupKeyHandler();
//     initializeConnection();
//     updateFieldContents(edObj);
//
//     console.log('Editable.js started for class ".'+edObj.invokingClass+'" ('+ ((edObj.isTouchDevice) ? 'touch-device' : 'non-touch-device') + ', backend: '+ edObj.backend+')');
// } // initializeEditableField




// //--------------------------------------------------------------
// function initializeConnection()
// {
//     var edObj = editableObj;
//     var res = false;
//     var json = '';
//
//     $('.' + edObj.invokingClass + ' input').addClass('lzy-wait');
//     this.ajaxSend(edObj, 'conn', '', json, function(json) {
//         console.log('Server connection established: ' + json);
//         if (json.match(/^</)) {
//             console.log('response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
//             return;
//         }
//         if (json.match(/failed/)) {
//             console.log('Session aborted');
//             $('main').html("<h1>Error connecting to server.</h1><p>Please try again with another browser or another computer.</p>");
//             res = false;
//         }
//         res = true;
//         $('.' + edObj.invokingClass + ' input').removeClass('lzy-wait');
//     });
//     return res;
// } // initializeConnection
//



