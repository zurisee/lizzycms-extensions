/*
 *  Editable.js
*/

var editableObj = new Object();
editableObj.backend = systemPath+'extensions/editable/backend/_editable_backend.php';
editableObj.dataRef = '';
editableObj.ignoreBlur = [];
editableObj.origValue = [];
editableObj.newValue =  [];
editableObj.editingTimeout = 60; // sec

editableObj.showAllButtons = false;
editableObj.showOkButton = false;
editableObj.showCancelButton = false;
editableObj.timeoutTimer = false;
editableObj.doubleClick = [];


(function ( $ ) {
    $('.lzy-editable').attr('tabindex', 0);

    initializeConnection();

    $('.lzy-editable').focus(function () {
        var id = $(this).attr('id');
        mylog('make editable on focus: ' + id);
        makeEditable( this );
    });

    $('.lzy-editable').dblclick(function (e) {
        var id = $(this).attr('id');
        mylog('double click: ' + id);
        editableObj.doubleClick[ id ] = true;
        // makeEditable( this );
    });

    editableObj.showAllButtons = $('body').hasClass('touch') ||
        $('html').hasClass('touchevents') ||
        $('.lzy-editable-show-buttons').length;

    if (editableObj.showAllButtons) {
        editableObj.showOkButton = true;
        editableObj.showCancelButton = true;
    }
}( jQuery ));


function initEditingHandlers($elem, edObj, id, origValue) {
    // then set input elem up:
    $('input, textarea', $elem)
        .click(function (e) {
            e.stopImmediatePropagation();
        })
        .each(function () {
            setupKeyHandlers(edObj, $elem);
        })
        .focus()
        .blur(function () {
//return;
            if (edObj.timeoutTimer) {
                clearTimeout(edObj.timeoutTimer);
                edObj.timeoutTimer = false;
            }

            // wait a moment to make sure key and button handlers fire first:
            setTimeout(function () {
                if (!edObj.ignoreBlur[id]) {
                    // if this point reached: no button or key became active.
                    edObj.ignoreBlur[id] = true;

                    if (!edObj.showOkButton) {           // no buttons -> save
                        terminateEditable(edObj, $elem, true);

                    } else if (edObj.showCancelButton) { // ok&cancel -> ignore
                        return;

                    } else {                            // only ok button -> cancel
                        edObj.newValue[id] = edObj.origValue[id];
                        terminateEditable(edObj, $elem, false);
                    }
                }
            }, 100);
        });

    // place cursor at end of string:
    $('input, textarea', $elem)[0].setSelectionRange(origValue.length, origValue.length);

    // set up timeout on editable field:
    resetEditableTimeout();
} // initEditingHandlers




