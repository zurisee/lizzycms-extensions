/*
 *  Editable.js
*/

"use strict";

var editableInx = 0;
var editables = [];



function Editable( options ) {
    this.options = options;
    this.backend = systemPath+'extensions/editable/backend/_editable_backend.php';
    this.dataRef = '';
    this.ignoreBlur = [];
    this.origValue = [];
    this.newValue = [];
    this.editingTimeout = 60; // sec

    this.showAllButtons = false;
    this.showOkButton = false;
    this.showCancelButton = false;
    this.timeoutTimer = false;
    this.doubleClick = [];


    this.init = function() {
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
    }; // init



    this.initEditingHandlers = function( $edElem ) {
        var parent = this;

        $edElem
            .focus(function () {
                const on = $(this).hasClass('lzy-editable');
                if (!on) {
                    return;
                }
                var id = $(this).attr('id');
                parent.makeEditable( this );
            })
            .on('click', 'input, textarea', function (e) {
                e.stopImmediatePropagation();
            })
            .on('blur', 'input, textarea', function () {
                var $this = $(this).parent();
                var id = $this.attr('id');
                if (parent.timeoutTimer) {
                    clearTimeout(parent.timeoutTimer);
                    parent.timeoutTimer = false;
                }

                // wait a moment to make sure key and button handlers fire first:
                setTimeout(function () {
                    if (!parent.ignoreBlur[id]) {
                        // if this point reached: no button or key became active.
                        parent.ignoreBlur[id] = true;

                        if (!parent.showOkButton) {           // no buttons -> save
                            parent.terminateEditable($this, true);

                        } else if (parent.showCancelButton) { // ok&cancel -> ignore
                            return;

                        } else {                            // only ok button -> cancel
                            parent.terminateEditable($this, false);
                        }
                    }
                }, 100);
            })
            .on('click', '.lzy-editable-submit-button', function (e) {
                e.stopPropagation();
                const $el = $(this).parent();
                const id = $el.attr('id');
                parent.newValue[ id ] = $('input, textarea', $el).val();
                parent.ignoreBlur[ id ] = true;
                parent.terminateEditable($el, true);
            });

        if (this.showCancelButton) {
            $edElem.on('click', '.lzy-editable-cancel-button', function (e) {
                e.stopPropagation();
                const $el = $(this).parent();
                const id = $el.attr('id');
                parent.newValue[ id ] = parent.origValue[ id ];
                parent.ignoreBlur[ id ] = true;
                parent.terminateEditable($el, false);
            });
        }

        this.setupKeyHandlers();
    }; // initEditingHandlers



    this.makeEditable = function( that ) {
        var parent = this;
        const $elem = $( that );

        if ($elem.closest('.lzy-editable-inactive').length) {
            return;
        }

        // check for already active fields, close others:
        $('.lzy-editable-active').each(function () {
            if (parent !== that) {
                if ($elem.closest('.lzy-editable-show-button,.lzy-editable-show-buttons').length) {
                    parent.terminateEditable( $(this), false );
                } else {
                    parent.terminateEditable( $(this), true );
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
            parent.ignoreBlur[ id ] = false;
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
                    parent.updateUi(data);
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
                if ((typeof parent.doubleClick[id] !== 'undefined') && parent.doubleClick[id]) {
                    parent.doubleClick[ id ] = true;
                }
            }

            $elem.removeClass('lzy-editable').addClass('lzy-editable-active');

            // on touch devices: always show buttons
            if (!parent.showAllButtons) {
                parent.showOkButton = ( $elem.hasClass('lzy-editable-show-button') ||
                    $elem.closest('.lzy-editable-wrapper').hasClass('lzy-editable-show-button'));
            }
            var buttons = '';
            if (parent.showOkButton) {
                buttons = parent.renderOkButton();
                if (parent.showCancelButton) {
                    buttons = buttons + parent.renderCancelButton();
                }
            }

            // now inject input/textarea element and buttons:
            var $innerEl = null;
            var val = $elem.text();
            parent.origValue[id] = val;
            if (parent.isMultiLineElement( $elem )) {
                $elem.html(buttons+'<textarea id="' + _id + '" aria-live="polite" aria-relevant="all">' + val + '</textarea>');
                $elem.addClass('lzy-editable-multiline');
                $innerEl = $('textarea', $elem);
            } else {
                $elem.html(buttons+'<input id="' + _id + '" aria-live="polite" aria-relevant="all" value="' + val + '"/>');
                $innerEl = $('input', $elem);
            }

            // set up timeout on editable field:
            parent.resetEditableTimeout();

            parent.revealButtons($elem);
            parent.putFocus( $innerEl );
        });
    }; // makeEditable



    this.setupKeyHandlers = function() {
        // Enter: save and close
        // ESC:     close and restore initial value
        // Tab:  save, close and jump to next tabstop (incl. next editable)

        var parent = this;
        var $elem = $('.lzy-editable');

        $elem.on('keydown', 'input, textarea', function(e) {
            const key = e.which;
            const meta = macKeys.cmdKey;
            var $el = $(this).parent();
            if (key === 9) {                  // Tab
                parent.onKeyTab(e, $el);
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
                parent.onKeyEnter(e, $el);

            } else if(key === 27) {                // ESC
                parent.onKeyEsc(e, $el);

            } else {
                const id = $el.attr('id');
                parent.newValue[ id ] = $('input, textarea', $el).val();
            }
        });
        $elem.on('keyup', 'textarea', function(e) {
            const key = e.which;
            const meta = macKeys.cmdKey;
            var $el = $(this).parent();
            e.preventDefault();
            if (((key === 13) && event.ctrlKey) ||      // Win: ctrl-enter
                ((key === 13) && macKeys.cmdKey)) {     // Mac: cmd-enter
                parent.onKeyEnter(e, $el);

            } else if(key === 27) {                // ESC
                parent.onKeyEsc(e, $el);

            } else {
                const id = $el.attr('id');
                parent.newValue[ id ] = $('input, textarea', $el).val();
            }
        });

    }; // setupKeyHandler



    this.onKeyEnter = function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = $('input, textarea', '#' + id).val();
        this.ignoreBlur[ id ] = true;
        this.terminateEditable($elem, true);
    }; // onKeyEnter



    this.onKeyTab = function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = $('input, textarea', '#' + id).val();
        this.ignoreBlur[ id ] = true;
        this.terminateEditable( $elem,true);
    }; // onKeyTab



    this.onKeyEsc = function( e, $elem ) {
        e.stopImmediatePropagation();
        const id = $elem.attr('id');
        this.newValue[ id ] = this.origValue[ id ];
        this.ignoreBlur[ id ] = true;
        this.terminateEditable($elem, false);
    }; // onKeyEsc



    this.lockField = function(id, callback) {
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
    }; // lockField



    this.unlockField = function(id) {
        mylog( 'unlockField: '+id );
        var parent = this;
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
                parent.handleResponse(json, id, false);
            },
            error: function(xhr, status, error) {
                mylog( 'unlockField: failed -> ' + error );
                $('#' + id).removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }; // unlockField



    this.saveAndUnlockField = function(id) {
        var parent = this;
        var $elem = $('#' + id);
        var newValue = parent.newValue[ id ];
        if (typeof newValue === 'undefined') {
            newValue = $('input', $elem).val();
        }
        if (this.origValue[ id ] === newValue) {
            mylog('unchanged, not saving');
            this.ignoreBlur[ id ] = true;
            if (this.showOkButton) {
                this.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    $elem.text(parent.origValue[ id ]).removeClass('lzy-editable-active').addClass('lzy-editable');
                    parent.unlockField(id);
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
                parent.handleResponse(json, id, '#lzy-db-error');
            },
            error: function(xhr, status, error) {
                mylog( 'save: failed -> ' + error );
                $('#' + id).removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }; // saveAndUnlockField



    this.terminateEditable = function($this, save) {
        var parent = this;
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
        if (parent.timeoutTimer) {
            clearTimeout(parent.timeoutTimer);
            parent.timeoutTimer = false;
        }

        $elems.each(function () {
            $elem = $(this);

            // save data resp. just terminate editing mode:
            id = $elem.attr('id');
            if ( id ) {
                if (save) {
                    parent.saveAndUnlockField(id);
                } else {
                    parent.unlockField(id);
                    parent.newValue[id] = parent.origValue[id];
                }
            }

            if (parent.showOkButton) {
                parent.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    if ( id ) {
                        $elem.text( parent.newValue[id] );
                        $elem.removeClass('lzy-editable-active').addClass('lzy-editable');
                        parent.ignoreBlur[id] = true;
                    }
                    $('input, textarea', $elem).blur();
                }, 150);
            } else {
                if ( id ) {
                    $elem.text(parent.newValue[id]).removeClass('lzy-editable-active').addClass('lzy-editable');
                    parent.ignoreBlur[id] = true;
                }
                $('input, textarea', $elem).blur();
            }
        });
    }; // terminateEditable



    this.renderOkButton = function() {
        return $('#lzy-editable-ok-text').html();
    }; // renderOkButton



    this.renderCancelButton = function() {
        return $('#lzy-editable-cancel-text').html();
    }; // renderCancelButton



    this.revealButtons = function($elem) {
        const $inp = $('input, textarea', $elem);
        const $btn = $('.lzy-editable-submit-button', $elem);
        if ($btn.length) {
            const w = $btn[0].offsetLeft - 2;
            $inp.animate({width: w + 'px'}, 100);
        }
    }; // revealButtons



    this.hideButtons = function($elem) {
        const $inp = $('input, textarea', $elem);
        $inp.animate({width: '100%'}, 100);
    }; // hideButtons



    this.getDatasrcRef = function(id) {
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
    }; // getDatasrcRef



    this.handleResponse = function(json0, id, msgId, errorHandler) {
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
    }; // handleResponse



    this.updateUi = function(data) {
        var txt = '';
        var x, y, c1, inx, cls, $tmp, $dataTable = null;
        var isDataTable = null;
        var id;
        const parent = this;
        const data1 = data.data;
        if (typeof data1 === 'object') {
            for (var tSel in data1) {
                txt = data1[ tSel ];
                c1 = tSel.substr(0,1);
                $( tSel ).each(function() {
                    if (!$(this).hasClass('lzy-editable-active')) {
                        $( this ).text( txt );
                        //mylog( tSel + ' <= ' + txt);
                        id = tSel.substr(1);
                        if (typeof parent.origValue !== 'undefined') {
                            parent.origValue[id] = txt;
                        }
                    }
                });
            }
            if (isDataTable) {
                lzyTable[ inx ].draw();
            }
        }
    }; // updateUi



    this.resetEditableTimeout = function() {
        var parent = this;
        if (this.timeoutTimer) {
            clearTimeout(this.timeoutTimer);
            this.timeoutTimer = false;
        }
        this.timeoutTimer = setTimeout(function () {
            parent.onEditableTimedOut();
        }, this.editingTimeout * 1000);
    }; // resetEditableTimeout



    this.onEditableTimedOut = function() {
        mylog('editable mode timed out');
        var $elem = $('.lzy-editable-active');
        this.terminateEditable($elem, false);
    }; // onEditableTimedOut



    this.putFocus = function( $elem ) {
        setTimeout(function () {
            var fldLength= $elem.val().length;
            $elem.focus();
            $elem[0].setSelectionRange(fldLength, fldLength);
        }, 150);
    }; // putFocus



    this.isMultiLineElement = function( $elem ) {
        const m1 = $('.lzy-editable-multiline', $elem).length;
        const m2 = $elem.closest('.lzy-editable-multiline').length;
        const id = $elem.attr('id');
        var m3 = this.doubleClick[ id ];
        m3 = (typeof m3 !== 'undefined') ? lzyEnableEditableDoubleClick && m3 : false;
        return (m1 || m2 || m3);
    }; // isMultiLineElement

} // Editable

