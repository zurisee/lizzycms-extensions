/*
	Support for Accordion
*/
var mobileView = false
$(document).ready(function() {
    checkSize();
    $(window).resize(checkSize);
});

function checkSize(){
    if ($(".accordion-tab-hdrs").css("display") != "none" ){
        // your code here
		console.log('desktop layout');
        mobileView = false;
    } else {
        console.log('mobile layout');
        mobileView = true;
	}
}



var inx = 0;
var id = '';
var elem = 0;


$('.accordion').each(function() {
	var $acc = $( this );
	var activeElem = '';
	var activeElemClass = '';
    elem++;
	if (!$acc.parent().hasClass('accordion-outer-wrapper')) {
        inx++;
        // elem = 1;
        id = 'accordion-tab-hdrs'+inx;
        $acc.parent().attr('id','accordion-outer-wrapper'+inx).addClass('accordion-outer-wrapper').prepend('<div id="'+ id +'" class="accordion-tab-hdrs"></div>');
        var $outerAccWrapper = $('.accordion-outer-wrapper');
        var $accHdr = $('#'+id);
        $accHdr.html('<ul></ul>');

        activeElemClass = ' class="accordion-open"';
        activeElem = ' accordion-open';
        if ($(".accordion-tab-hdrs").css("display") != "none" ) {
            $acc.addClass('accordion-open');
        }
        // activeElemClass = ' class="active"';
        // activeElem = ' active';
        // $acc.addClass('active');
	}
	var $hdr = $acc.children(':first-child').clone();
	$('#'+id+' ul').append('<li'+activeElemClass+'><a href="#accordion-elem'+elem+'">'+$hdr.text()+'</a></li>');
    // $('ul',$accHdr).append('<li>'+$hdr.text()+'</li>');
	$acc.attr('id', 'accordion-elem'+elem);
	var $accContent = $acc.clone();
	$acc.html( $hdr );
	$(' > *', $acc).addClass('accordion-hdr');
	$acc.append('<div class="accordion-wrapper"></div>');
	// $acc.append('<div class="accordion-wrapper'+activeElem+'"></div>');
//	$acc.append('<div id="accordion-elem'+elem+'" class="accordion-wrapper'+activeElem+'"></div>');
	$('.accordion-wrapper', $acc).html($accContent);
	$('.accordion-wrapper .accordion', $acc).attr('class', 'accordion-inner-wrapper');
	$('.accordion-inner-wrapper', $acc).children(':first-child').remove();
	
	// prepare:
	$('.accordion-inner-wrapper', $acc).css('margin-top', - ($('.accordion-inner-wrapper', $acc).height() + 4));
    // if ($(".accordion-tab-hdrs").css("display") == "none" ) {
    //     $('.accordion').removeClass('accordion-open');
    // }

});

// open/close
$('.accordion-hdr').click(function() {		// accordion mode
/*
	if ( $( this ).parent().hasClass('accordion-open') ) {
		$( this ).removeClass('accordion-open');
		// $( this ).parent().removeClass('accordion-open');
	} else {
		$( '.accordion' ).removeClass('accordion-open');
		// $( this ).addClass('accordion-open');
		$( this ).parent().addClass('accordion-open');
	}
*/
    $( this ).parent().toggleClass('accordion-open');

});

$('.accordion-tab-hdrs ul li a').click(function(e) {	// tabs mode
	e.preventDefault();
	var id = $(this).attr('href');	// a clicked
	$('li', $( this ).parent().parent() ).removeClass('accordion-open');
    $(this).parent().addClass('accordion-open'); // li clicked

    $( '.accordion' ).removeClass('accordion-open');
	$( id ).addClass('accordion-open');
});
