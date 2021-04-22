/*
        calendar.js

        Custom callback functions:
            openCalPopupHandler( inx, event, isNewEvent )
            openPostCalPopupHandler( inx, event, isNewEvent )
            customOpenNewCalPopup( event )
            customOpenCalPopup( event )
 */

var inx = 0;
var lzyCalendarInx = 0;
var lzyCalendar = [];



function LzyCalendar() {
    this.doSubmit = true;
    this.fcStdElems = ['allDay','className','end','i','source','start','title','_id','__proto__','event'];
    this.deleteNotSave = false;
    this.fullCal = null;
    this.options = null;
    this.inx = null;
    this.$formWrapper = null;
    this.titleWidth = null;
} // LzyCalendar




LzyCalendar.prototype.init = function( $elem, options ) {
    let inx = options.inx;
    let parent = this;
    this.$elem = $elem;
    this.backendUrl = calBackend + '?inx=' + inx + '&ds=' + $elem.attr('data-datasrc-ref'),
    this.options = options;
    this.inx = inx;
    this.$formWrapper = $('#lzy-cal-popup-template-' + inx);

    if (typeof options.fullCalendarOptions.slotMinTime !== 'undefined') {
        this.options.calDayStart = options.fullCalendarOptions.slotMinTime;
    } else {
        this.options.calDayStart = '08:00';
    }

    // default values for fullCalendarOptions:
    let fullCalendarOptionDefaults = {
        headerToolbar: {
            left: 'prev,today,next',
            center: 'title',
            right: 'timeGridWeek,dayGridMonth,listYear',
        },
        locale: calLang,
        timeZone: false,
        navLinks: true, // can click day/week names to navigate views
        height: 'auto',
        dayMaxEvents: true, // allow "more" link when too many events
        selectable: true,
        nowIndicator: true,
        weekNumbers: true,
        weekNumberCalculation: 'ISO',
        slotMinTime: '07:00',
        slotMaxTime: '18:00',
        businessHours: { daysOfWeek: [ 1, 2, 3, 4, 5 ], startTime: '08:00', endTime: '17:00'},
        buttonText: {
            prev: '〈 ',
            next: ' 〉',
            prevYear: '《',
            nextYear: '》',
        },
        events: {
            url: this.backendUrl + '&get',
            success: function( args ) {
                mylog('cal events fetched', false);
            },
            failure: function( events ) {
                console.log('Error in calendar data:');
                console.log( events );
            }
        },
        eventContent: function( args ) {
            return parent.renderEvent( args );
        },
        viewDidMount: function (e) {
            parent.onViewReady( e );
        },
        dateClick: function (e) {
            parent.openCalPopup(inx, e, true);
        },
        eventClick: function (e) {
            parent.openCalPopup(inx, e, false);
        },
        eventDrop: function (e) {
            return parent.calEventChanged(inx, e);
        },
        eventResize: function (e) {
            parent.calEventChanged(inx, e);
        },
        windowResize: function() {
            parent.onResize();
        }
    }; // fullCalendarOptionDefaults

    // === initialize FullCalendar ============================================
    let fullCalendarOptions = Object.assign({}, fullCalendarOptionDefaults, options.fullCalendarOptions);

    if (typeof options.categories !== 'undefined') {
        fullCalendarOptions.eventClassNames = function(arg) {
            if (typeof arg.event._def.extendedProps.category !== 'undefined') {
                const catName = arg.event._def.extendedProps.category;
                const catInx = options.categories.indexOf( catName ) + 1;
                return 'lzy-cal-category-' + catInx + ' lzy-cal-category-' + catName.replace(/\s+/, '-').toLowerCase();
            }
        };
    }

    mylog(fullCalendarOptions, false);
    let elem = $elem[0];
    this.fullCal = new FullCalendar.Calendar(elem, fullCalendarOptions);
    this.fullCal.render();

    this.titleWidth = $('.fc-header-toolbar > div:first-child .fc-button-group', $elem).width() +
        $('.fc-header-toolbar > div:nth-child(2) h2', $elem).width() +
        $('.fc-header-toolbar > div:last-child .fc-button-group', $elem).width() + 10;
    this.onResize();
    this.setupTriggers();

}; // init