function makeEditable( that )
{
    var edObj = editableObj;
    const $elem = $( that );

    // check for already active fields, close others:
    $('.lzy-editable-active').each(function () {
        if (this !== that) {
            if ($elem.closest('.lzy-editable-show-button,.lzy-editable-show-buttons').length) {
                terminateEditable(edObj, $(this), false);
            } else {
                terminateEditable(edObj, $(this), true);
            }
        }
    });
    // check for spurious re-entry, ignore:
    if ($elem.hasClass('lzy-editable-active')) {
        return;
    }
    // check whether field is locked, ignore:
    if ($elem.hasClass('lzy-element-locked') ||
            $elem.hasClass('lzy-element-frozen')) {
        mylog('element is locked or frozen -> ignored');
        $elem.blur();
        return;
    }

    var id = $elem.attr('id');
    if (typeof id === 'undefined') {
        const x = $elem[0].cellIndex + 1;
        const y = $elem[0].parentNode.rowIndex;
        // const y = $elem[0].parentNode.rowIndex + 1;
        id = 'lzy-elem-' + y + '-' + x;
        $elem.attr('id', id);
    }
    mylog('make editable: ' + id);

    edObj.dataRef = $elem.data('lzyDataRef');

    edObj.ignoreBlur[ id ] = false;
    var origValue = $elem.text();
    edObj.origValue[ id ] = origValue;
    edObj.newValue[ id ] = origValue;

    var _id = '_' + id;
    $elem.removeClass('lzy-editable').addClass('lzy-editable-active');

    // on touch devices: always show buttons
    if (!edObj.showAllButtons) {
        edObj.showOkButton = ( $elem.hasClass('lzy-editable-show-button') ||
            $elem.closest('.lzy-editable-wrapper').hasClass('lzy-editable-show-button'));
    }
    if (edObj.showOkButton) {
        var buttons = renderOkButton();
        if (edObj.showCancelButton) {
            buttons = buttons + renderCancelButton();
        }
        $elem.html(buttons+'<input id="'+_id+'" aria-live="polite" aria-relevant="all" value="' + origValue + '"/>');
        revealButtons(edObj, $elem);

        // set up button handlers:
        $('.lzy-editable-submit-button', $elem).click(function (e) {
            e.stopPropagation();
            mylog( 'ok button clicked: ' + edObj.newValue[ id ] );
            edObj.ignoreBlur[ id ] = true;
            terminateEditable(edObj, $elem, true);
        });

        if (edObj.showCancelButton) {
            $('.lzy-editable-cancel-button', $elem).click(function (e) {
                e.stopPropagation();
                mylog('cancel button clicked');
                edObj.newValue[ id ] = edObj.origValue[ id ];
                edObj.ignoreBlur[ id ] = true;
                terminateEditable(edObj, $elem, false);
            });
        }
    } else {
        if (isMultiLineElement( $elem )) {
            $elem.html('<textarea id="' + _id + '" class="lzy-editable-multiline" aria-live="polite" aria-relevant="all">' + edObj.origValue[id] + '</textarea>');
            // $elem.html('<textarea id="' + _id + '" aria-live="polite" aria-relevant="all">' + edObj.origValue[id] + '</textarea>');
        } else {
            $elem.html('<input id="' + _id + '" aria-live="polite" aria-relevant="all" value="' + edObj.origValue[id] + '"/>');
        }
    }

    // first: lock field:
    lockField(edObj, id);
    initEditingHandlers($elem, edObj, id, origValue);
} // makeEditable



//--------------------------------------------------------------
function lockField(edObj, id)
{
    mylog( 'lockField: '+id );
    var url = edObj.backend + '?lock=' + id + '&ds=' + getDataRef(id);
    var $inputField = $('#_' + id);

    $inputField.addClass('lzy-wait');

    $.ajax({
        url: url,
        type: 'get',
        cache: false,
        success: function(json) {
            $inputField.removeClass('lzy-wait');
            handleResponse(edObj, json, id, '#error-locked', function (edObj, data) {
            // handleResponse(edObj, json, '#error-locked', function (edObj, data) {
                edObj.ignoreBlur[ id ] = true;
                if (data.result.match(/^ok/)) {
                    terminateEditable(edObj, $('#' + data.id), false);
                } else {
                    mylog(data.result);
                }
            });
        },
    });
} // lockField



//--------------------------------------------------------------
function unlockField(edObj, id)
{
    mylog( 'unlockField: '+id );
    var url = edObj.backend + '?unlock=' + id + '&ds=' + getDataRef(id);
    $.ajax({
        url: url,
        type: 'get',
        cache: false,
        success: function (json) {
            handleResponse(edObj, json, id, false);
            // handleResponse(edObj, json, false);
        }
    });
    return true;
} // unlockField





//--------------------------------------------------------------
function saveAndUnlockField(edObj, id)
{
    var $elem = $('#' + id);
    if (typeof edObj.newValue[ id ] === 'undefined') {
        return;
    }
    if (edObj.origValue[ id ] === edObj.newValue[ id ]) {
        mylog('unchanged, not saving');
        edObj.ignoreBlur[ id ] = true;
        if (edObj.showOkButton) {
            hideButtons($elem);
            setTimeout(function(){ // wait for buttons to disappear
                $elem.text(edObj.origValue[ id ]).removeClass('lzy-editable-active').addClass('lzy-editable');
                unlockField(edObj, id);
            }, 150);
        } else {
            $elem.text(edObj.origValue[ id ]).removeClass('lzy-editable-active').addClass('lzy-editable');
            unlockField(edObj, id);
        }
        return;
    }
    mylog( 'save: '+id + ' => ' + edObj.newValue[ id ] );
    var url = edObj.backend + '?save=' + id + '&ds=' + getDataRef(id);
    const text = encodeURI( edObj.newValue[ id ] );   // -> php urldecode() to decode

    $.ajax({
        url: url,
        type: 'post',
        data: { text: text },
        cache: false,
        success: function (json) {
            handleResponse(edObj, json, id, '#db-error');
            // handleResponse(edObj, json, '#db-error');
        }
    });
    return true;
} // saveAndUnlockField




