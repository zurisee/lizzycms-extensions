// Chronicle

"use strict";

var chronicle = null;



function Chronicle() {
    this.ajaxHndl = null;
    this.lastUpdated = 0;
    this.refs = '';
    this.elemInx = 0;
    this.backend = './';


    this.init = function() {
        this.setupEventHandlers();

        this.lastUpdated = -1;

        this.scrollToBottom();

    }; // init



    this.setupEventHandlers = function() {
        const parent = this;
        $('.lzy-chronicle-send button').click(function () {
            parent.handleUserInput( this );
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
            this.$elem = that.closest('.lzy-chronicle');
        } else {
            this.$elem = $(that).closest('.lzy-chronicle');
        }
        this.srcRef = this.$elem.attr('data-datasrc-ref');
        this.dataRef = this.getDataRef();
        const $entryField = $('textarea,input', this.$elem);
        var text = $entryField.val();
        $('.lzy-chronicle-entry', this.$elem).addClass('lzy-wait');
        this.saveValue( text );
    }; // handleUserInput




    this.saveValue = function( text ) {
        text = encodeURI( text );
        var parent = this;
        const id = $('[data-ref]', this.$elem).attr('id');
        mylog('chronicle saveValue ' + text, false);
        var data = {
            chronicle: 'save',
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
                mylog( 'chronicle saveValue success: ' + json, false );
                parent.handleResponse( json );
            },
            error: function(xhr, status, error) {
                mylog( 'chronicle lockField: failed -> ' + error );
                $('.lzy-wait', parent.$elem).removeClass('lzy-wait');
            }
        });
    }; // saveValue



    this.handleResponse = function(json0) {
        const json = json0.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        if (!json) {
            return;
        }

        const data = JSON.parse(json);
        const result = data.result;
        if (result.match(/^ok/)) {
            const html = data.data;
            $('.lzy-chronicle-presentation', this.$elem).html( html );
            this.scrollToBottom();

            $('.lzy-chronicle-entry', this.$elem).val( '' ).focus();
            $('.lzy-textarea-autogrow', this.$elem).attr('data-replicated-value','');
        }
    }; // handleResponse




    // this.resolveIndirectRef = function( dataRef ) {
    this.getDataRef = function() {
        var m,s,$elem, tagName;
        var dataRef = $('[data-ref]', this.$elem).attr('data-ref');
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



    this.scrollToBottom = function() {
        var $elem = $( '.lzy-chronicle-presentation-wrapper', this.$elem );
        var h, $el = null;
        $elem.each(function () {
            $el = $( this );
            h = $el.get(0).scrollHeight + 10;
            $el.animate({ scrollTop: h }, 500);
        });
    }; // scrollToBottom
} // Chronicle



function chronicleLiveDataCallback( data )
{
    // updates content of chronicle presentation box.
    // callback invoked via attribute 'data-live-pre-update-callback' in HTML.
    var val = null;
    var $chronicle = null;
    var $presentationBox = null;
    for (var targSel in data) {
        val = data[ targSel ];
        mylog('chronicle live updating: ' + targSel + ' val: ' + val, false);
        $( targSel ).html( val );
    }

    chronicle.scrollToBottom();
    return false;
} // chronicleLiveDataCallback




function initChronicle() {
    if (!chronicle) {
        chronicle = new Chronicle();
    }
    chronicle.init();
    return chronicle;
} // liveChronicle




// $.fn.chronicle = function () {
//     // initialize:
//     $( this ).each( function() {
//         var $this = $(this);
//         chronicle = new Chronicle( $this );
//         chronicle.init();
//     });
// } // $.fn.chronicle