LzyCalendar.prototype.onViewReady = function( arg ) {
    // view changed, so update state on host:
    let viewType = arg.view.type;
    if (this.options.initialView !== viewType) {
        this.storeViewMode(inx, viewType);
        this.options.initialView = viewType;
    }
}; // onViewReady




LzyCalendar.prototype.renderEvent = function( arg ) {
    if (typeof customRenderEvent === 'function') {
        let res = customRenderEvent( arg );
        if (res) {
            return res;
        }
    }
    return this.defaultRenderEvent( arg );
}; // renderEvent




LzyCalendar.prototype.defaultRenderEvent = function( arg ) {
    let options = this.options;

    // apply summary:
    let el = document.createElement('div');
    el.classList.add('lzy-cal-event-title');
    let lbl = 'summary';
    if ((typeof options.fieldLabels !== 'undefined') && (typeof options.fieldLabels.event !== 'undefined')) {
        lbl = options.fieldLabels.event;
    }

    // apply  category:
    let category = '';
    if (typeof arg.event._def.extendedProps.category !== 'undefined') {
        category = arg.event._def.extendedProps.category;
    }

    // prepare time:
    let elT = document.createElement('div');
    if (arg.event._def.allDay) {
        elT.innerHTML = '';

    } else {
        let start = moment( arg.event._instance.range.start ).utc();
        let end = moment( arg.event._instance.range.end ).utc();
        elT.innerHTML = start.format('HH:mm') + ' - ' + end.format('HH:mm');
    }
    elT.classList.add('lzy-cal-time');

    // prepare prefix:
    let prefix = options.catPrefixes[ category ];
    if (!prefix || (typeof prefix === 'undefined')) {
        prefix = options.catDefaultPrefix;
    }
    if (prefix) {
        prefix = '<span class="fc-title-prefix">' + prefix + '</span>';
    }

    // apply time, prefix and title:
    let title = this.convertMD(arg.event._def.title);
    if (!title) {
        title = '&nbsp;';
    }
    el.innerHTML = '<span class="lzy-cal-title-label">'+ lbl +':</span> <span class="lzy-cal-title-text">' + prefix + ' <span>' + title + '</span></span>';
    let elements = [ elT, el ];

    // apply custom properties:
    if (arg.event.extendedProps) {
        for ( let cust in arg.event._def.extendedProps) {
            if ((cust[0] === '_') || (cust === 'i') || (cust === 'event')) {  // skip meta elements
                continue;
            }
            let s = arg.event._def.extendedProps[cust];
            if (!s) {
                continue;
            }
            s = this.convertMD( s );
            let el1 = document.createElement('div');
            let cls = cust.toLowerCase().replace(/\W/, '-');
            el1.classList.add('lzy-cal-custom');
            el1.classList.add('lzy-cal-custom-' + cls);
            if (typeof options.fieldLabels[cust] !== 'undefined') {
                cust = options.fieldLabels[cust];
            } else {
                cust = cust[0].toUpperCase() + cust.substring(1);
            }
            el1.innerHTML = '<span class="lzy-cal-custom-label">' + cust + ':</span> <span class="lzy-cal-custom-text">' + s + '</span>';
            elements.push( el1 );
        }
    }

    this.activateTooltips( arg.view.type );

    return { domNodes: elements };
}; // defaultRenderEvent




LzyCalendar.prototype.calEventChanged = function( inx, event0 ) {
    if (!this.options.editingPermission) {
        return false;
    }
    let event = event0.event;
    if (!this.checkPermission( event )) {
        lzyPopup('{{ You don\'t have permission to modify somebody else\'s event. }}');
        this.fullCal.refetchEvents();
        return false;
    }
    if (!this.checkFreeze( event0 )) {
        lzyPopup('{{ You can\'t create or modify events that lie in the past. }}');
        this.fullCal.refetchEvents();
        return;
    }

    let start = moment( event._instance.range.start ).utc();
    let end = moment( event._instance.range.end ).utc();
    let rec = {};
    rec['rec-id'] = event._def.extendedProps.i;
    rec['title'] = event._def.title;
    rec['start-date'] = start.format('YYYY-MM-DD');
    rec['start-time'] = start.format('HH:mm');
    rec['end-date'] = end.format('YYYY-MM-DD');
    rec['end-time'] = end.format('HH:mm');
    rec['allday'] = event.allDay;
    if (event.allDay) {
        rec['end-date'] = end.add('days', -1).format('YYYY-MM-DD');
    }

    for (let e in event._def.extendedProps) {
        if (e === 'i') {
            continue;
        }
        rec[e] = event._def.extendedProps[e];
    }

    let json = JSON.stringify(rec);
    let parent = this;

    $.ajax({
        url : this.backendUrl + '&save',
        type: 'post',
        data : 'json='+json,
    }).done(function(response){
        if (isServerErrMsg(response)) {
            return;
        }
        if (response !== 'ok') {
            parent.fullCal.refetchEvents();
            lzyPopup(response);
        }
    });
}; // calEventChanged




