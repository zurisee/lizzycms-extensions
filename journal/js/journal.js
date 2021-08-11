// Journal

"use strict";

var journal = null;



function Journal() {
    this.lastUpdated = 0;
    this.refs = '';
    this.elemInx = 0;
    this.backend = './';
    this.journalLocked = false;



    this.init = function() {
        this.setupEventHandlers();
        this.lastUpdated = -1;
        this.scrollToBottom();
    }; // init



    this.setupEventHandlers = function() {
        const parent = this;
        $('.lzy-journal-send button').click(function () {
            parent.handleUserInput( this );
        });

        $('.lzy-journal-edit-btn').click(function () {
            if ($(this).hasClass('lzy-activated')) {
                return;
            }
            liveData.abortAjax();
            $(this).addClass('lzy-activated');
            const $journalElem = $(this).closest('.lzy-journal');
            parent.srcRef = $journalElem.attr('data-datasrc-ref');
            parent.dataRef = $('[data-ref]', $journalElem).attr('data-ref');
            parent.useRichEditor = $journalElem.hasClass('lzy-editor-rich');

            execAjaxPromise('lock-rec', {ds: parent.srcRef, dataRef: parent.dataRef})
                .then(function( json ) {
                    var data = null;
                    try {
                        data = JSON.parse( json );
                    }
                    catch (e) {
                        mylog('Journal Error: ' + json);
                        return;
                    }
                    if (!data.res.match(/ok/i)) {
                        if (data.res.match(/restart/i)) {
                            lzyReload();
                            return;
                        } else {
                            $('.lzy-journal .lzy-activated').removeClass('lzy-activated');
                            mylog('Journal: DB locked');
                            lzyPopup('{{ Sorry, Database is currently locked }}');
                            return;
                        }
                    }

                    parent.journalLocked = true;
                    initLzyEditor({
                        srcRef: parent.srcRef,
                        dataRef: parent.dataRef,
                        useRichEditor: parent.useRichEditor,
                        postSaveCallback: 'journalOnCloseCallback',
                        postCancelCallback: 'journalOnCloseCallback',
                    });
                }); // done
        });

        this.setupKeyHandlers();
    }; // setupEventHandlers



    this.setupKeyHandlers = function() {
        var parent = this;

        // keyhandler for INPUT field (singleline mode):
        $('input', this.$elem).on('keydown', function(e) {
            const meta = macKeys.cmdKey || event.ctrlKey;
            const key = e.which;
            var $el = $(this);
            if ((key === 13) || ((key === 83) && meta)) {  // Enter or Meta-s
                e.preventDefault();
                mylog('editable KeyEnter or cmdS', false);
                const text = $el.val();
                parent.handleUserInput( $el );
            }
        });

        // keyhandler for TEXTAREA field (multiline mode):
        $('textarea', this.$elem).on('keydown', function(e) {
            const meta = macKeys.cmdKey || event.ctrlKey;
            const key = e.which;
            var $el = $(this);
            if (meta && ((key === 13) || (key === 83))) {  // Meta-Enter or Meta-s
                e.preventDefault();
                mylog('editable KeyCmdEnter or cmdS', false);
                parent.handleUserInput( $el );
            }
        });

        $('input, textarea', this.$elem).on('keyup', function(e) {
            e.preventDefault();
            const key = e.which;
            var $el = $(this);
            if (key === 27) {                // ESC
                mylog( 'editable KeyESC: ', false );
                $el.val('').focus();
            }
        });
    }; // setupKeyHandlers



    this.handleUserInput = function( that ) {
        if ( typeof that[0] !== 'undefined') {
            this.$elem = that.closest('.lzy-journal');
        } else {
            this.$elem = $(that).closest('.lzy-journal');
        }
        this.srcRef = this.$elem.attr('data-datasrc-ref');
        this.dataRef = this.getDataRef();
        const $entryField = $('textarea,input', this.$elem);
        var text = $entryField.val();
        $('.lzy-journal-entry', this.$elem).addClass('lzy-wait');
        this.saveValue( text );
    }; // handleUserInput



    this.saveValue = function( text ) {
        text = encodeURI( text );
        var parent = this;
        const id = $('[data-ref]', this.$elem).attr('id');
        mylog('journal saveValue ' + text, false);
        var data = {
            journal: 'save',
            text: text,
            srcRef: this.srcRef,
            dataRef: this.dataRef,
            id: id,
        };

        $.ajax({
            url: this.backend,
            'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
            type: 'POST',
            data: data,
            cache: false,
            success: function (json) {
                $('.lzy-wait', parent.$elem).removeClass('lzy-wait');
                mylog( 'journal saveValue success: ' + json, false );
                parent.handleResponse( json );
            },
            error: function(xhr, status, error) {
                mylog( 'journal lockField: failed -> ' + error );
                $('.lzy-wait', parent.$elem).removeClass('lzy-wait');
            }
        });
    }; // saveValue



    this.handleResponse = function(json0) {
        var json = json0.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        if (!json) {
            return;
        }

        if (json.match(/xdebug-error/)) {
            json = json.replace(/<[^>]*>?/gm, '');
            mylog( json );
            return;
        }

        var data = null;
        try {
            data = JSON.parse( json );
        }
        catch (e) {
            mylog('Journal Error: ' + json);
            return;
        }

        const result = data.result;
        if (result.match(/^ok/)) {
            const html = data.data;
            $('.lzy-journal-presentation', this.$elem).html( html );
            this.scrollToBottom();

            $('.lzy-journal-entry', this.$elem).val( '' ).focus();
            $('.lzy-textarea-autogrow', this.$elem).attr('data-replicated-value','');
        } else {
            var errMsg = json0.replace(/.*\#(.*)"}/, '$1');
            if (errMsg.match(/xdebug-error/)) {
                errMsg = errMsg.replace(/<[^>]*>?/gm, '');
            }
            mylog( errMsg );
            lzyPopup('{{ Sorry, Database is currently locked }}');
            if (typeof journalErrorHandler === 'function') {
                journalErrorHandler(data);
            }
        }
    }; // handleResponse



    this.getDataRef = function() {
        var m,s,$elem, tagName;
        var dataRef = $('[data-ref]', this.$elem).attr('data-ref');
        if ((typeof dataRef !== 'string') || !dataRef) {
            return '';
        }
        if ((dataRef.charAt(0) === '#') || (dataRef.charAt(0) === '.')) {
            m = dataRef.match( /(.*?),(.*)/ );
            if (m) {
                $elem = $( m[1] );
                tagName = $elem[0].tagName;
                if (tagName === 'SELECT') {
                    s = $(':selected', $elem).val();
                } else if (tagName === 'INPUT') {
                    s = $elem.val();
                } else {
                    s = $elem.text();
                }
                dataRef = s.trim() + ',' + m[2];
            }
        }
        return dataRef;
    }; // getDataRef



    this.unlockRec = function () {
        if (this.journalLocked) {
            execAjax({ds: this.srcRef, dataRef: this.dataRef}, 'unlock-rec');
        }
        this.journalLocked = false;
    }; // unlock



    this.scrollToBottom = function() {
        var $elem = $( '.lzy-journal-presentation-wrapper' );
        var h, $el = null;
        $elem.each(function () {
            $el = $( this );
            h = $el.get(0).scrollHeight + 10;
            $el.animate({ scrollTop: h }, 500);
        });
    }; // scrollToBottom
} // Journal



function journalLiveDataCallback( data )
{
    // updates content of journal presentation box.
    // callback invoked via attribute 'data-live-pre-update-callback' in HTML.
    var val = null;
    for (var targSel in data) {
        val = data[ targSel ];
        const ch1 = targSel.charAt(0);
        if ((ch1 !== '#') && (ch1 !== '#') && (ch1 !== '[')) {
            targSel = '#' + targSel;
        }
        mylog('journal live updating: ' + targSel + ' val: ' + val, false);
        $( targSel ).html( val );
    }

    journal.scrollToBottom();
    return false;
} // journalLiveDataCallback



function journalOnCloseCallback()
{
    $('.lzy-journal .lzy-activated').removeClass('lzy-activated');
    if (journal.journalLocked) {
        journal.unlockRec();
    }
    liveData.init(true);
} // journalOnCloseCallback



function initJournal() {
    if (!journal) {
        journal = new Journal();
    }
    journal.init();
    return journal;
} // liveJournal



$(window).bind('beforeunload', function() {
    if (journal.journalLocked) {
        journal.unlockRec();
    }
});



$('.lzy-textarea-autogrow textarea.lzy-journal-entry').on('input', function() {
    this.parentNode.dataset.replicatedValue = this.value;
});

// $.fn.journal = function () {
//     // initialize:
//     $( this ).each( function() {
//         var $this = $(this);
//         journal = new Journal( $this );
//         journal.init();
//     });
// } // $.fn.journal

