/*
        calendar.js

        Custom callback functions:
            openCalPopupHandler( inx, event, isNewEvent )
            openPostCalPopupHandler( inx, event, isNewEvent )
            customOpenNewCalPopup( event )
            customOpenCalPopup( event )
 */


var fcStdElems = ['allDay','className','end','i','source','start','title','_id','__proto__','event'];
var inx = 0;
var deleteNotSubmit = false;

$( document ).ready(function() {

    $('.lzy-calendar').each(function() {
        inx = $(this).attr('data-lzy-cal-inx');

        if (typeof headerRightButtons === 'undefined') {
            headerRightButtons = 'timeGridWeek,dayGridMonth,listYear';
        }

        // === initialize FullCalendar ============================================
        let calDev = lzyCal[ inx ];
        let calendarEl = $( this );
        calendarEl = calendarEl[0];
        let calendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: calDev.headerLeftButtons,
                center: 'title',
                right: calDev.headerRightButtons
            },
            locale: calLang,
            timeZone: false,

            buttonText: calDev.buttonLabels,
            weekText: calDev.calLabels['weekText'],
            allDayText: calDev.calLabels['allDayText'],
            moreLinkText: calDev.calLabels['moreLinkText'],
            noEventsText: calDev.calLabels['noEventsText'],

            initialDate: calDev.initialDate,
            initialView: calDev.initialView,
            navLinks: true, // can click day/week names to navigate views
            height: 'auto',
            editable: calDev.editingPermission,
            dayMaxEvents: true, // allow "more" link when too many events
            businessHours: true, // display business hours
            selectable: true,
            eventOverlap: calDev.eventOverlap,
            nowIndicator: true,
            weekNumbers: true,
            weekNumberCalculation: 'ISO',
            slotMinTime: calDev.calDayStart,
            slotMaxTime: calDev.calDayEnd,
            customButtons: {
                addEventButton: {
                    text: '＋',
                    click: function() {
                        alert('clicked the custom button!');
                    }
                }
            },

            events: {
                url: calBackend + '?inx=' + inx + '&ds=' + calDev.ref,
                success: saveCurrDate,
                failure: function( events ) {
                    console.log('Error in calendar data:');
                    console.log( events );
                }
            },

           eventContent: renderEvent,

            // apply category specific classes:
            eventClassNames: function(arg) {
                if (typeof arg.event._def.extendedProps.category !== 'undefined') {
                    const catName = arg.event._def.extendedProps.category;
                    const catInx = lzyCal[ inx ].categories.indexOf( catName ) + 1;
                    return 'lzy-cal-category-' + catInx + ' lzy-cal-category-' + catName.replace(/\s+/, '-').toLowerCase();
                }
            },

            viewDidMount: onViewReady,

            dateClick: function (e) {
                openCalPopup(inx, e, true);
            },
            eventClick: function (e) {
                openCalPopup(inx, e, false);
            },
            eventDrop: function (e) {
                return calEventChanged(inx, e);
            },
            eventResize: function (e) {
                calEventChanged(inx, e);
            },
        });
        calendar.render();
    });

}); // ready




function onViewReady( arg ) {
    // view changed, so update state on host:
    let viewType = arg.view.type;
    if (lzyCal[inx].initialView !== viewType) {
        storeViewMode(inx, viewType);
        lzyCal[inx].initialView = viewType;
    }
} // onViewReady




function renderEvent( arg ) {
    if (typeof customRenderEvent === 'function') {
        let res = customRenderEvent( arg );
        if (res) {
            return res;
        }
    }

    return defaultRenderEvent( arg );
} // renderEvent