LzyCalendar.prototype.openCalPopup = function( inx, event, isNewEvent ) {
    let runDefault = true;
    if (typeof openCalPopupHandler !== 'undefined') {
        runDefault = openCalPopupHandler(inx, event, isNewEvent);
    }
    if (runDefault) {
        this.defaultOpenCalPopup(inx, event, isNewEvent);
    }
    if (typeof openPostCalPopupHandler !== 'undefined') {
        openPostCalPopupHandler(inx, event, isNewEvent);
    }
    this.doSubmit = true;
}; // openCalPopup




LzyCalendar.prototype.defaultOpenCalPopup = function( inx, event0, isNewEvent ) {
    if (!this.options.editingPermission) {
        mylog('User has insufficient privileges to edit calendar');
        if (this.options.editingPermission === null) {
            lzyPopup('{{ lzy-warning-insufficient-privileges }}');
        }
        return;
    }
    let parent = this;
    let catClass = '';
    let event = event0.event;
    if (!this.checkPermission( event )) {
        lzyPopup('{{ You don\'t have permission to modify somebody else\'s event. }}');
        return;
    }

    if (!this.checkFreeze( event0, true )) {
        lzyPopup('{{ You can\'t create or modify events that lie in the past. }}');
        parent.fullCal.refetchEvents();
        return;
    }
    this.$form = $('form', this.$formWrapper);

    this.openPopup();
    this.resetForm();

    let dateStr = '';
    let timeStr = '';
    let start = null;
    let end = null;
    let res = false;
    let defaultEventDuration = this.options.defaultEventDuration;
    let header = '';

    this.hideRestrictedCategories();

    if ( isNewEvent ) {                                       // new entry
        if (typeof customOpenNewCalPopup === 'function') {
            res = customOpenNewCalPopup( event0 );
            if (res) {
                return res;
            }
        }
        initNewEntryForm.call(this);

    } else {                                                   // existing entry
        if (typeof customOpenCalPopup === 'function') {
            res = customOpenCalPopup( event0 );
            if (res) {
                return res;
            }
        }
        initExistingEntryForm.call(this);
    }

    // update header:
    $('.lzy-popup-header > div', this.$formWrapper).html( header );
    setTimeout( function() { $('.lzy-cal-event-name', parent.$form).focus(); }, 500);




    function initNewEntryForm() {
        header = $('.lzy-cal-new-event-header', this.$form).html();

        start = moment(event0.date).utc();
        dateStr = start.format('YYYY-MM-DD');

        if (defaultEventDuration === 'allday') {
            event0.allDay = true;

        } else if (event0.view.type === 'dayGridMonth') {
            // for month view: avoid allDay event as default, unless no defaultEventDuration is defined:
            if (defaultEventDuration) {
                event0.allDay = false;
            }
            start = moment(dateStr + ' ' + this.options.calDayStart);
        }
        if (event0.allDay === false) {
            $('#lzy-cal-start-date-' + this.inx).val(dateStr).attr('data-prev-val', dateStr);
            timeStr = start.format('HH:mm');
            $('#lzy-cal-start-time-' + this.inx).val(timeStr).attr('data-prev-val', timeStr);

            $('#lzy-cal-end-date-' + this.inx).val(dateStr);
            timeStr = start.add(defaultEventDuration, 'minutes').format('HH:mm');
            $('#lzy-cal-end-time-' + this.inx).val(timeStr);

            $('.lzy-cal-event-name', this.$form).val('');
            $('.lzy-cal-event-location', this.$form).val('');
            $('.lzy-cal-comment', this.$form).text('');

        } else {    // case whole day event:
            $('#lzy-cal-start-date-' + this.inx).val(dateStr).attr('data-prev-val', dateStr);
            $('#lzy-cal-end-date-' + this.inx).val(dateStr);
            $('#lzy-cal-start-time-' + this.inx).attr('type', 'hidden').val('09:00').attr('data-prev-val', '09:00');
            $('.lzy-allday', this.$form).val('true');
            $('.lzy-cal-allday-event-checkbox', this.$form).prop('checked', true);
            $('#lzy-cal-end-time-' + this.inx).attr('type', 'hidden').val('11:00');
        }
        this.preselectCategory();

        this.resetDeleteCheckbox( false );
    } // initNewEntryForm

    
    
    
    function initExistingEntryForm() {
        header = $('.lzy-cal-modify-event-header', this.$form).html();

        let tsDiff = moment(event._instance.range.start).utcOffset(); // timezone offset -> subtract from end
        start = moment(event._instance.range.start).subtract(tsDiff, 'minutes');
        end = moment(event._instance.range.end).subtract(tsDiff, 'minutes');
        dateStr = start.format('YYYY-MM-DD');
        $('#lzy-cal-start-date-' + this.inx).val(dateStr).attr('data-prev-val', dateStr);
        if (event.allDay === false) {    // normal event
            timeStr = start.format('HH:mm');
            $('#lzy-cal-start-time-' + this.inx).val(timeStr).attr('data-prev-val', timeStr);
            $('.lzy-allday', this.$form).val('false');
            dateStr = end.format('YYYY-MM-DD');
            $('#lzy-cal-end-date-' + this.inx).val(dateStr);
            timeStr = end.format('HH:mm');
            $('#lzy-cal-end-time-' + this.inx).val(timeStr);

        } else {    // allday event:
            $('#lzy-cal-start-date-' + this.inx).val(dateStr);
            end = moment(event._instance.range.end).subtract(tsDiff + 1, 'minutes');
            dateStr = end.format('YYYY-MM-DD');
            $('#lzy-cal-end-date-' + this.inx).val(dateStr);
            $('#lzy-cal-start-time-' + this.inx).attr('type', 'hidden').val('00:00');
            $('#lzy-cal-end-time-' + this.inx).attr('type', 'hidden').val('00:00');
            $('.lzy-allday', this.$form).val('true');
            $('.lzy-cal-allday-event-checkbox', this.$form).prop('checked', true);
        }

        $('.lzy-cal-event-name', this.$form).val(event._def.title);

        let $startTime = $('#lzy-cal-start-time-' + this.inx);
        let startTime = $startTime.val();
        $startTime.attr('data-prev-val', startTime);

        // populate custom fields:
        let extendedProps = event._def.extendedProps;
        if (typeof extendedProps !== 'undefined') {
            $('.lzy-cal-comment', this.$form).text(extendedProps.comment);

            for (let fld in extendedProps) {
                if (this.fcStdElems.indexOf(fld) === -1) {
                    $('.lzy-cal-event-' + fld, this.$form).val(extendedProps[fld]);
                }
            }

            // set selected option:
            if (extendedProps.category) {
                $('.lzy-cal-category option[value=' + extendedProps.category + ']', this.$form).prop('selected', true);
                catClass = extendedProps.category.replace(/\s+/g, '-').toLowerCase().replace(/[^a-z0-9-_]+/g, '');
                $('#lzy-calendar-default-form-' + this.inx).addClass('lzy-cal-category-' + catClass);
            }
            $('.lzy-rec-id', this.$form).val(extendedProps.i);
        }

        this.resetDeleteCheckbox();

    } // initExistingEntryForm
    
}; // defaultOpenCalPopup




