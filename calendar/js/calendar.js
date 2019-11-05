

$( document ).ready(function() {

    $('.lzy-calendar').each(function() {
        var inx = $(this).attr('data-lzy-cal-inx');
        var defaultDate = $(this).attr('data-lzy-cal-start');

        $( this ).fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,listYear',   // listWeek, listMonth, listYear
                // right: 'month,agendaWeek,agendaDay,listYear',   // listWeek, listMonth, listYear
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
            viewRender: function(view, element) {
                storeViewMode(inx, view);
                // storeViewMode(inx, view.name);
            },
            dayClick: function (e) {
                if (typeof openCalPopup !== 'undefined') {
                    openCalPopup(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                }
            },
            eventClick: function (e) {
                if (typeof openCalPopup !== 'undefined') {
                    openCalPopup(inx, e);
                } else {
                    defaultOpenCalPopup(inx, e);
                }
            },
            eventDrop: function (e) {
                calEventChanged(inx, e);
            },
            eventResize: function (e) {
                calEventChanged(inx, e);
            },
            eventRender: function (event, element) {
                // console.log(element);
                if (typeof event.i !== 'undefined') {
                    $(element).append('<div class="fc-index">' + event.i + '</div>');
                }
                if (typeof event.location !== 'undefined') {
                    $(element).append('<div class="fc-location">' + event.location + '</div>');
                }
                if (typeof event.description !== 'undefined') {
                    $(element).append('<div class="fc-description">' + event.description + '</div>');
                }
                if (typeof event.comment !== 'undefined') {
                    $(element).append('<div class="fc-comment">' + event.comment + '</div>');
                }
                if (typeof event.tags !== 'undefined') {
                    $(element).append('<div class="fc-tags">' + event.tags + '</div>');
                    $(element).addClass(event.tags);
                }
                // tooltips
            }
        });
    });
});



function calEventChanged(inx, e) {
    if (!lzyCal[ inx ].calEditingPermission) {
        return;
    }
    if (e.allDay) { // TODO: moving allDay events (possible it's not working due to a bug in fullcalendar)
        lzyReload();
        return;
    }
    var start = moment(e.start._i);
    var end = moment(e.end._i);
    var rec = {};
    rec['rec-id'] = e.i;
    rec['data-src'] = '';
    rec['user'] = e.title;
    rec['title'] = e.title;
    rec['location'] = e.location;
    rec['start-date'] = start.format('YYYY-MM-DD');
    rec['start-time'] = start.format('HH:mm');
    rec['end-date'] = end.format('YYYY-MM-DD');
    rec['end-time'] = end.format('HH:mm');
    rec['comment'] = e.comment;
    rec['tags'] = e.tags;
    rec['allday'] = e.allDay;

    var json = JSON.stringify(rec);

    console.log('calEventChanged: ' + json);
    $.ajax({
        url : calBackend + '?inx=' + inx + '&save',
        type: 'post',
        data : 'json='+json,
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
        if (response && response != 'ok') { console.log(response); }
    });
} // calEventChanged



// function storeViewMode(inx, viewName) {
function storeViewMode(inx, view) {
    var viewName = view.name;
    var intervalStart = view.intervalStart;
    console.log('save view mode: ' + viewName);
    $.ajax({
        url : calBackend + '?inx=' + inx + '&mode=' + viewName,
        type: 'get',
    }).done(function(response){
        if (isServerErrMsg(response)) { return; }
        if (response && response !== 'ok') { console.log(response); }
        // if (response && response != 'ok') { console.log(response); }
    });
} // storeViewMode



function defaultOpenCalPopup(inx, e) {
    if (!lzyCal[ inx ].calEditingPermission) {
        return;
    }
    var options = {};
    options.anker = false;

    $('#lzy-inx').val(inx);
    $('#lzy-calendar-default-form').attr('data-cal-inx', inx);

    if (typeof e._i != 'undefined') {                          // new entry
        $('#lzy-cal-new-event-header').show();
        $('#lzy-cal-modify-event-header').hide();
        var date = e._i;    // case: new entry
        if (typeof date != 'number') {
            var d = moment(date);
            d.local();
            var dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            var timeStr = d.format('HH:mm');
            $('#lzy-calendar-default-form input[name=start-time]').val(timeStr);

            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            timeStr = d.add(1, 'hours').format('HH:mm');
            $('#lzy-calendar-default-form input[name=end-time]').val(timeStr);

            $('#lzy_cal_event_name').val('');
            $('#lzy_cal_event_location').val('');
            $('#lzy_cal_comment').text('');

        } else {    // case whole day event:
            var dateStr = moment(date).format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('');
            $('#lzy_cal_end_time').attr('type', 'hidden').val('');
            $('#lzy-allday').val('true');
        }

    } else {                                                   // existing entry
        $('#lzy-cal-new-event-header').hide();
        $('#lzy-cal-modify-event-header').show();
        var d = moment( e.start._i );
        d.utc();
        var dateStr = d.format('YYYY-MM-DD');
        $('#lzy_cal_start_date').val(dateStr);
        if (e.allDay == false) {    // normal event
            var timeStr = d.format('HH:mm');
            $('#lzy_cal_start_time').val(timeStr);
            $('#lzy-allday').val('false');

            d = moment(e.end._i);
            d.utc();
            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            timeStr = d.format('HH:mm');
            $('#lzy_cal_end_time').val(timeStr);

        } else {    // allday event:
            var d = moment( e.start._i );
            dateStr = d.format('YYYY-MM-DD');
            $('#lzy_cal_start_date').val(dateStr);
            d = moment(e.end._i);
            dateStr = d.subtract({ minutes: 1}).format('YYYY-MM-DD');
            $('#lzy_cal_end_date').val(dateStr);
            $('#lzy_cal_start_time').attr('type', 'hidden').val('');
            $('#lzy_cal_end_time').attr('type', 'hidden').val('');
            $('#lzy-allday').val('true');
        }

        $('#lzy_cal_event_name').val(e.title);
        $('#lzy_cal_event_location').val(e.location);
        $('#lzy_cal_comment').text(e.comment);

        $('#lzy_cal_tags option[value=' + e.tags + ']').attr('selected','selected');
        $('#lzy-rec-id').val(e.i);

        $('#lzy_cal_delete_entry').show();

        $('#lzy-cal-delete-entry-checkbox').change(function(e) {
            e.preventDefault();
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

    $('#lzy-calendar-default-form').submit(function(event) {
        event.preventDefault();
        var $this = $(this);

        var post_url = $this.attr("action") + '?save';
        var request_method = $this.attr("method");
        var form_data = $this.serialize();

        // console.log(': ' + viewName);
        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function (response) {
            if (isServerErrMsg(response)) { return; }
            if (response && response != 'ok') { console.log(response); alert(response); }
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
            if (isServerErrMsg(response)) { return; }
            if (response && response != 'ok') { console.log(response); alert(response); }
            lzyReload();
        });
    });

    $('#lzy_cal_event_name').focus();

} // defaultOpenCalPopup