function defaultRenderEvent( arg ) {
    let calDef = lzyCal[ inx ];

    // apply summary:
    let el = document.createElement('div');
    el.classList.add('lzy-cal-event-title');
    let lbl = 'summary';
    if (typeof calDef.fieldLabels.event !== 'undefined') {
        lbl = calDef.fieldLabels.event;
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
        let t = start.format('HH:mm') + ' - ' + end.format('HH:mm');
        elT.innerHTML = t;
    }
    elT.classList.add('lzy-cal-time');

    // prepare prefix:
    let prefix = lzyCal[inx].catPrefixes[ category ];
    if (!prefix || (typeof prefix === 'undefined')) {
        prefix = lzyCal[inx].catDefaultPrefix;
    }
    if (prefix) {
        prefix = '<span class="fc-title-prefix">' + prefix + '</span>';
    }

    // apply time, prefix and title:
    let title = convertMD(arg.event._def.title);
    el.innerHTML = '<span class="lzy-cal-title-label">'+ lbl +':</span> <span class="lzy-cal-title-text">' + prefix + ' <span>' + title + '</span></span>';
    let elements = [ elT, el ];

    mylog('defaultRenderEvent: ' + title, false);

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
            s = convertMD( s );
            let el1 = document.createElement('div');
            let cls = cust.toLowerCase().replace(/\W/, '-');
            el1.classList.add('lzy-cal-custom');
            el1.classList.add('lzy-cal-custom-' + cls);
            if (typeof calDef.fieldLabels[cust] !== 'undefined') {
                cust = calDef.fieldLabels[cust];
            } else {
                cust = cust[0].toUpperCase() + cust.substring(1);
            }
            el1.innerHTML = '<span class="lzy-cal-custom-label">' + cust + ':</span> <span class="lzy-cal-custom-text">' + s + '</span>';
            elements.push( el1 );
        }
    }

    activateTooltips( arg.view.type );

    return { domNodes: elements };
} // defaultRenderEvent