function terminateEditable(edObj, $elem, save)
{
    // clear the editable field timeout:
    if (edObj.timeoutTimer) {
        clearTimeout(edObj.timeoutTimer);
        edObj.timeoutTimer = false;
    }

    // save data resp. just terminate editing mode:
    const id = $elem.attr('id');
    if ((typeof save !== 'undefined') && save) {
        saveAndUnlockField(edObj, id);
    } else {
        unlockField(edObj, id);
    }

    if (edObj.showOkButton) {
        hideButtons($elem);
        setTimeout(function(){ // wait for buttons to disappear
            $elem.text( edObj.newValue[ id ] ).removeClass('lzy-editable-active').addClass('lzy-editable');
            edObj.ignoreBlur[ id ] = true;
            $('input, textarea', $elem).blur();
            $elem.blur();
        }, 150);
    } else {
        $elem.text( edObj.newValue[ id ] ).removeClass('lzy-editable-active').addClass('lzy-editable');
        edObj.ignoreBlur[ id ] = true;
        $('input, textarea', $elem).blur();
        $elem.blur();
    }
} // terminateEditable



//--------------------------------------------------------------
function renderOkButton()
{
    var okSymbol = '&#10003;';
    if ($('body').hasClass('legacy')) {
        okSymbol = '&radic;';
    }
    var button = "<button class='lzy-editable-submit-button lzy-button'>" + okSymbol + "<span class='invisible'> Save</span></button>";
    return button;
} // getButtons



//--------------------------------------------------------------
function renderCancelButton()
{
    const cancelSymbol = '&times;';
    var button = "<button class='lzy-editable-cancel-button lzy-button'>" + cancelSymbol + "<span class='invisible'> Cancel</span></button>";
    return button;
} // getButtons



function revealButtons(edObj, $elem)
{
    const $inp = $('input, textarea', $elem);
    const $btn = $('button', $elem);
    var bw = $btn.width();
    var w = $elem.width();
    if (edObj.showCancelButton) {
        w = w - 2*bw - 6;
    } else {
        w = w - bw - 4;
    }
    $inp.animate({width: w + 'px'}, 150);
} // revealButtons



//--------------------------------------------------------------
function hideButtons($elem)
{
    const $inp = $('input, textarea', $elem);
    $inp.animate({width: '100%'}, 150);
} // hideButtons



//--------------------------------------------------------------
function getDataRef(id) {
    var dataRef = '';
    if (typeof id !== 'undefined') {
        dataRef = $('#' + id).attr('data-lzy-data-ref');
    } else {
        dataRef = $('.lzy-editable-wrapper').attr('data-lzy-data-ref');
    }
    if ((typeof dataRef === 'undefined') && (typeof id !== 'undefined')) {
        dataRef = $('#' + id).closest('[data-lzy-data-ref]').attr('data-lzy-data-ref');
    }
    if (typeof dataRef === 'undefined') {
        dataRef = $('.lzy-data-ref').attr('data-lzy-data-ref');
    }
    // if (typeof dataRef === 'undefined') {
    //     dataRef = $('#' + id).closest('[data-lzy-data-ref]').attr('data-lzy-data-ref');
    // }
    return dataRef;
} // getDataRef



