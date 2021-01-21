/*
 *  Editable.js
*/

"use strict";

var Editable = new Object({
    backend: systemPath+'extensions/editable/backend/_editable_backend.php',
    dataRef: '',
    ignoreBlur: [],
    origValue: [],
    newValue:  [],
    editingTimeout: 60, // sec

    showAllButtons: false,
    showOkButton: false,
    showCancelButton: false,
    timeoutTimer: false,
    doubleClick: [],

    init: function() {
        // determine global state:
        this.showAllButtons = $('body').hasClass('touch') ||
            $('html').hasClass('touchevents') ||
            $('.lzy-editable-show-buttons').length;

        if (this.showAllButtons) {
            this.showOkButton = true;
            this.showCancelButton = true;
        }
        var $edElem = $('.lzy-editable');

        // make all editable elements reachable by kbd:
        $edElem.attr('tabindex', 0);

        this.initEditingHandlers( $edElem );
    }, // init




    initEditingHandlers: function( $edElem ) {
        var rootObj = this;

        $edElem
            .focus(function () {
                const on = $(this).hasClass('lzy-editable');
                if (!on) {
                    return;
                }
                var id = $(this).attr('id');
                // rootObj.doubleClick[ id ] = macKeys.cmdKey;
                rootObj.makeEditable( this );
            })
            .on('click', 'input, textarea', function (e) {
                e.stopImmediatePropagation();
            })
            .on('blur', 'input, textarea', function () {
                var $this = $(this).parent();
                var id = $this.attr('id');
                if (rootObj.timeoutTimer) {
                    clearTimeout(rootObj.timeoutTimer);
                    rootObj.timeoutTimer = false;
                }

                // wait a moment to make sure key and button handlers fire first:
                setTimeout(function () {
                    if (!rootObj.ignoreBlur[id]) {
                        // if this point reached: no button or key became active.
                        rootObj.ignoreBlur[id] = true;

                        if (!rootObj.showOkButton) {           // no buttons -> save
                            rootObj.terminateEditable($this, true);

                        } else if (rootObj.showCancelButton) { // ok&cancel -> ignore
                            return;

                        } else {                            // only ok button -> cancel
                            rootObj.terminateEditable($this, false);
                        }
                    }
                }, 100);
            })
            .on('click', '.lzy-editable-submit-button', function (e) {
                e.stopPropagation();
                const $el = $(this).parent();
                const id = $el.attr('id');
                rootObj.newValue[ id ] = $('input, textarea', $el).val();
                rootObj.ignoreBlur[ id ] = true;
                rootObj.terminateEditable($el, true);
            });

        if (this.showCancelButton) {
            $edElem.on('click', '.lzy-editable-cancel-button', function (e) {
                e.stopPropagation();
                const $el = $(this).parent();
                const id = $el.attr('id');
                rootObj.newValue[ id ] = rootObj.origValue[ id ];
                rootObj.ignoreBlur[ id ] = true;
                rootObj.terminateEditable($el, false);
            });
        }

        this.setupKeyHandlers();
        }, // initEditingHandlers



    makeEditable: function( that ) {
        var rootObj = this;
        const $elem = $( that );

        if ($elem.closest('.lzy-editable-inactive').length) {
            return;
        }

        // check for already active fields, close others:
        $('.lzy-editable-active').each(function () {
            if (rootObj !== that) {
                if ($elem.closest('.lzy-editable-show-button,.lzy-editable-show-buttons').length) {
                    rootObj.terminateEditable( $(this), false );
                } else {
                    rootObj.terminateEditable( $(this), true );
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

        // make sure field has fixed width (not only min/max-width):
        $elem.css('width', $elem.outerWidth() + 'px');

        var id = $elem.attr('id');

        // make sure element has an id, apply one if not:
        if (typeof id === 'undefined') {
            const x = $elem[0].cellIndex;
            const y = parseInt( $elem.closest('tr').attr('class').replace(/\D/g, '') ) - 1;
            id = 'lzy-elem-' + y + '-' + x;
            $elem.attr('id', id);
        }
        $elem.addClass('lzy-wait');
        mylog('make editable: ' + id);

        // lock field:
        this.lockField(id, function(id, json) {
            var _id = '_' + id;
            var $elem = $('#' + id);
            var origValue = $elem.text();
            rootObj.ignoreBlur[ id ] = false;
            rootObj.origValue[ id ] = origValue;
            $elem.removeClass('lzy-wait');

            // evaluate lock request's response:
            json = json.replace(/(.*[\]}])#.*/, '$1');    // remove trailing #comment
            if (json) {
                var data = JSON.parse(json);
                if (data.result.match(/^failed#lockFrozen/)) {
                    $elem.addClass('lzy-element-frozen');
                    lzyPopup({
                        contentFrom: '#lzy-info-frozen',
                    });
                    return;
                } else if (data.result.match(/^ok/)) {
                    rootObj.updateUi(data);
                } else {
                    mylog(data.result);
                    $elem.addClass('lzy-element-locked');
                    lzyPopup({
                        contentFrom: '#lzy-error-locked',
                    });
                    return;
                }
            }

            // if it was a double click: treat as a multiline textarea:
            if (lzyEnableEditableDoubleClick) {
                if ((typeof rootObj.doubleClick[id] !== 'undefined') && rootObj.doubleClick[id]) {
                    rootObj.doubleClick[ id ] = true;
                }
            }

            $elem.removeClass('lzy-editable').addClass('lzy-editable-active');

            // on touch devices: always show buttons
            if (!rootObj.showAllButtons) {
                rootObj.showOkButton = ( $elem.hasClass('lzy-editable-show-button') ||
                    $elem.closest('.lzy-editable-wrapper').hasClass('lzy-editable-show-button'));
            }
            var buttons = '';
            if (rootObj.showOkButton) {
                buttons = rootObj.renderOkButton();
                if (rootObj.showCancelButton) {
                    buttons = buttons + rootObj.renderCancelButton();
                }
            }

            // now inject input/textarea element and buttons:
            var $innerEl = null;
            if (rootObj.isMultiLineElement( $elem )) {
                $elem.html(buttons+'<textarea id="' + _id + '" aria-live="polite" aria-relevant="all">' + rootObj.origValue[id] + '</textarea>');
                $elem.addClass('lzy-editable-multiline');
                $innerEl = $('textarea', $elem);
            } else {
                $elem.html(buttons+'<input id="' + _id + '" aria-live="polite" aria-relevant="all" value="' + rootObj.origValue[id] + '"/>');
                $innerEl = $('input', $elem);
            }

            // set up timeout on editable field:
            rootObj.resetEditableTimeout();

            rootObj.revealButtons($elem);
            rootObj.putFocus( $innerEl );
        });
    }, // makeEditable




    setupKeyHandlers: function() {
        // Enter: save and close
        // ESC:     close and restore initial value
        // Tab:  save, close and jump to next tabstop (incl. next editable)

        var rootObj = this;
        var $elem = $('.lzy-editable');

        $elem.on('keydown', 'input, textarea', function(e) {
            const key = e.which;
            const meta = macKeys.cmdKey;
            var $el = $(this).parent();
            if (key === 9) {                  // Tab
                rootObj.onKeyTab(e, $el);
            }
        });

        $elem.on('keyup', 'input', function(e) {
            const key = e.which;
            const meta = macKeys.cmdKey;
            var $el = $(this).parent();
            e.preventDefault();
            if ((key === 13) || // Enter
                ((key === 13) && event.ctrlKey) ||      // Win: ctrl-enter
                ((key === 13) && macKeys.cmdKey)) {     // Mac: cmd-enter
                rootObj.onKeyEnter(e, $el);

            } else if(key === 27) {                // ESC
                rootObj.onKeyEsc(e, $el);

            } else {
                const id = $el.attr('id');
                rootObj.newValue[ id ] = $('input, textarea', $el).val();
            }
        });
        $elem.on('keyup', 'textarea', function(e) {
            const key = e.which;
            const meta = macKeys.cmdKey;
            var $el = $(this).parent();
            e.preventDefault();
            if (((key === 13) && event.ctrlKey) ||      // Win: ctrl-enter
                ((key === 13) && macKeys.cmdKey)) {     // Mac: cmd-enter
                rootObj.onKeyEnter(e, $el);

            } else if(key === 27) {                // ESC
                rootObj.onKeyEsc(e, $el);

            } else {
                const id = $el.attr('id');
                rootObj.newValue[ id ] = $('input, textarea', $el).val();
            }
        });

    }, // setupKeyHandler



    onKeyEnter: function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = $('input, textarea', '#' + id).val();
        this.ignoreBlur[ id ] = true;
        this.terminateEditable($elem, true);
    }, // onKeyEnter



    onKeyTab: function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = $('input, textarea', '#' + id).val();
        this.ignoreBlur[ id ] = true;
        this.terminateEditable( $elem,true);
    }, // onKeyTab



    onKeyEsc: function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = this.origValue[ id ];
        this.ignoreBlur[ id ] = true;
        this.terminateEditable($elem, false);
    }, // onKeyEsc



    lockField: function(id, callback) {
        mylog( 'lockField: '+id );
        const url = this.backend;
        var $inputField = $('#_' + id);
        $inputField.addClass('lzy-wait');
        var data = {
            cmd: 'lock',
            id: id,
            srcRef: this.getDatasrcRef(id),
        };
        var dSel = $( '#' + id ).attr('data-ref');
        if (typeof dSel !== 'undefined') {
            data.elemRef = dSel;
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            cache: false,
            success: function (json) {
                callback(id, json);
            },
            error: function(xhr, status, error) {
                mylog( 'lockField: failed -> ' + error );
                $('#' + id).removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
    }, // lockField




    unlockField: function(id) {
        mylog( 'unlockField: '+id );
        var rootObj = this;
        const url = this.backend;
        var data = {
            cmd: 'unlock',
            id: id,
            srcRef: this.getDatasrcRef(id),
        };
        var dSel = $( '#' + id ).attr('data-ref');
        if (typeof dSel !== 'undefined') {
            data.elemRef = dSel;
        }
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            cache: false,
            success: function (json) {
                rootObj.handleResponse(json, id, false);
            },
            error: function(xhr, status, error) {
                mylog( 'unlockField: failed -> ' + error );
                $('#' + id).removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }, // unlockField






    saveAndUnlockField: function(id) {
        var rootObj = this;
        var $elem = $('#' + id);
        var newValue = rootObj.newValue[ id ];
        if (typeof newValue === 'undefined') {
            newValue = $('input', $elem).val();
        }
        if (this.origValue[ id ] === newValue) {
            mylog('unchanged, not saving');
            this.ignoreBlur[ id ] = true;
            if (this.showOkButton) {
                this.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    $elem.text(rootObj.origValue[ id ]).removeClass('lzy-editable-active').addClass('lzy-editable');
                    rootObj.unlockField(id);
                }, 150);
            } else {
                $elem.text(this.origValue[ id ]).removeClass('lzy-editable-active').addClass('lzy-editable');
                this.unlockField(id);
            }
            return;
        }
        mylog( 'save: '+id + ' => ' + newValue );
        const text = encodeURI( newValue );   // -> php urldecode() to decode
        const url = this.backend;
        var data = {
            cmd: 'save',
            id: id,
            text: text,
            srcRef: this.getDatasrcRef(id),
        };
        var dSel = $elem.attr('data-ref');
        const recKey = $elem.parent().attr('data-reckey');
        if (typeof dSel !== 'undefined') {
            if (typeof recKey !== 'undefined') {
                dSel = dSel.replace(/^(\d+)/, recKey);
            }
            data.elemRef = dSel;
        }
        if (typeof recKey !== false) {
            data.recKey = recKey;
        }

        $.ajax({
            url: url,
            type: 'post',
            data: data,
            cache: false,
            success: function (json) {
                rootObj.handleResponse(json, id, '#lzy-db-error');
            },
            error: function(xhr, status, error) {
                mylog( 'save: failed -> ' + error );
                $('#' + id).removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }, // saveAndUnlockField




    terminateEditable: function($this, save) {
        var rootObj = this;
        var $elems, $elem = null;
        var id = null;

        if ((typeof $this !== 'undefined') && ($this.length)) {
            $elems = $this.closest('.lzy-editable-active');
            save = (typeof save !== 'undefined')? save: false;
        } else {
            $elems = $('.lzy-editable-active');
            save = false;
        }

        // clear the editable field timeout:
        if (rootObj.timeoutTimer) {
            clearTimeout(rootObj.timeoutTimer);
            rootObj.timeoutTimer = false;
        }

        $elems.each(function () {
            $elem = $(this);

            // save data resp. just terminate editing mode:
            id = $elem.attr('id');
            if ( id ) {
                if (save) {
                    rootObj.saveAndUnlockField(id);
                } else {
                    rootObj.unlockField(id);
                    rootObj.newValue[id] = rootObj.origValue[id];
                }
            }

            if (rootObj.showOkButton) {
                rootObj.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    if ( id ) {
                        $elem.text( rootObj.newValue[id] );
                        $elem.removeClass('lzy-editable-active').addClass('lzy-editable');
                        rootObj.ignoreBlur[id] = true;
                    }
                    $('input, textarea', $elem).blur();
                }, 150);
            } else {
                if ( id ) {
                    $elem.text(rootObj.newValue[id]).removeClass('lzy-editable-active').addClass('lzy-editable');
                    rootObj.ignoreBlur[id] = true;
                }
                $('input, textarea', $elem).blur();
            }
        });
    }, // terminateEditable




    renderOkButton: function() {
        return $('#lzy-editable-ok-text').html();
    }, // renderOkButton



    renderCancelButton: function() {
        return $('#lzy-editable-cancel-text').html();
    }, // renderCancelButton



    revealButtons: function($elem) {
        const $inp = $('input, textarea', $elem);
        const $btn = $('.lzy-editable-submit-button', $elem);
        if ($btn.length) {
            const w = $btn[0].offsetLeft - 2;
            $inp.animate({width: w + 'px'}, 150);
        }
    }, // revealButtons




    hideButtons: function($elem) {
        const $inp = $('input, textarea', $elem);
        $inp.animate({width: '100%'}, 150);
    }, // hideButtons




    getDatasrcRef: function(id) {
        var dataRef = '';
        if (typeof id !== 'undefined') {
            dataRef = $('#' + id).attr('data-lzy-datasrc-ref');
        } else {
            dataRef = $('.lzy-editable-wrapper').attr('data-lzy-datasrc-ref');
        }
        if ((typeof dataRef === 'undefined') && (typeof id !== 'undefined')) {
            dataRef = $('#' + id).closest('[data-lzy-datasrc-ref]').attr('data-lzy-datasrc-ref');
        }
        if (typeof dataRef === 'undefined') {
            dataRef = $('.lzy-data-ref').attr('data-lzy-datasrc-ref');
        }
        return dataRef;
    }, // getDatasrcRef



    handleResponse: function(json0, id, msgId, errorHandler) {
        const json = json0.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        if (!json) {
            return;
        }

        const data = JSON.parse(json);
        const result = data.result;
        if (result.match(/^ok/)) {
            this.updateUi(data);
        } else {
            if (msgId) {
                if (msgId === true) {
                    msgId = data.result;
                }
                lzyPopup({
                    contentRef: msgId,
                });
            }
            if (typeof errorHandler === 'function') {
                errorHandler(data);
            }
        }
    }, // handleResponse




    updateUi: function(data) {
        var txt = '';
        var x, y, c1, inx, cls, $tmp, $dataTable = null;
        var isDataTable = null;
        const data1 = data.data;
        if (typeof data1 === 'object') {
            for (var tSel in data1) {
                txt = data1[ tSel ];
                c1 = tSel.substr(0,1);
                $( tSel ).each(function() {
                    if (!$(this).hasClass('lzy-editable-active')) {
                        $(tSel).text(txt);
                    }
                });
            }
            if (isDataTable) {
                lzyTable[ inx ].draw();
            }
        }
    }, // updateUi





    resetEditableTimeout: function() {
        var rootObj = this;
        if (this.timeoutTimer) {
            clearTimeout(this.timeoutTimer);
            this.timeoutTimer = false;
        }
        this.timeoutTimer = setTimeout(function () {
            rootObj.onEditableTimedOut();
        }, this.editingTimeout * 1000);
    }, // resetEditableTimeout



    onEditableTimedOut: function() {
        mylog('editable mode timed out');
        var $elem = $('.lzy-editable-active');
        this.terminateEditable($elem, false);
    }, // onEditableTimedOut




    putFocus: function( $elem ) {
        setTimeout(function () {
            var fldLength= $elem.val().length;
            $elem.focus();
            $elem[0].setSelectionRange(fldLength, fldLength);
        }, 150);
    }, // putFocus



    isMultiLineElement: function( $elem ) {
        const m1 = $('.lzy-editable-multiline', $elem).length;
        const m2 = $elem.closest('.lzy-editable-multiline').length;
        const id = $elem.attr('id');
        var m3 = this.doubleClick[ id ];
        m3 = (typeof m3 !== 'undefined') ? lzyEnableEditableDoubleClick && m3 : false;
        return (m1 || m2 || m3);
    }, // isMultiLineElement

}); // Object