function calEventChanged(inx, event0) {
    if (!lzyCal[ inx ].editingPermission) {
        return false;
    }
    let event = event0.event;
    if (!checkPermission( event )) {
        alert('You don\'t have permission to modify somebody else\'s event');
        event0.revert();
        return false;
    }
    if (!checkFreeze( event0 )) {
        alert('You can\'t create or modify events in the past');
        event0.revert();
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

    $.ajax({
        url : calBackend + '?inx=' + inx + '&save' + '&ds=' + lzyCal[inx].ref,
        type: 'post',
        data : 'json='+json,
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // calEventChanged



function activateTooltips( viewType ) {
    // activate Tooltips
    if (!lzyCal[ inx ].tooltips) {
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

} // onViewReady




function storeViewMode(inx, viewName) {
    $.ajax({
        url : calBackend + '?inx=' + inx + '&mode=' + viewName + '&ds=' + lzyCal[inx].ref,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // storeViewMode





function saveCurrDate( arg ) {
    let dateStr = moment( this.currentData.currentDate ).utc().format('YYYY-MM-DD');
    $.ajax({
        url : calBackend + '?inx=' + inx + '&date=' + dateStr + '&ds=' + lzyCal[inx].ref,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // saveCurrDate




function openCalPopup(inx, event, isNewEvent) {
    let runDefault = true;
    if (typeof openCalPopupHandler !== 'undefined') {
        runDefault = openCalPopupHandler(inx, event, isNewEvent);
    }
    if (runDefault) {
        defaultOpenCalPopup(inx, event, isNewEvent);
        setupTriggers();
    }
    if (typeof openPostCalPopupHandler !== 'undefined') {
        openPostCalPopupHandler(inx, event, isNewEvent);
    }
} // openCalPopup




function defaultOpenCalPopup(inx, event0, isNewEvent) {
    let thisCal = lzyCal[ inx ];
    if (!thisCal.editingPermission) {
        return;
    }
    let catClass = '';
    let event = event0.event;
    if (!checkPermission( event )) {
        alert('You don\'t have permission to modify somebody else\'s event');
        event0.revert();
        return;
    }

    if (!checkFreeze( event0 )) {
        alert('You can\'t create or modify events in the past');
        if (typeof event0.revert === 'function') {
            event0.revert();
        }
        return;
    }

    // reset selected options:
    $('#lzy_cal_category option').removeAttr('selected');
    $('#lzy-calendar-default-form').attr( 'class', '' );


    let options = {};
    options.anker = false;
    $('.lzy-cal-popup input[type=text]').val('');
    $('.lzy-cal-popup textarea').text('');

    $('#lzy-inx').val(inx);
    $('#lzy-calendar-default-form').attr('data-cal-inx', inx);

    let dateStr = '';
    let timeStr = '';
    let start = null;
    let end = null;
    let res = false;
    let defaultEventDuration = thisCal.defaultEventDuration;
    let header = '';

    applyCatRestrictions();

    if ( isNewEvent ) {                                       // new entry
        if (typeof customOpenNewCalPopup === 'function') {
            res = customOpenNewCalPopup( event0 );
            if (res) {
                return res;
            }
        }
        header = $('#lzy-cal-new-event-header').html();

        start = moment( event0.date ).utc();
        dateStr = start.format('YYYY-MM-DD');

        if (defaultEventDuration === 'allday') {
            event0.allDay = true;

        } else if (event0.view.type === 'dayGridMonth') {
            // for month view: avoid allDay event as default, unless no defaultEventDuration is defined:
            if (defaultEventDuration) {
                event0.allDay = false;
            }
            start = moment(dateStr + ' ' + thisCal.calDayStart);
        }
        if (event0.allDay === false) {
            $('#lzy_cal_start_date').val(dateStr).attr('data-prev-val', dateStr);
            timeStr = start.format('HH:mm');
            $('#lzy_cal_start_time').val(timeStr).attr('data-prev-val', timeStr);

            $('#lzy_cal_end_date').val(dateStr);
            timeStr = start.add(defaultEventDuration, 'minutes').format('HH:mm');
            $('#lzy_cal_end_time').val(timeStr);

            $('#lzy_cal_event_name').val('');
            $('#lzy_cal_event_location').val('');
            $('#lzy_cal_comment').text('');

        } else {    // case whole day event:
            $('#lzy_cal_start_date').val(dateStr).attr('data-prev-val', dateStr);
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('09:00').attr('data-prev-val', '09:00');
            $('#lzy-allday').val('true');
            $('#lzy-cal-allday-event-checkbox').prop('checked', true);
            $('#lzy_cal_end_time').attr('type', 'hidden').val('11:00');
        }

        let cat = thisCal.calCatPermission;
        if ( cat ) {
            if (cat.indexOf(',') !== -1) {
                let cats = cat.replace(' ', '').split(',');
                cat = cats[0];
            }
            cat = cat.toLocaleLowerCase();
            $('#lzy_cal_category option').each(function() {
                if ($(this).val().toLocaleLowerCase() === cat) {
                    $(this).attr('selected', 'selected');
                }
            });
            // attr('selected', 'selected');
        }


    } else {                                                   // existing entry
        if (typeof customOpenCalPopup === 'function') {
            res = customOpenCalPopup( event0 );
            if (res) {
                return res;
            }
        }
        header = $('#lzy-cal-modify-event-header').html();

        start = moment( event._instance.range.start ).utc();
        let tsDiff = moment( event._instance.range.start ).utcOffset() + 1; // timezone offset -> subtract from end
        end = moment( event._instance.range.end ).subtract(tsDiff, 'minutes');
        dateStr = start.format('YYYY-MM-DD');
        $('#lzy_cal_start_date').val(dateStr).attr('data-prev-val', dateStr);
        if (event.allDay === false) {    // normal event
            timeStr = start.format('HH:mm');
            $('#lzy_cal_start_time').val(timeStr).attr('data-prev-val', timeStr);
            $('#lzy-allday').val('false');
            dateStr = end.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            timeStr = end.format('HH:mm');
            $('#lzy_cal_end_time').val(timeStr);

        } else {    // allday event:
            $('#lzy_cal_start_date').val(dateStr);
            dateStr = end.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('00:00');
            $('#lzy_cal_end_time').attr('type', 'hidden').val('00:00');
            $('#lzy-allday').val('true');
            $('#lzy-cal-allday-event-checkbox').prop('checked', true);
        }

        $('#lzy_cal_event_name').val(event._def.title);

        let $startTime = $('#lzy_cal_start_time');
        let startTime = $startTime.val();
        $startTime.attr('data-prev-val', startTime);

        // populate custom fields:
        let extendedProps = event._def.extendedProps;
        if (typeof extendedProps !== 'undefined') {
            $('#lzy_cal_comment').text(extendedProps.comment);

            for (let fld in extendedProps) {
                if (fcStdElems.indexOf(fld) === -1) {
                    $('#lzy_cal_event_' + fld).val(extendedProps[fld]);
                }
            }

            // set selected option:
            if (extendedProps.category) {
                $('#lzy_cal_category option[value=' + extendedProps.category + ']').attr('selected', 'selected');
                catClass = extendedProps.category.replace(/\s+/g,'-').toLowerCase().replace(/[^a-z0-9-_]+/g,'');
                $('#lzy-calendar-default-form').addClass( 'lzy-cal-category-' +  catClass );
            }
            $('#lzy-rec-id').val(extendedProps.i);
        }


        $('#lzy_cal_delete_entry').show();

        // Delete Entry checkbox:
        $('#lzy-cal-delete-entry-checkbox').change(function(event) {
            event.preventDefault();
            if ($('#lzy-cal-delete-entry-checkbox').prop('checked')) {
                deleteNotSubmit = true;
                $('#lzy-calendar-default-submit').val( calSubmitBtnLabel[1] );
            } else {
                deleteNotSubmit = false;
                $('#lzy-calendar-default-submit').val( calSubmitBtnLabel[0] );
            }
        });
    }

    // set form class if category changes:
    $('#lzy_cal_category').change(function() {
        catClass = $( ':selected', $(this) ).val().toLowerCase();
        $(this).closest('form').attr('class', '').addClass( 'lzy-cal-category-' + catClass );
    });

    $('#lzy-cal-popup-template-' + inx).lzyPopup({
        contentRef: true,
        header: true,
        draggable: true,
        closeButton: true,
        closeOnBgClick: false,
    }); // open popup

    // update header:
    $('#lzy-cal-popup-template-' + inx + ' .lzy-popup-header').html( header );
    setTimeout( function() { $('#lzy_cal_event_name').focus(); }, 500);

} // defaultOpenCalPopup




function convertMD(text) {
    if (!text) {
        return text;
    }
    // lookbehind -> not working in js pre V8:
    // text = text.replace(/(?<!\\)\\n/, '<br>');  // new-line
    // text = text.replace(/(?<!\\)\*\*(.+?)\*\*/, '<strong>$1</strong>'); // **strong**
    // text = text.replace(/(?<!\\)\*(.+?)\*/, '<em>$1</em>'); // *em**
    // text = text.replace(/\\\\/, '\\'); // \\ -> \

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
} // convertMD




var doSubmit = true;
function setupTriggers() {
    // submit button:
    $('#lzy-calendar-default-form').submit(function (event) {
        event.preventDefault();

        // prevent multiple calls (if user opend&closed popup multiple times):
        if (!doSubmit) {
            return;
        }
        doSubmit = false;
        let $this = $(this);

        let postUrl = $this.attr('action') + '?save' + '&ds=' + lzyCal[inx].ref;
        let requestMethod = $this.attr('method');
        let formData = $this.serialize();

        if ( deleteNotSubmit ) {    // delete:
            postUrl = $this.attr('action') + '?del' + '&ds=' + lzyCal[inx].ref;
            console.log('deleting entry');
        }

        $.ajax({
            url: postUrl,
            type: requestMethod,
            data: formData
        }).done(function (response) {
            if (isServerErrMsg(response)) {
                return;
            }
            if (response && response !== 'ok') {
                console.log(response);
                alert(response);
            }
            lzyReload();
        });
    });

    // All-day toggle:
    $('#lzy-cal-allday-event-checkbox').change(function () {
        let $this = $( this );
        let allday = $this.prop('checked');
        if (allday) {
            $('#lzy-allday').val('true');
            $('#lzy_cal_start_time').attr('type', 'hidden');
            $('#lzy_cal_end_time').attr('type', 'hidden');
        } else {
            $('#lzy-allday').val('false');
            $('#lzy_cal_start_time').attr('type', 'time');
            $('#lzy_cal_end_time').attr('type', 'time');
        }
    });

    // Cancel button:
    $("#lzy-calendar-default-form input[type=reset]").click(function () {
        $("#lzy-cal-popup-template").lzyPopup('hide');  // close popup
    });


    // if start date changes, move end date accordingly:
    $('#lzy_cal_start_date').change(function () {
        let $this = $( this );
        let newDateStr = $this.val();
        newDateStr = newDateStr? newDateStr: '2000-01-01'; // avoid empty string
        let newTimeStr = $('#lzy_cal_start_time').val();
        newTimeStr = newTimeStr? newTimeStr: '08:00';
        let newT = moment( newDateStr + ' ' + newTimeStr );
        let prevT = moment( $this.attr('data-prev-val') + ' ' + $('#lzy_cal_start_time').val() );
        let dT = moment.duration(newT.diff(prevT)).subtract(1, 's');
        let endT = moment( $('#lzy_cal_end_date').val() + ' ' + $('#lzy_cal_end_time').val() );
        endT = endT.add( dT );
        let newEndDateStr = endT.format('YYYY-MM-DD');
        $('#lzy_cal_end_date').val(newEndDateStr);
        $this.attr('data-prev-val', newDateStr);
    });

    // if start time changes, move end time accordingly:
    $('#lzy_cal_start_time').change(function () {
        let $this = $( this );
        let newDateStr = $('#lzy_cal_start_date').val();
        newDateStr = newDateStr? newDateStr: '2000-01-01'; // avoid empty string
        let newTimeStr = $this.val();
        newTimeStr = newTimeStr? newTimeStr: '08:00';
        let newT = moment( newDateStr + ' ' + newTimeStr );
        let prevT = moment( newDateStr + ' ' + $this.attr('data-prev-val') );
        let dT = moment.duration(newT.diff(prevT));
        let endT = moment( $('#lzy_cal_end_date').val() + ' ' + $('#lzy_cal_end_time').val() );
        endT = endT.add( dT );
        let newEndTimeStr = endT.format('HH:mm');
        $('#lzy_cal_end_time').val(newEndTimeStr);
        $this.attr('data-prev-val', newTimeStr);
    });

    $('.lzy-calendar').resize(function (){
        let $cal = $(this);
        let w1 = $('.fc-header-toolbar', $cal).width();
        let w2 = $('.fc-header-toolbar > div:first-child', $cal).width();
        let w3 = $('.fc-header-toolbar > div:last-child', $cal).width();
        let w4 = $('.fc-header-toolbar > div:nth-child(2)', $cal).width();
        if (true) {
            $('.fc-header-toolbar', $cal).addClass('lzy-cal-header-narrow');
        }
    });
} // setupTriggers




function checkPermission(event) {
    let creatorOnlyPermission = lzyCal[ inx ].creatorOnlyPermission;
    if (creatorOnlyPermission) {
        try {
            let creator = event._def.extendedProps._creator;
            if (creatorOnlyPermission !== creator) {
                console.log(`Attempt to modify event created by other user ${creator} -> blocked.`);
                return false;
            }
        } catch(e){
        }
    }

    let calCatPermission = lzyCal[ inx ].calCatPermission;
    if (calCatPermission) {
        try {
            let category = event._def.extendedProps.category.toLowerCase();
            if (calCatPermission.toLowerCase().indexOf(category) === -1) {
                console.log(`Attempt to modify event of unauthorized category ${category} -> blocked.`);
                return false;
            }
        } catch(e){
        }
    }
    return true;
} // checkPermission



function checkFreeze(event) {
    let freeze = lzyCal[ inx ].freezePast;
    if (!freeze) {
        return true;
    }
// let start = null;
    let end = null;
    if (typeof event.date !== 'undefined') {
// start = moment(event.date).utc();
        end = moment(event.date);
// end = moment(event.date).local();
// end = moment(event.date).utc();

    } else if (typeof event.event._instance.range.start !== 'undefined') {
// start = moment( event.event._instance.range.start ).utc();
        end = moment( event.event._instance.range.end ).utc();

    } else {
        return false;
    }

// let UTC = moment.utc();
// let local = moment(UTC).local();
// let ts = moment().format('Z');
// let ts2 = moment().format('ZZ');

    let now = moment().utc();
// let isAfter = start.isAfter( now );
    let isAfter = end.isAfter( now );
    return isAfter;
} // checkFreeze




function applyCatRestrictions() {
    if (lzyCal[ inx ].calCatPermission) {
        $('#lzy_cal_category option').hide();
        let cats = lzyCal[ inx ].calCatPermission.split(',');
        for (let i in cats) {
            let cat = cats[i].trim();
            $('#lzy_cal_category option[value=' + cat + ']').show();
        }
    }
} // applyCatRestrictions
