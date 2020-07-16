
var fcStdElems = ['allDay','className','end','i','source','start','title','_id','__proto__','event'];
var inx = 0;

$( document ).ready(function() {

    $('.lzy-calendar').each(function() {
        inx = $(this).attr('data-lzy-cal-inx');
        // var editingPermission = lzyCal[ inx ].editingPermission;
        // var addEventButton = '';
        // if (editingPermission) {
        //     addEventButton = ' addevent';
        // }
        if (typeof headerRightButtons === 'undefined') {
            headerRightButtons = 'timeGridWeek,dayGridMonth,listYear';
        }

        // === initialize FullCalendar ============================================
        var calDev = lzyCal[ inx ];
        var calendarEl = $( this );
        calendarEl = calendarEl[0];
        var calendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: calDev.headerLeftButtons,
                center: 'title',
                right: calDev.headerRightButtons
            },
            locale: calLang,
            timeZone: false,

            buttonText: calDev.buttonLabels,
            weekText: calDev.calLabels['weekText'], //'KW',
            allDayText: calDev.calLabels['allDayText'], // 'Ganztag',
            moreLinkText: calDev.calLabels['moreLinkText'], // 'mehr',
            noEventsText: calDev.calLabels['noEventsText'], // 'Leer',

            initialDate: calDev.initialDate,
            initialView: calDev.initialView,
            navLinks: true, // can click day/week names to navigate views
            height: 'auto',
            editable: calDev.editingPermission,
            dayMaxEvents: true, // allow "more" link when too many events
            businessHours: true, // display business hours
            selectable: true,
            nowIndicator: true,
            weekNumbers: true,
            weekNumberCalculation: 'ISO',
            slotMinTime: '08:00',
            slotMaxTime: '20:00',
            customButtons: {
                addEventButton: {
                    text: '＋',
                    click: function() {
                        alert('clicked the custom button!');
                    }
                }
            },

            events: {
                url: calBackend + '?inx=' + inx,
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
                    return 'lzy-cal-category-' + arg.event._def.extendedProps.category;
                }
            },

            viewDidMount: onViewReady,

            dateClick: function (e) {
                if (typeof openCalPopupHandler !== 'undefined') {
                    openCalPopupHandler(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                    setupTriggers();
                }
            },
            eventClick: function (e) {
                if (typeof openCalPopupHandler !== 'undefined') {
                    openCalPopupHandler(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                    setupTriggers();
                }
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
    var viewType = arg.view.type;
    if (lzyCal[ inx ].initialView !== viewType) {
        storeViewMode(inx, viewType);
        lzyCal[ inx ].initialView = viewType;
    }
    // activate Tooltips
    if (!lzyCal[ inx ].tooltips) {
        return;
    }
    setTimeout(function () {
        if (viewType === 'dayGridMonth') {
            $('.fc-daygrid-event').each(function () {
                var $elem = $(this);
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
                var $elem = $(this);
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




function renderEvent( arg ) {
    if (typeof customRenderEvent === 'function') {
        var res = customRenderEvent( arg );
        if (res) {
            return res;
        }
    }

    return defaultRenderEvent( arg );
} // renderEvent




function defaultRenderEvent( arg ) {
    var calDef = lzyCal[ inx ];

    // apply summary:
    let el = document.createElement('div');
    el.classList.add('lzy-cal-event-title');
    var lbl = 'summary';
    if (typeof calDef.fieldLabels.event !== 'undefined') {
        lbl = calDef.fieldLabels.event;
    }
    // if (typeof lzyCal[ inx ].fieldLabels.event !== 'undefined') {
    //     lbl = lzyCal[inx].fieldLabels.event;
    // }

    // apply  category:
    var category = '';
    if (typeof arg.event._def.extendedProps.category !== 'undefined') {
        category = arg.event._def.extendedProps.category;
    }

    // prepare time:
    let elT = document.createElement('div');
    var time = arg.timeText;
    elT.innerHTML = time;
    elT.classList.add('lzy-cal-time');

    // prepare prefix:
    var prefix = lzyCal[inx].catPrefixes[ category ];
    if (typeof prefix === 'undefined') {
        prefix = lzyCal[inx].catDefaultPrefix;
    }
    if (prefix) {
        prefix = '<span class="fc-title-prefix">' + prefix + '</span>';
    }

    // apply time, prefix and title:
    var title = convertMD(arg.event._def.title);
    el.innerHTML = '<span class="lzy-cal-title-label">'+ lbl +':</span> <span class="lzy-cal-title-text">' + prefix + title + '</span>';
    var elements = [ elT, el ];

    // apply custom properties:
    if (arg.event.extendedProps) {
        for ( var cust in arg.event._def.extendedProps) {
            if ((cust[0] === '_') || (cust === 'i') || (cust === 'event')) {  // skip meta elements
                continue;
            }
            var s = arg.event._def.extendedProps[cust];
            if (!s) {
                continue;
            }
            s = convertMD( s );
            let el1 = document.createElement('div');
            let cls = cust.toLowerCase().replace(/\W/, '-');
            el1.classList.add('lzy-cal-custom');
            el1.classList.add('lzy-cal-custom-' + cls);
            if (typeof lzyCal[ inx ].fieldLabels[cust] !== 'undefined') {
                cust = lzyCal[ inx ].fieldLabels[cust];
            } else {
                cust = cust[0].toUpperCase() + cust.substring(1);
            }
            el1.innerHTML = '<span class="lzy-cal-custom-label">' + cust + ':</span> <span class="lzy-cal-custom-text">' + s + '</span>';
            elements.push( el1 );
        }
    }
    return { domNodes: elements };
} // defaultRenderEvent




function calEventChanged(inx, event) {
    if (!lzyCal[ inx ].editingPermission) {
        return false;
    }
    var ev = event.event;
    if (ev.allDay) { // TODO: moving allDay events (possible it's not working due to a bug in fullcalendar)
        lzyReload();
        return;
    }
    var start = moment( ev._instance.range.start ).utc();
    var end = moment( ev._instance.range.end ).utc();
    var rec = {};
    rec['rec-id'] = ev._def.extendedProps.i;
    rec['title'] = ev._def.title;
    rec['start-date'] = start.format('YYYY-MM-DD');
    rec['start-time'] = start.format('HH:mm');
    rec['end-date'] = end.format('YYYY-MM-DD');
    rec['end-time'] = end.format('HH:mm');
    rec['allday'] = ev.allDay;

    for (var e in ev._def.extendedProps) {
        if (e === 'i') {
            continue;
        }
        rec[e] = ev._def.extendedProps[e];
    }

    var json = JSON.stringify(rec);

    $.ajax({
        url : calBackend + '?inx=' + inx + '&save',
        type: 'post',
        data : 'json='+json,
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // calEventChanged




function storeViewMode(inx, viewName) {
    $.ajax({
        url : calBackend + '?inx=' + inx + '&mode=' + viewName,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // storeViewMode





function saveCurrDate( arg ) {
    var dateStr = moment( this.currentData.currentDate ).utc().format('YYYY-MM-DD');
    $.ajax({
        url : calBackend + '?inx=' + inx + '&date=' + dateStr,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
    });
} // saveCurrDate





function defaultOpenCalPopup(inx, event0) {
    if (!lzyCal[ inx ].editingPermission) {
        return;
    }
    // reset selected options:
    $('#lzy_cal_category option').removeAttr('selected');

    var event = event0.event;
    if (typeof customOpenCalPopup === 'function') {
        var res = customOpenCalPopup( event0 );
        if (res) {
            return res;
        }
    }

    var options = {};
    options.anker = false;
    $('.lzy-cal-popup input[type=text]').val('');
    $('.lzy-cal-popup textarea').text('');

    $('#lzy-inx').val(inx);
    $('#lzy-calendar-default-form').attr('data-cal-inx', inx);

    var dateStr = '';
    var timeStr = '';
    var start = null;
    var end = null;
    var defaultEventDuration = lzyCal[ inx ].defaultEventDuration;

    if (typeof event === 'undefined') {                          // new entry
        $('#lzy-cal-new-event-header').show();
        $('#lzy-cal-modify-event-header').hide();

        start = moment( event0.date ).utc();
        dateStr = start.format('YYYY-MM-DD');
        if (defaultEventDuration === 'allday') {
            event0.allDay = true;
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

    } else {                                                   // existing entry
        $('#lzy-cal-new-event-header').hide();
        $('#lzy-cal-modify-event-header').show();

        start = moment( event._instance.range.start ).utc();
        end = moment( event._instance.range.end ).utc();
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

        var $startTime = $('#lzy_cal_start_time');
        var startTime = $startTime.val();
        $startTime.attr('data-prev-val', startTime);

        // populate custom fields:
        var extendedProps = event._def.extendedProps;
        if (typeof extendedProps !== 'undefined') {
            $('#lzy_cal_comment').text(extendedProps.comment);

            for (var fld in extendedProps) {
                if (fcStdElems.indexOf(fld) === -1) {
                    $('#lzy_cal_event_' + fld).val(extendedProps[fld]);
                }
            }

            // set selected option:
            if (extendedProps.category) {
                $('#lzy_cal_category option[value=' + extendedProps.category + ']').attr('selected', 'selected');
            }
            $('#lzy-rec-id').val(extendedProps.i);
        }


        $('#lzy_cal_delete_entry').show();

        $('#lzy_cal_category').change(function(ev) {
            var cat = 'lzy-cal-category-' + $( ':selected', $(this) ).val();
            // var cat = $( 'option[selected]', $(this) ).val();
            $(this).closest('form').attr('class', '').addClass(cat);
        });

        $('#lzy-cal-delete-entry-checkbox').change(function(ev) {
            ev.preventDefault();
            if (!$('#lzy-cal-delete-entry-checkbox').prop('checked')) {
                $('#lzy_btn_delete_entry').hide();
                $('#lzy-calendar-default-submit').prop('disabled', false).removeClass('lzy-cal-inactive');

            } else {
                $('#lzy_btn_delete_entry').show();
                $('#lzy-calendar-default-submit').prop('disabled', true).addClass('lzy-cal-inactive');
            }
        });
    }

    $(".lzy-cal-popup").popup('show');      // open popup
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
    var m = text.match(/(.*)\[(.+?)\]\((.+?)\)(.*)/);
    if ( m ) {
        var href = m[3];
        if (!href.match(/^https?/)) {
            href = 'https://' + href;
        }
        text = m[1] + '<a href="'+href+'" class="lzy-cal-link" target="_blank">'+m[2]+'</a>' + m[4];
    }
    return text;
} // convertMD





function setupTriggers() {
    $('#lzy-calendar-default-form').submit(function (event) {    // submit button
        event.preventDefault();
        var $this = $(this);

        var post_url = $this.attr("action") + '?save';
        var request_method = $this.attr("method");
        var form_data = $this.serialize();

        // console.log( form_data );
        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function (response) {
            if (isServerErrMsg(response)) {
                return;
            }
            if (response && response != 'ok') {
                console.log(response);
                alert(response);
            }
            lzyReload();
        });
    });

    $('#lzy-cal-allday-event-checkbox').change(function (e) {
        var $this = $( this );
        var allday = $this.prop('checked');
        if (allday) {
            // console.log('allday on');
            $('#lzy-allday').val('true');
            $('#lzy_cal_start_time').attr('type', 'hidden');
            $('#lzy_cal_end_time').attr('type', 'hidden');
        } else {
            // console.log('allday off');
            $('#lzy-allday').val('false');
            $('#lzy_cal_start_time').attr('type', 'time');
            $('#lzy_cal_end_time').attr('type', 'time');
        }
    });

    $("#lzy-calendar-default-form input[type=reset]").click(function () {
        $(".lzy-cal-popup").popup('hide');  // close popup
    });


    $("#lzy_cal_delete_entry input[type=button]").click(function () {
        var $form = $(this).closest('form');
        var post_url = $form.attr("action") + '?del';
        var request_method = $form.attr("method");
        var form_data = $form.serialize();
        // console.log('delete entry');
        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function (response) {
            if (isServerErrMsg(response)) {
                return;
            }
            if (response && response != 'ok') {
                console.log(response);
                alert(response);
            }
            lzyReload();
        });
    });

    // if start date changes, move end date accordingly:
    $('#lzy_cal_start_date').change(function () {
        var $this = $( this );
        var newDateStr = $this.val();
        var newT = moment( newDateStr + ' ' + $('#lzy_cal_start_time').val() );
        var prevT = moment( $this.attr('data-prev-val') + ' ' + $('#lzy_cal_start_time').val() );
        var dT = moment.duration(newT.diff(prevT)).subtract(1, 's');
        var endT = moment( $('#lzy_cal_end_date').val() + ' ' + $('#lzy_cal_end_time').val() );
        endT = endT.add( dT );
        var newEndDateStr = endT.format('YYYY-MM-DD');
        $('#lzy_cal_end_date').val(newEndDateStr);
        $this.attr('data-prev-val', newDateStr);
    });

    // if start time changes, move end time accordingly:
    $('#lzy_cal_start_time').change(function () {
        var $this = $( this );
        var newTimeStr = $this.val();
        var newT = moment( $('#lzy_cal_start_date').val() + ' ' + newTimeStr );
        var prevT = moment( $('#lzy_cal_start_date').val() + ' ' + $this.attr('data-prev-val') );
        var dT = moment.duration(newT.diff(prevT));
        var endT = moment( $('#lzy_cal_end_date').val() + ' ' + $('#lzy_cal_end_time').val() );
        endT = endT.add( dT );
        var newEndTimeStr = endT.format('HH:mm');
        $('#lzy_cal_end_time').val(newEndTimeStr);
        $this.attr('data-prev-val', newTimeStr);
    });
} // setupTriggers