function handleResponse(edObj, json, id, msgId, errorHandler)
// function handleResponse(edObj, json, msgId, errorHandler)
{
    json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
    if (!json) {
        return;
    }

    if (typeof edObj.doubleClick[id] !== 'undefined') {
        edObj.doubleClick[id] = false;
        const $elem = $( '#' + id );
        $elem.html('<textarea id="_' + id + '" class="lzy-editable-multiline" aria-live="polite" aria-relevant="all">' + edObj.origValue[id] + '</textarea>');
        initEditingHandlers($elem, edObj, id, edObj.origValue[id]);
    }

    var data = JSON.parse(json);
    var result = data.result;
    if (result.match(/^ok/)) {
        updateUi(edObj, data);
    } else {
        if (msgId) {
            if (msgId === true) {
                msgId = data.result;
            }
            lzyPopup({
                contentRef: msgId,
//                contentFrom: msgId,
            });
        }
        if (typeof errorHandler === 'function') {
            errorHandler(edObj, data);
        }
    }
} // handleResponse




function updateUi(edObj, data)
{
    var txt = '';
    var data1 = data.data;
    if (typeof data1 === 'object') {
        for (var tSel in data1) {
            txt = data1[ tSel ];
            const c1 = tSel.substr(0,1);
            if ((c1 !== '#') && (c1 !== '.')) {
                tSel = '#' + tSel;
            }
            if (!$( tSel ).hasClass('lzy-editable-active')) {
                $(tSel).text(txt);
            }
        }
    }
} // updateUi




//--------------------------------------------------------------
function initializeConnection()
{
    var edObj = editableObj;
    var url = edObj.backend + '?conn&ds=' + getDataRef();
    $.ajax({
        url: url,
        type: 'get',
        cache: false,
        success: function (json) {
            handleResponse(edObj, json,  null,'#conn-error');
            // handleResponse(edObj, json, '#conn-error');
        }
    });
} // initializeConnection



function resetEditableTimeout()
{
    if (editableObj.timeoutTimer) {
        clearTimeout(editableObj.timeoutTimer);
        editableObj.timeoutTimer = false;
    }
    editableObj.timeoutTimer = setTimeout(function () {
        onEditableTimedOut();
    }, editableObj.editingTimeout * 1000);
} // resetEditableTimeout



function onEditableTimedOut()
{
    mylog('editable mode timed out');
    $elem = $('.lzy-editable-active');
    terminateEditable(editableObj, $elem, false);
} // onEditableTimedOut




function isMultiLineElement( $elem )
{
    const m1 = $('.lzy-editable-multiline', $elem).length;
    const m2 = $elem.closest('.lzy-editable-multiline').length;
mylog('isMultiLineElement: ' + m1 + '/' + m2);
    return (m1 || m2);
    // return $elem.closest('.lzy-editable-multiline').length;
} // isMultiLineElement




//--------------------------------------------------------------
function setupKeyHandlers( edObj, $elem )
{
    // Enter: save and close
    // ESC:     close and restore initial value
    // Tab:  save, close and jump to next tabstop (incl. next editable)

    resetEditableTimeout();

    var $input = $('input, textarea', $elem);
    $input.keydown(function (e) {
        var key = e.which;
        if (key === 9) {                  // Tab
            onKeyTab(e, edObj, $elem);
        }
    });

    $input.keyup(function (e) {
        var key = e.which;
        e.preventDefault();
        if ((key === 13) && !isMultiLineElement( $elem )) { // Enter
            onKeyEnter(e, edObj, $elem);

        } else if(key === 27) {                // ESC
            onKeyEsc(e, edObj, $elem);

        } else {
            const id = $elem.attr('id');
            edObj.newValue[ id ] = $('input, textarea', $elem).val();
        }
    });
} // setupKeyHandler



function onKeyEnter( e, edObj, $elem )
{
    e.stopImmediatePropagation();
    const id = $elem.attr('id');
    edObj.ignoreBlur[ id ] = true;
    terminateEditable(edObj, $elem, true);
} // onKeyEnter



function onKeyTab( e, edObj, $elem )
{
    e.stopImmediatePropagation();
    const id = $elem.attr('id');
    edObj.ignoreBlur[ id ] = true;
    terminateEditable(edObj, $elem, true);
} // onKeyTab



function onKeyEsc( e, edObj, $elem )
{
    e.stopImmediatePropagation();
    const id = $elem.attr('id');
    edObj.newValue[ id ] = edObj.origValue[ id ];
    edObj.ignoreBlur[ id ] = true;
    terminateEditable(edObj, $elem, false);
} // onKeyEsc