LzyCalendar.prototype.resetDeleteCheckbox = function( show = true ) {
    $('.lzy-cal-delete-entry-checkbox', this.$form).prop('checked', false);
    if (show) {
        $('#lzy-cal-delete-entry-' + this.inx).show();
    } else {
        $('#lzy-cal-delete-entry-' + this.inx).hide();
    }
    $('.lzy-calendar-default-submit', this.$form).val(calSubmitBtnLabel[0]);
    this.deleteNotSave = false;
}; // resetDeleteCheckbox




LzyCalendar.prototype.activateTooltips = function( viewType ) {
    // activate Tooltips
    if (!this.options.tooltips) {
        return;
    }
    setTimeout(function () {
        if (viewType === 'dayGridMonth') {
            $('.fc-daygrid-event').each(function () {
                let $elem = $(this);
                $elem.qtip({
                    content: $elem.html(),
                    position: {
                        my: 'bottom center',
                        at: 'top center',
                        target: $elem
                    },
                    hide: {
                        fixed: true,
                        delay: 500
                    }
                });
            });

        } else if (viewType === 'timeGridWeek') {
            $('.fc-timegrid-event > div:first-child').each(function () {
                let $elem = $(this);
                $elem.qtip({
                    content: $elem.html(),
                    position: {
                        my: 'bottom center',
                        at: 'top center',
                        target: $elem
                    },
                    hide: {
                        fixed: true,
                        delay: 500
                    }
                });
            });
        }
    }, 500);
}; // activateTooltips




