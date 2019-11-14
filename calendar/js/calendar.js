
var fcStdElems = ['allDay','className','end','i','source','start','title','_id','__proto__'];
var inx = 0;

$( document ).ready(function() {

    $('.lzy-calendar').each(function() {
        inx = $(this).attr('data-lzy-cal-inx');
        var defaultDate = $(this).attr('data-lzy-cal-start');

        $( this ).fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'agendaWeek,month,listYear',   // further options: listWeek, listMonth, listYear
            },
            navLinks: true,       // can click day/week names to navigate views
            editable: lzyCal[ inx ].calEditingPermission,
            eventLimit: true,     // allow "more" link when too many events
            firstDay: 1,
            locale: calLang,
            weekNumbers: true,
            defaultDate: defaultDate,
            defaultView: lzyCal[ inx ].calDefaultView,

            events: {
                url: calBackend + '?inx=' + inx,
                error: function () {
                    $('#script-warning').show();
                }
            },
            loading: function (bool) {
                $('#loading').toggle(bool);
            },
            viewRender: function(view) {
                storeViewMode(inx, view);
                if (typeof customRenderView === 'function') {
                    customRenderView(view);
                }
            },
            dayClick: function (e) {
                if (typeof openCalPopup !== 'undefined') {
                    openCalPopup(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                    setupTriggers();
                }
            },
            eventClick: function (e) {
                if (typeof openCalPopup !== 'undefined') {
                    openCalPopup(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                    setupTriggers();
                }
            },
            eventDrop: function (e) {
                calEventChanged(inx, e);
            },
            eventResize: function (e) {
                calEventChanged(inx, e);
            },
            eventRender: renderEvent,
            eventAfterAllRender: function() {
                // in list-view: add td in header-row to balance custom fc-custom-field-wrapper
                if ($('.fc-list-view').length) {
                    $('.fc-list-heading').append('<td class="fc-widget-header2"></td>');
                }
            }
        });
    });
}); // ready


function renderEvent(event, element)
{
    if (typeof customRenderEvent === 'function') {
        customRenderEvent(event, element);
    } else {
        defaultRenderEvent(event, element);
    }
}



function defaultRenderEvent(event, element) {

    var $elem = $(element);

    // if special field category present -> apply it as class to event wrapper:
    if (typeof event.category !== 'undefined') {
        $elem.addClass('lzy-event-' + event.category);
    }

    // create wrapper for custom fields:
    if ($('.fc-list-view').length) {    // td for list-view:
        $elem.append('<td class="fc-custom-field-wrapper"></td>');
    } else {
        $('.fc-content', $elem).append('<div class="fc-custom-field-wrapper"></div>');

    }
    var $wrapper = $('.fc-custom-field-wrapper', $elem);


    var flabel = lzyCal[inx].fieldLabels.title;
    var prefix = lzyCal[inx].catPrefixes[ event.category ];
    if (typeof prefix === 'undefined') {
        prefix = lzyCal[inx].catDefaultPrefix;
    }
    prefix = '<span class="fc-title-prefix">' + prefix + '</span>';

    // md-compile title:
    var title = '';
    if ($('#lzy-calendar' + inx + ' .fc-list-view').length) {
        title = $('.fc-list-item-title a', $elem).text();
        title = '<span class="fc-field-label">'+flabel+':</span><span class="fc-field-value">' + prefix + convertMD(title) + '</span>';
        $('.fc-list-item-title a', $elem).html(title);

    } else {
        title = $('.fc-title', $elem).text();
        title = '<span class="fc-field-label">'+flabel+':</span><span class="fc-field-value">' + prefix + convertMD(title) + '</span>';
        $('.fc-title', $elem).html(title);
    }

    flabel = lzyCal[inx].fieldLabels.time;
    if (typeof flabel !== 'undefined') {
        var date = $('.fc-time', $elem).text();
        date = '<span class="fc-field-label">'+flabel+':</span><span class="fc-field-value">' + date + '</span>';
        $('.fc-time', $elem).html(date);
    }

    // populate custom fields:
    for (var fld in event) {
        if (fcStdElems.indexOf(fld) === -1) {
            // exclude system-fields starting with '_'
            if (fld.charAt(0) === '_') {
                continue;
            }
            flabel = lzyCal[ inx ].fieldLabels[fld];
            if (typeof flabel === 'undefined') {
                flabel = fld;
            }
            var text = event[fld];
            // exlcude empty fields:
            if (text) {
                text = convertMD(text);
                $wrapper.append('<div class="fc-event-field fc-' + fld + '"><span class="fc-field-label">' + flabel + ': </span><span class="fc-field-value">' + text + '</span></div>');
            }
        }
    }
    $elem.qtip({
        content: $('.fc-content', $elem).html(),
        position: {
            my: 'bottom center',
            at: 'top center',
            target: $elem
        },
        hide: {
            fixed: true,
            delay: 500
        }
        // for debugging/styling tooltips:
        // show: 'click',
        // hide: 'click',
    });
} // defaultRenderEvent



