/*
 *  Editable.js
*/

"use strict";

var editableInx = 0;
var editables = [];

function Editable( options ) {
    this.options = options;
    this.backend = systemPath + 'extensions/editable/backend/_editable_backend.php';
    this.dataRef = '';
    this.id = '';
    this.recKey = '';
    this.ignoreBlur = [];
    this.origValue = [];
    this.newValue = [];
    this.editingTimeout = 60; // sec

    this.showAllButtons = false;
    this.showOkButton = false;
    this.showCancelButton = false;
    this.timeoutTimer = false;
    this.clickCnt = 0;
    this.firstClickTime = 0;
    this.dblclickTime = 500;    // [ms] time window within which to detect double click
    this.saving = [];
    this.dataTable = false;


    this.init = function() {
        // determine global state:
        this.showAllButtons = $('body').hasClass('touch') ||
            $('html').hasClass('touchevents') ||
            $('.lzy-editable-show-buttons').length;

        if (this.showAllButtons) {
            this.showOkButton = true;
            this.showCancelButton = true;
        }
        const $edElem = $('.lzy-editable');
        if ( !this.dataTable ) {
            const $table = $edElem.closest('.dataTables_wrapper');
            if ($table.length) {
                const tableInx = $('[data-inx]', $table).attr('data-inx');
                if (typeof tableInx !== 'undefined') {
                    this.dataTable = lzyTable[tableInx];
                    mylog('Editable: DataTable detected', false);
                }
            }
        }

        // make all editable elements reachable by kbd:
        $edElem.attr('tabindex', 0);

        this.initEditingHandlers( $edElem );
    }; // init



    this.makeEditable = function( that ) {
        const parent = this;
        const $elem = $( that );
        this.$elem = $elem;

        this.parseElem(); // get: id, dataRef

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
            mylog('editable element is locked or frozen -> ignored', false);
            $elem.blur();
            return;
        }

        // make sure field has fixed width (not only min/max-width):
        $elem.css('width', $elem.outerWidth() + 'px');

        // show waiting status:
        $elem.addClass('lzy-wait');
        mylog('make editable: ' + this.dataRef, false);

        // lock field:
        this.lockField(function(json) {
            const _id = parent._id;
            const $elem = parent.$elem;
            parent.ignoreBlur[ parent.dataRef ] = false;

            // evaluate lock request's response and continue making editable:
            json = json.replace(/(.*[\]}])#.*/, '$1');    // remove trailing #comment
            if (!json) {
                return;
            }

            const data = JSON.parse(json);
            if (data.result.match(/^ok/)) {
                parent.updateUi(data);
                // -> continue...

            } else if (data.result.match(/^failed#lockFrozen/)) {
                $elem.addClass('lzy-element-frozen');
                mylog('editable element is frozen: ' + parent.dataRef);
                lzyPopup({
                    contentFrom: '#lzy-info-frozen',
                });
                return;
            } else { // other irregular response:
                mylog('editable ajax response: ' + data.result);
                const $row = $elem.closest('tr');
                if ($row.length) {
                    $row.addClass('lzy-element-locked');
                } else {
                    $elem.addClass('lzy-element-locked');
                }
                lzyPopup({
                    contentFrom: '#lzy-error-locked',
                });
                return;
            }

            $elem.removeClass('lzy-editable').addClass('lzy-editable-active');

            // on touch devices: always show buttons
            if (!parent.showAllButtons) {
                parent.showOkButton = ( $elem.hasClass('lzy-editable-show-button') ||
                    $elem.closest('.lzy-editable-wrapper').hasClass('lzy-editable-show-button'));
            }
            let buttons = '';
            if (parent.showOkButton) {
                buttons = parent.renderOkButton();
                if (parent.showCancelButton) {
                    buttons = buttons + parent.renderCancelButton();
                }
            }

            // now inject input/textarea element and buttons:
            let $innerEl = null;
            const val = $elem.text();
            parent.origValue[ parent.dataRef ] = val;

            // transform element to input or textarea:
            const isMultilineElem = parent.isMultiLineElement( $elem );
            const txt = $elem.text();
            const isDblclickable = ($elem.closest('.lzy-multiline-enabled').length || txt.match(/\n/));
            if (!isMultilineElem && isDblclickable) {
                new Promise(function (resolve) {
                    const wait = parent.dblclickTime - (time() - parent.firstClickTime);
                    if (wait > 0) {
                        setTimeout(
                            function () {
                                const isDblClick = (parent.clickCnt >= 2);
                                resolve(isDblClick);
                            }, wait);
                    } else {
                        const isDblClick = (parent.clickCnt >= 2);
                        resolve(isDblClick);
                    }

                }).then(function (isDblClick) {
                    parent.clickCnt = 0;
                    if (isDblClick) {
                        $elem.html(buttons + '<textarea id="' + _id + '" aria-live="polite" aria-relevant="all">' + val + '</textarea>');
                        $elem.addClass('lzy-editable-multiline');
                        $innerEl = $('textarea', $elem);
                    } else {
                        $elem.html(buttons+'<input id="' + _id + '" aria-live="polite" aria-relevant="all" value="' + val + '"/>');
                        $innerEl = $('input', $elem);
                    }

                    // set up timeout on editable field:
                    parent.resetEditableTimeout();

                    $elem.removeClass('lzy-wait');
                    parent.revealButtons($elem);
                    parent.putFocus( $innerEl );
                });
                return;

            } else if (isMultilineElem) {
                $elem.html(buttons + '<textarea id="' + _id + '" aria-live="polite" aria-relevant="all">' + val + '</textarea>');
                $elem.addClass('lzy-editable-multiline');
                $innerEl = $('textarea', $elem);
            } else {
                $elem.html(buttons+'<input id="' + _id + '" aria-live="polite" aria-relevant="all" value="' + val + '"/>');
                $innerEl = $('input', $elem);
            }

            // set up timeout on editable field:
            parent.resetEditableTimeout();

            $elem.removeClass('lzy-wait');
            parent.revealButtons($elem);
            parent.putFocus( $innerEl );
        }); // lockField
    }; // makeEditable



    this.lockField = function(callback) {
        const id = this.id;
        const parent = this;
        mylog('editable lockField: ' + this.dataRef, false);
        const data = {
            cmd: 'lock',
            id: id,
            srcRef: this.srcRef,
            dataRef: this.dataRef,
        };

        $.ajax({
            url: this.backend,
            type: 'POST',
            data: data,
            cache: false,
            success: function (json) {
                mylog( 'editable lockField success: ' + json, false );
                callback(json);
            },
            error: function(xhr, status, error) {
                mylog( 'editable lockField: failed -> ' + error );
                parent.$elem.removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
    }; // lockField



    this.unlockField = function( dataRef ) {
        const id = this.id;
        if (typeof dataRef === 'undefined') {
            dataRef = this.dataRef;
        }
        mylog('editable unlockField: ' + dataRef, false);
        const parent = this;
        const data = {
            cmd: 'unlock',
            id: id,
            srcRef: this.srcRef,
            dataRef: dataRef,
        };
        $.ajax({
            url: this.backend,
            type: 'POST',
            data: data,
            cache: false,
            success: function (json) {
                mylog( 'editable unlockField success: ' + json, false );
                parent.handleResponse(json);
            },
            error: function(xhr, status, error) {
                mylog( 'editable unlockField: failed -> ' + error );
                parent.$elem.removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }; // unlockField



    this.saveAndUnlockField = function( dataRef ) {
        const parent = this;
        const $elem = $('[data-ref="' + dataRef + '"]');
        const id = $elem.attr('id');
        $elem.removeClass('lzy-editable-active').addClass('lzy-editable');
        let newValue = parent.newValue[ dataRef ];
        if (typeof newValue === 'undefined') {
            newValue = $('input', $elem).val();
        }
        if (this.origValue[ dataRef ] === newValue) {
            mylog('editable unchanged, not saving ' + dataRef, false);
            this.ignoreBlur[ dataRef ] = true;
            if (this.showOkButton) {
                this.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    $elem.text(parent.origValue[ dataRef ]);
                    parent.unlockField();
                }, 150);
            } else {
                $elem.text(this.origValue[ dataRef ]);
                this.unlockField();
            }
            return;
        }

        mylog('editable save: ' + dataRef + ' => ' + newValue, false);
        const text = encodeURI( newValue );   // -> php urldecode() to decode
        const data = {
            cmd: 'save',
            id: id,
            text: text,
            srcRef: this.srcRef,
            dataRef: dataRef,
        };
        this.saving[ dataRef ] = true;

        $.ajax({
            url: this.backend,
            type: 'post',
            data: data,
            cache: false,
            success: function (json) {
                mylog( 'editable save success: ' + json, false );
                parent.saving[ dataRef ] = false;
                parent.handleResponse(json,'#lzy-db-error');
            },
            error: function(xhr, status, error) {
                mylog( 'editable save: failed -> ' + error );
                parent.saving[ dataRef ] = false;
                parent.$elem.removeClass('lzy-wait').addClass('lzy-locked');
            }
        });
        return true;
    }; // saveAndUnlockField



    this.terminateEditable = function( $elems, save ) {
        const parent = this;
        let $elem = null;

        if ((typeof $elems !== 'undefined') && ($elems.length)) {
            if (!$elems.hasClass('lzy-editable-active')) {
                $elems = $elems.closest('.lzy-editable-active');
            }
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
            const dataRef = $elem.attr('data-ref');

            // save data resp. just terminate editing mode:
            if ( save ) {
                parent.saveAndUnlockField( dataRef );
            } else {
                mylog( 'editable terminate/skipping save: ' + dataRef, false );
                parent.newValue[ dataRef ] = parent.origValue[ dataRef ];
                if ((typeof parent.saving[ dataRef ] === 'undefined') || !parent.saving[ dataRef ]) {
                    parent.saving[ dataRef ] = false;
                    parent.unlockField( dataRef );
                } else {
                    parent.saving[ dataRef ] = false;
                }
            }

            if (parent.showOkButton) {
                parent.hideButtons($elem);
                setTimeout(function(){ // wait for buttons to disappear
                    if ( dataRef ) {
                        $elem.text( parent.newValue[ dataRef ] );
                        $elem.removeClass('lzy-editable-active').addClass('lzy-editable');
                        parent.ignoreBlur[ dataRef ] = true;
                    }
                    $('input, textarea', $elem).blur();
                }, 150);
            } else {
                if ( dataRef ) {
                    $elem.text(parent.newValue[ dataRef ]).removeClass('lzy-editable-active').addClass('lzy-editable');
                    parent.ignoreBlur[ dataRef ] = true;
                }
                $('input, textarea', $elem).blur();
            }
        });
    }; // terminateEditable



    this.parseElem = function() {
        const $elem = this.$elem;

        // get ID:
        this.id = $elem.attr('id');
        if (typeof this.id === 'undefined') {
            const x = $elem[0].cellIndex;
            const y = parseInt( $elem.closest('tr').attr('class').replace(/\D/g, '') ) - 1;
            this.id = 'lzy-elem-' + y + '-' + x;
            $elem.attr('id', this.id);
        }
        this._id = '_' + this.id;

        // get dataRef:
        this.dataRef = $elem.attr('data-ref');
        if (typeof this.dataRef === 'undefined') {
            this.dataRef = $elem.closest('[data-ref]').attr('data-ref');
        }

        // get recKey:
        this.recKey = $elem.attr('data-reckey');
        if (typeof this.recKey === 'undefined') {
            this.recKey = $elem.closest('[data-reckey]').attr('data-reckey');
            if (typeof this.recKey === 'undefined') {
                this.recKey = $('#lzy-reckey').text().trim();
                if (typeof this.recKey === 'undefined') {
                    const m = this.dataRef.match(/(.*?),/);
                    if (m) {
                        this.recKey = m[1];
                    }
                }
            }
        }

        // get srcRef:
        this.srcRef = this.getDatasrcRef();
    }; // parseElem



    this.initEditingHandlers = function( $edElem ) {
        const parent = this;
        let $wrapper = $edElem.parent();
        $wrapper
            .on('mousedown', '.lzy-editable', function (e) {
                e.stopImmediatePropagation();
                if (!parent.clickCnt) {
                    parent.firstClickTime = time();
                }
                parent.clickCnt++;
            })
            .on('click', 'input, textarea', '.lzy-editable', function (e) {
                mylog( 'click' );
                e.stopImmediatePropagation();
            })
            .on('focus', '.lzy-editable', function () {
                const on = $(this).hasClass('lzy-editable');
                if (!on) {
                    return;
                }
                parent.makeEditable( this );
            })

            .on('blur', '.lzy-editable-active input, .lzy-editable-active textarea', function () {
                parent.clickCnt = 0;
                const $this = $(this).parent();
                if (parent.timeoutTimer) {
                    clearTimeout(parent.timeoutTimer);
                    parent.timeoutTimer = false;
                }

                // wait a moment to make sure key and button handlers fire first:
                setTimeout(function () {
                    if (!parent.ignoreBlur[ parent.dataRef ]) {
                        parent.ignoreBlur[ parent.dataRef ] = true;

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
                parent.newValue[ parent.dataRef ] = $('input, textarea', $el).val();
                parent.ignoreBlur[ parent.dataRef ] = true;
                parent.terminateEditable($el, true);
            });

        if (this.showCancelButton) {
            $wrapper.on('click', '.lzy-editable-cancel-button', function (e) {
                e.stopPropagation();
                const $el = $(this).parent();
                parent.newValue[ parent.dataRef ] = parent.origValue[ parent.dataRef ];
                parent.ignoreBlur[ parent.dataRef ] = true;
                parent.terminateEditable($el, false);
            });
        }

        this.setupKeyHandlers();
    };



    this.setupKeyHandlers = function() {
        // Enter: save and close
        // ESC:     close and restore initial value
        // Tab:  save, close and jump to next tabstop (incl. next editable)

        const parent = this;
        const $elem = $('.lzy-editable');

        $elem.on('keydown', 'input, textarea', function(e) {
            const key = e.which;
            const $el = $(this).parent();
            const dataRef = $el.attr('data-ref');
            if (key === 9) {                  // Tab
                mylog( 'editable KeyTab: ' + dataRef, false );
                parent.onKeyTab(e, $el, dataRef);
            } else if ((key === 13) || (key === 83)) {  // Meta-Enter or Meta-s
                if (macKeys.cmdKey || event.ctrlKey) { // Win: ctrl-enter / Mac: cmd-enter
                    e.preventDefault();
                    mylog('editable KeyCmdEnter: ' + dataRef, false);
                    parent.onKeyEnter(e, $el, dataRef);
                }

            }
        });

        $elem.on('keyup', 'input', function(e) {
            e.preventDefault();
            const key = e.which;
            const $el = $(this).parent();
            const dataRef = $el.attr('data-ref');
            if (key === 13) {  // Enter
                mylog( 'editable KeyEnter: ' + dataRef, false );
                parent.onKeyEnter(e, $el);

            } else if (key === 27) {                // ESC
                mylog( 'editable KeyESC: ' + dataRef, false );
                parent.onKeyEsc(e, $el, dataRef);

            } else {
                parent.newValue[ dataRef ] = $(this).val();
            }
        });

        $elem.on('keyup', 'textarea', function(e) {
            e.preventDefault();
            const key = e.which;
            const meta = macKeys.cmdKey || event.ctrlKey;
            const $el = $(this).parent();
            const dataRef = $el.attr('data-ref');
            if (key === 27) {                // ESC
                mylog( 'editable KeyESC: ' + dataRef, false );
                parent.onKeyEsc(e, $el, dataRef);

            } else {
                parent.newValue[ dataRef ] = $(this).val();
            }
        });
    }; // setupKeyHandler



    this.onKeyEnter = function( e, $elem, dataRef ) {
        e.stopImmediatePropagation();
        this.newValue[ dataRef ] = $('input, textarea', $elem).val();
        this.ignoreBlur[ dataRef ] = true;
        this.terminateEditable($elem, true);
    }; // onKeyEnter



    this.onKeyTab = function( e, $elem, dataRef ) {
        e.stopImmediatePropagation();
        this.newValue[ dataRef ] = $('input, textarea', $elem).val();
        this.ignoreBlur[ dataRef ] = true;
        this.terminateEditable( $elem,true);
    }; // onKeyTab



    this.onKeyEsc = function( e, $elem, dataRef ) {
        e.stopImmediatePropagation();
        this.newValue[ dataRef ] = this.origValue[ dataRef ];
        this.ignoreBlur[ dataRef ] = true;
        this.terminateEditable($elem, false);
    }; // onKeyEsc



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



    this.getDatasrcRef = function() {
        let srcRef = '';
        srcRef = this.$elem.attr('data-datasrc-ref');
        if (typeof srcRef === 'undefined') {
            srcRef = this.$elem.closest('[data-datasrc-ref]').attr('data-datasrc-ref');
            if (typeof srcRef === 'undefined') {
                srcRef = $('[data-datasrc-ref]').attr('data-datasrc-ref');
            }
        }
        return srcRef;
    }; // getDatasrcRef



    this.handleResponse = function(json0, msgId, errorHandler) {
        const json = json0.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        if (!json) {
            return;
        }

        const data = JSON.parse(json);
        const result = data.result;
        if (result.match(/^ok/)) {
            this.updateUi( data );

        } else {
            if ((typeof msgId !== 'undefined') && msgId) {
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
        let txt = '';
        let dSel;
        const parent = this;
        const data1 = data.data;
        let redrawDataTables = false;

        if (typeof data1 === 'object') {
            for (let tSel in data1) {
                txt = data1[ tSel ];
                $( tSel ).each( function() {
                    const $this = $(this);
                    if (!$this.hasClass('lzy-editable-active')) {
                        mylog('editable updateUi: ' + tSel + ' <= ' + txt, false);
                        let dataTable = false;
                        if (($this.closest('.dataTable').length > 0)) {
                            const tableInx = $this.closest('[data-inx]').attr('data-inx');
                            if (typeof tableInx !== 'undefined') {
                                dataTable = lzyTable[ parseInt(tableInx) ];
                                redrawDataTables = true;
                            }
                        }

                        if (dataTable) {
                            dataTable.cell( this ).data( txt );
                        } else {
                            $this.text( txt );
                        }

                        dSel = tSel.match(/\[data-ref='(.*)']/);
                        if ((typeof dSel[1] !== 'undefined') && (typeof parent.origValue !== 'undefined')) {
                            parent.origValue[ dSel[1] ] = txt;
                        }
                    } else {
                        mylog('editable updateUi: ' + tSel + ' skipped, had class lzy-editable-active', false );
                    }
                });
            }

            // if DataTables are active, we need to redraw them:
            if (redrawDataTables) {
                mylog('liveData:updateDOM: redraw table', false);
                for (let i=1; true; i++) {
                    const dataTable = lzyTable[i];
                    if (typeof dataTable === 'undefined') {
                        break;
                    }
                    dataTable.draw();
                }
            }
        } else {
            mylog('Error in editable updateUi: ' + data1 );
        }
    }; // updateUi

    // this.updateUi = function(data) {
    //     let txt = '';
    //     let dSel;
    //     const parent = this;
    //     const data1 = data.data;
    //     if (typeof data1 === 'object') {
    //         for (let tSel in data1) {
    //             txt = data1[ tSel ];
    //             $( tSel ).each( function() {
    //                 if (!$(this).hasClass('lzy-editable-active')) {
    //                     mylog('editable updateUi: ' + tSel + ' <= ' + txt, false);
    //                     if (parent.dataTable) {
    //                         parent.dataTable.cell( this ).data( txt );
    //                     } else {
    //                         $( this ).text( txt );
    //                     }
    //                     dSel = tSel.match(/\[data-ref='(.*)']/);
    //                     if ((typeof dSel[1] !== 'undefined') && (typeof parent.origValue !== 'undefined')) {
    //                         parent.origValue[ dSel[1] ] = txt;
    //                     }
    //                 } else {
    //                     mylog('editable updateUi: ' + tSel + ' skipped, had class lzy-editable-active', false );
    //                 }
    //             });
    //         }
    //         if (parent.dataTable) {
    //             parent.dataTable.draw();
    //         }
    //     } else {
    //         mylog('Error in editable updateUi: ' + data1 );
    //     }
    // }; // updateUi



    this.resetEditableTimeout = function() {
        const parent = this;
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
        const $elem = $('.lzy-editable-active');
        this.terminateEditable($elem, false);
    }; // onEditableTimedOut



    this.putFocus = function( $elem ) {
        setTimeout(function () {
            const fldLength= $elem.val().length;
            $elem.focus();
            $elem[0].setSelectionRange(fldLength, fldLength);
        }, 150);
    }; // putFocus



    this.isMultiLineElement = function( $elem ) {
        const m1 = $('.lzy-editable-multiline', $elem).length;
        const m2 = $elem.closest('.lzy-editable-multiline').length;
        return (m1 || m2);
    }; // isMultiLineElement

} // Editable