LzyCalendar.prototype.storeViewMode = function( inx, viewName ) {
    $.ajax({
        url : this.backendUrl + '&mode=' + viewName,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
}; // storeViewMode




LzyCalendar.prototype.saveCurrDate = function( that, arg ) {
    let dateStr = moment( that.currentData.currentDate ).utc().format('YYYY-MM-DD');
    $.ajax({
        url : this.backendUrl + '&date=' + dateStr,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
}; // saveCurrDate




LzyCalendar.prototype.convertMD = function( text ) {
    if (!text) {
        return text;
    }
    // strong and em:
    text = text.replace(/([^\\])\\n/, '$1<br>');  // new-line
    text = text.replace(/^\*\*(.+?)\*\*/, '<strong>$1</strong>'); // **strong** at first pos
    text = text.replace(/([^\\])\*\*(.+?)\*\*/, '$1<strong>$2</strong>'); // **strong**
    text = text.replace(/^\*(.+?)\*/, '<em>$1</em>'); // *em*
    text = text.replace(/([^\\*])\*(.+?)\*/, '$1<em>$2</em>'); // *em*
    text = text.replace(/\\\\/, '\\'); // \\ -> \

    // links []():
    let m = text.match(/(.*)\[(.+?)\]\((.+?)\)(.*)/);
    if ( m ) {
        let href = m[3];
        if (!href.match(/^https?/)) {
            href = 'https://' + href;
        }
        text = m[1] + '<a href="'+href+'" class="lzy-cal-link" target="_blank">'+m[2]+'</a>' + m[4];
    }
    return text;
}; // convertMD




LzyCalendar.prototype.setupTriggers = function() {
    let parent = this;
    let $form = $('#lzy-cal-popup-template-' + this.inx + ' form');

    // onSubmit:
    $form.submit(function (event) {
        event.preventDefault();
        parent.submitForm()
    });

    // All-day toggle:
    $('.lzy-cal-allday-event-checkbox', this.$form).change(function () {
        let $this = $( this );
        let allday = $this.prop('checked');
        if (allday) {
            $('.lzy-allday', this.$form).val('true');
            $('#lzy-cal-start-time-' + parent.inx).attr('type', 'hidden');
            $('#lzy-cal-end-time-' + parent.inx).attr('type', 'hidden');
        } else {
            $('.lzy-allday', this.$form).val('false');
            $('#lzy-cal-start-time-' + parent.inx).attr('type', 'time');
            $('#lzy-cal-end-time-' + parent.inx).attr('type', 'time');
        }
    }); // All-day toggle


    // Delete Entry checkbox:
    $('.lzy-cal-delete-entry-checkbox', this.$form).change(function (event) {
        event.preventDefault();
        if ($('.lzy-cal-delete-entry-checkbox', parent.$form).prop('checked')) {
            parent.deleteNotSave = true;
            $('.lzy-calendar-default-submit', parent.$form).val(calSubmitBtnLabel[1]);
        } else {
            parent.deleteNotSave = false;
            $('.lzy-calendar-default-submit', parent.$form).val(calSubmitBtnLabel[0]);
        }
    });


    // Cancel button:
    $("input[type=reset]", this.$form).click(function () {
        parent.closePopup();
    });


    // if start date changes, move end date accordingly:
    $('#lzy-cal-start-date-' + this.inx).change(function (e) {
        const $startDate = $( this );
        const $endDate   = $('#lzy-cal-end-date-' + parent.inx);
        const newDateStr = $startDate.val();
        if (!newDateStr) {  // browser returns empty string if date impossible
            return;
        }
        // orig start datetime:
        const origStartDate = $startDate.attr('data-prev-val');
        $startDate.attr('data-prev-val', newDateStr);
        const origStart = moment( origStartDate );
        // orig end datetime:
        const origEnd = moment( $endDate.val() );
        // newStart:
        const newStart = moment( newDateStr );
        const duration = moment.duration( origEnd.diff(origStart) );
        const newEnd = newStart.add( duration );
        $endDate.val( newEnd.format('YYYY-MM-DD') );
    });

    // if start time changes, move end time accordingly:
    $('#lzy-cal-start-time-' + this.inx).change(function () {
        const $startDate = $('#lzy-cal-start-date-' + parent.inx);
        const $startTime = $( this );
        const $endDate   = $('#lzy-cal-end-date-' + parent.inx);
        const $endTime   = $('#lzy-cal-end-time-' + parent.inx);

        const newDateStr = $startDate.val();
        if (!newDateStr) {  // browser returns empty string if date impossible
            return;
        }
        // orig start datetime:
        const origStartTime = $startTime.attr('data-prev-val');
        const origStart = moment( newDateStr + ' ' + origStartTime );

        // new start datetime:
        const newStartTime = $startTime.val();
        const newStart = moment( newDateStr + ' ' + newStartTime );
        $startTime.attr('data-prev-val', newStartTime);

        // orig end datetime:
        const origEnd = moment( $endDate.val() + ' ' + $endTime.val() );

        // duration (=end-start):
        const duration = moment.duration( origEnd.diff(origStart) );

        // new end datetime (= new-start + duration):
        const newEnd = newStart.add( duration );

        // write new end back to UI:
        const newEndDateStr = newEnd.format('YYYY-MM-DD');
        $endDate.val( newEndDateStr );
        const newEndTimeStr = newEnd.format('HH:mm');
        $endTime.val( newEndTimeStr );
    });

    this.launchWindowFreeze();

}; // setupTriggers




LzyCalendar.prototype.launchWindowFreeze = function() {
    let parent = this;
    freezeWindowAfter('1 hour', function() {
        mylog('freezeWindowAfter...');
        lzyConfirm("{{ Reload page now? }}").then(function() {
            lzyReload();
        }, function() {
            mylog('retrigger freezeWindowAfter');
            parent.launchWindowFreeze();
        });
    });
}; // launchWindowFreeze




LzyCalendar.prototype.checkPermission = function( event ) {
    let creatorOnlyPermission = this.options.creatorOnlyPermission;
    if (creatorOnlyPermission) {
        try {
            let creator = event._def.extendedProps._creator;
            if (creatorOnlyPermission !== creator) {
                mylog(`Attempt to modify event created by other user ${creator} -> blocked.`);
                return false;
            }
        } catch(e){
        }
    }

    let calCatPermission = this.options.calCatPermission;
    if (calCatPermission) {
        try {
            let category = event._def.extendedProps.category.toLowerCase();
            if (calCatPermission.toLowerCase().indexOf(category) === -1) {
                mylog(`Attempt to modify event of unauthorized category ${category} -> blocked.`);
                return false;
            }
        } catch(e){
        }
    }
    return true;
}; // checkPermission




LzyCalendar.prototype.checkFreeze = function( event, checkAgainstEnd = false ) {
    let freeze = this.options.freezePast;
    if (!freeze) {
        return true;
    }

    let now = moment().add( moment().utcOffset() - 1, 'minutes');
    let time = null;
    let time1 = null;
    let time2 = null;
    if (typeof event.oldEvent !== 'undefined') {     // existing event
        if (checkAgainstEnd) {
            time1 = moment(event.event._instance.range.end);
            time2 = moment(event.oldEvent._instance.range.end);
        } else {
            time1 = moment(event.event._instance.range.start);
            time2 = moment(event.oldEvent._instance.range.start);
        }
        time = time1.isBefore(time2) ? time1: time2;

    } else if (typeof event.event !== 'undefined') {     // existing event
        if (checkAgainstEnd) {
            time = moment(event.event._instance.range.end);
        } else {
            time = moment(event.event._instance.range.start);
        }

    } else if (typeof event.date !== 'undefined') {         // new event
        time = moment(event.date);
    } else {
        return false;
    }
    let isAfter = time.isAfter( now );
    return isAfter;
}; // checkFreeze




LzyCalendar.prototype.openPopup = function() {
    this.$formWrapper.lzyPopup({
        contentRef: true,
        header: true,
        draggable: true,
        closeButton: true,
        closeOnBgClick: false,
    }); // open popup
}; // openPopup




LzyCalendar.prototype.closePopup = function() {
    lzyPopupClose( this.$formWrapper );
}; // closePopup




LzyCalendar.prototype.submitForm = function() {
    let parent = this;
    // prevent multiple calls (if user opend&closed popup multiple times):
    if (!this.doSubmit) {
        return;
    }
    this.doSubmit = false;

    // if all-day: make sure time is nulled:
    if ($('.lzy-cal-allday-event-checkbox', this.$form).prop('checked')) {
        $('#lzy-cal-start-time-' + this.inx).val('');
        $('#lzy-cal-end-time-' + this.inx).val('');
    }
    let postUrl = this.backendUrl + '&save';
    let formData = this.$form.serialize();

    if ( this.deleteNotSave ) {    // delete:
        postUrl = this.backendUrl + '&del';
        mylog('deleting entry', false);
    }

    $.ajax({
        url: postUrl,
        type: 'post',
        data: formData
    }).done(function (response) {
        lzyPopupClose();
        if (isServerErrMsg(response)) {
            if (!debug) {
                lzyReload();
            } else {
                lzyPopup( response.replace(/<(?:.|\n)*?>/gm, '') );
            }
            return;
        }
        if (response && response !== 'ok') {
            mylog(response, false);
            lzyPopup(response);
        }
        parent.fullCal.refetchEvents();
    });
}; // submitForm




LzyCalendar.prototype.resetForm = function() {
    $('option', this.$form).prop('selected', false);
    $('input[type=text]', this.$form).val('');
    $('.lzy-rec-id', this.$form).val('');
    $('textarea', this.$form).text('');
    $('input[type=checkbox],input[type=radio]', this.$form).prop('checked', false)
    $('.lzy-popup-header > div', this.$formWrapper).html( '-' );

    $('.lzy-inx', this.$form).val( this.inx );
    this.$form.attr('data-cal-inx', this.inx);
} // resetForm




LzyCalendar.prototype.preselectCategory = function() {
    let loggedinUser = calUser.toLocaleLowerCase();
    $('.lzy-cal-category option', this.$form).each(function() {
        let user = $(this).val().toLocaleLowerCase();
        if (loggedinUser === user) {
            $(this).attr('selected', 'selected');
            return false;
        }
    });
}; // preselectCategory




LzyCalendar.prototype.hideRestrictedCategories = function() {
    let cats = this.options.calCatPermission;
    if ( cats ) {
        cats = cats.toLocaleLowerCase();
        $('.lzy-cal-category option', this.$form).each(function() {
            let opt = $(this).val().toLocaleLowerCase();
            if (cats.indexOf(opt) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
}; // hideRestrictedCategories





LzyCalendar.prototype.onResize = function() {
    let $elem = this.$elem;
    let elemWidth = $('.fc-header-toolbar', $elem).width();
    mylog('Cal onResize elem width: '+ elemWidth +' header width: ' + this.titleWidth, false);

    if (elemWidth < this.titleWidth) {
        $('.fc-header-toolbar', $elem).addClass('lzy-cal-header-narrow');
    } else {
        $('.fc-header-toolbar', $elem).removeClass('lzy-cal-header-narrow');
    }
}; // onResize




(function( $ ){
    $.fn.lzyCalendar = function( options ) {
        let lzyCal = new LzyCalendar();
        lzyCalendar[ lzyCalendarInx++ ] = lzyCal;
        if (typeof options !== 'undefined') {
            options.inx = lzyCalendarInx;
        }
        lzyCal.init( $(this), options );
    };
})( jQuery );