function convertMD(text)
{
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
    text = text.replace(/^\*(.+?)\*/, '<em>$1</em>'); // *em**
    text = text.replace(/([^\\*])\*(.+?)\*/, '$1<em>$2</em>'); // *em**
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




function calEventChanged(inx, event)
{
    if (!lzyCal[ inx ].calEditingPermission) {
        return;
    }
    if (event.allDay) { // TODO: moving allDay events (possible it's not working due to a bug in fullcalendar)
        lzyReload();
        return;
    }
    var start = moment(event.start._i);
    var end = moment(event.end._i);
    var rec = {};
    rec['rec-id'] = event.i;
    rec['title'] = event.title;
    rec['start-date'] = start.format('YYYY-MM-DD');
    rec['start-time'] = start.format('HH:mm');
    rec['end-date'] = end.format('YYYY-MM-DD');
    rec['end-time'] = end.format('HH:mm');
    rec['allday'] = event.allDay;

    for (var ev in event) {
        if ((fcStdElems.indexOf(ev) === -1) && (typeof event[ev] !== 'undefined')) {
            rec[ev] = event[ev];
        }
    }

    var json = JSON.stringify(rec);

    //console.log('calEventChanged: ' + json);
    $.ajax({
        url : calBackend + '?inx=' + inx + '&save',
        type: 'post',
        data : 'json='+json,
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
        if (response && response !== 'ok') { console.log(response); }
    });
} // calEventChanged




function storeViewMode(inx, view) {
    var viewName = view.name;
    console.log('save view mode: ' + viewName);
    $.ajax({
        url : calBackend + '?inx=' + inx + '&mode=' + viewName,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
        if (response && response !== 'ok') { console.log(response); }
    });
} // storeViewMode





function defaultOpenCalPopup(inx, event) {
    if (!lzyCal[ inx ].calEditingPermission) {
        return;
    }
    var options = {};
    options.anker = false;
    $('.lzy-cal-popup input[type=text]').val('');
    $('.lzy-cal-popup textarea').text('');

    $('#lzy-inx').val(inx);
    $('#lzy-calendar-default-form').attr('data-cal-inx', inx);

    if (typeof event._i != 'undefined') {                          // new entry
        $('#lzy-cal-new-event-header').show();
        $('#lzy-cal-modify-event-header').hide();
        var date = event._i;    // case: new entry
        var dateStr = '';
        var timeStr = '';
        if (typeof date !== 'number') {
            var d = moment(date);
            d.local();
            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            timeStr = d.format('HH:mm');
            $('#lzy-calendar-default-form input[name=start-time]').val(timeStr);

            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            timeStr = d.add(1, 'hours').format('HH:mm');
            $('#lzy-calendar-default-form input[name=end-time]').val(timeStr);

            $('#lzy_cal_event_name').val('');
            $('#lzy_cal_event_location').val('');
            $('#lzy_cal_comment').text('');

        } else {    // case whole day event:
            dateStr = moment(date).format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('');
            $('#lzy_cal_end_time').attr('type', 'hidden').val('');
            $('#lzy-allday').val('true');
        }

    } else {                                                   // existing entry
        $('#lzy-cal-new-event-header').hide();
        $('#lzy-cal-modify-event-header').show();
        var d = moment( event.start._i );
        d.utc();
        var dateStr = d.format('YYYY-MM-DD');
        $('#lzy_cal_start_date').val(dateStr);
        if (event.allDay == false) {    // normal event
            var timeStr = d.format('HH:mm');
            $('#lzy_cal_start_time').val(timeStr);
            $('#lzy-allday').val('false');

            d = moment(event.end._i);
            d.utc();
            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            timeStr = d.format('HH:mm');
            $('#lzy_cal_end_time').val(timeStr);

        } else {    // allday event:
            var d = moment( event.start._i );
            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            d = moment(event.end._i);
            dateStr = d.subtract({ minutes: 1}).format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('');
            $('#lzy_cal_end_time').attr('type', 'hidden').val('');
            $('#lzy-allday').val('true');
        }

        $('#lzy_cal_event_name').val(event.title);
        $('#lzy_cal_comment').text(event.comment);

        // populate custom fields:
        for (var fld in event) {
            if (fcStdElems.indexOf(fld) === -1) {
                $('#lzy_cal_event_'+fld).val(event[fld]);
            }
        }

        // reset selected options and then select anew:
        $('#lzy_cal_category option').removeAttr('selected');
        if (event.category) {
            $('#lzy_cal_category option[value=' + event.category + ']').attr('selected', 'selected');
        }
        $('#lzy-rec-id').val(event.i);

        $('#lzy_cal_delete_entry').show();

        $('#lzy_cal_category').change(function(event) {
            var cat = 'lzy-cal-category-' + $( ':selected', $(this) ).val();
            // var cat = $( 'option[selected]', $(this) ).val();
            $(this).closest('form').attr('class', '').addClass(cat);
        });

        $('#lzy-cal-delete-entry-checkbox').change(function(event) {
            event.preventDefault();
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




function setupTriggers() {
    $('#lzy-calendar-default-form').submit(function (event) {    // submit button
        event.preventDefault();
        var $this = $(this);

        var post_url = $this.attr("action") + '?save';
        var request_method = $this.attr("method");
        var form_data = $this.serialize();

        console.log( form_data );
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


    $("#lzy-calendar-default-form input[type=reset]").click(function () {
        $(".lzy-cal-popup").popup('hide');  // close popup
    });


    $("#lzy_cal_delete_entry input[type=button]").click(function () {
        var $form = $(this).closest('form');
        var post_url = $form.attr("action") + '?del';
        var request_method = $form.attr("method");
        var form_data = $form.serialize();
        console.log('delete entry');
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
} // setupTriggers
