if(!Modernizr.touch){ 
    $.stellar();
}
    
/* ==============================================
Liquid Slider - Home Text Slider
=============================================== */
/* $('#slider-home').liquidSlider({
	  autoSlide:          true,
	  autoSlideInterval:  4500,
	  autoSlideControls:  true,
	  forceAutoSlide: true,
	  dynamicArrows: false,
    
  slideEaseFunction:'animate.css',
  slideEaseDuration:900,
  heightEaseDuration:900,
  animateIn:"bounceIn",
  animateOut:"bounceOut",
  callback: function() {
    var self = this;
    $('.slider-6-panel').each(function() {
      $(this).removeClass('animated ' + self.options.animateIn);
    });
  }
}); */

/* ==============================================
Liquid Slider - Quotes Slider
=============================================== */
/* $('#separator-slider-1').liquidSlider({
	  autoSlide:          true,
	  autoSlideDirection: 'right',
	  autoSlideInterval:  4500,
	  autoSlideControls:  true,
	  forceAutoSlide: true,
	  autoHeight: false,
      dynamicArrows: true,
	  
  slideEaseFunction:'animate.css',
  slideEaseDuration:500,
  heightEaseDuration:500,
  animateIn:"flipInX",
  animateOut:"fadeOut",
  callback: function() {
    var self = this;
    $('.slider-6-panel').each(function() {
      $(this).removeClass('animated ' + self.options.animateIn);
    });
  }
}); */

/* ==============================================
Liquid Slider - Testimonials
=============================================== */
/* $('#testimonials-slider').liquidSlider({
	  autoSlide:          true,
	  autoSlideDirection: 'right',
	  autoSlideInterval:  5000,
	  autoSlideControls:  true,
	  forceAutoSlide: true,
	  autoHeight: false,
    dynamicArrows: true,
	  
	  
  slideEaseFunction:'animate.css',
  slideEaseDuration:500,
  heightEaseDuration:500,
  animateIn:"flipInX",
  animateOut:"fadeOut",
  callback: function() {
    var self = this;
    $('.slider-6-panel').each(function() {
      $(this).removeClass('animated ' + self.options.animateIn);
    });
  }
}); */

/* ==============================================
Sticky Navbar
=============================================== */

$(document).ready(function(){
    $(".navbar").sticky({topSpacing:0});
});

$(document).ready(function() {
	var $header = $('.header-top');
    $(window).scroll(function () {
       if(scrollY <= 0){
           $header.animate({
                opacity: 0
           }, 300);
       }
       if(scrollY > 0 && $header.is(':not(:animated)')){
           $header.animate({
                opacity: 0.9999
           }, 300);
        }
     });
});

/* ==============================================
Navbar "hovernav" dropdown menu
=============================================== */

/* $(document).ready(function() {

	var mq = window.matchMedia('(min-width: 768px)');
  if (mq.matches) {
    $('ul.navbar-nav li').addClass('hovernav');
  } else {
  	$('ul.navbar-nav li').removeClass('hovernav');
  };

  if (matchMedia) {
    var mq = window.matchMedia('(min-width: 768px)');
    mq.addListener(WidthChange);
    WidthChange(mq);
  }
	function WidthChange(mq) {
    if (mq.matches) {
      $('ul.navbar-nav li').addClass('hovernav');
    } else {
      $('ul.navbar-nav li').removeClass('hovernav');
    }
  };
  
}); */

/* ==============================================
Auto Close Responsive Navbar on Click
=============================================== */

function close_toggle() {
if ($(window).width() <= 768) {
  $('.navbar-collapse a').on('click', function(){
      $('.navbar-collapse').collapse('hide');
  });
}
else {
 $('.navbar .navbar-default a').off('click');
}
}
close_toggle();

$(window).resize(close_toggle); 
 
/* ==============================================
WOW plugin triggers animation.css on scroll
=============================================== */

var wow = new WOW(
  {
    boxClass:     'wow',      // animated element css class (default is wow)
    animateClass: 'animated', // animation css class (default is animated)
    offset:       150,          // distance to the element when triggering the animation (default is 0)
    mobile:       false        // trigger animations on mobile devices (true is default)
  }
);
wow.init();

/* ==============================================
Bootstrap Tooltip, Alert, Tabs
=============================================== */

$(function () { $("[data-toggle='tooltip']").tooltip();  
    $(".alert").alert()
});

$(function () {
    var active = true;
    $('#collapse-init').click(function () {
        if (active) {
            active = false;
            $('.panel-collapse').collapse('show');
            $('.panel-title').attr('data-toggle', '');
            $(this).text('Close All');
        } else {
            active = true;
            $('.panel-collapse').collapse('hide');
            $('.panel-title').attr('data-toggle', 'collapse');
            $(this).text('Open All');
        }
    });
    $('#accordion').on('show.bs.collapse', function () {
        if (active) $('#accordion .in').collapse('hide');
    });
});

$('#myTab a').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})

/* ==============================================
mb.YTPlayer
=============================================== */

/* $(function(){
	$(".player").mb_YTPlayer();
}); */

/* ==============================================
Skill Bars
=============================================== */

$('.skills-col').waypoint(function() {
   jQuery('.skillbar').each(function(){
		jQuery(this).find('.skillbar-bar').animate({
			width:jQuery(this).attr('data-percent')
		},2000);
	});
	
	}, { offset: '100%' 
});

/* ==============================================
Preloader
=============================================== */

jQuery(window).load(function(){
    jQuery("#preloader").delay(500).fadeOut(1000);
        jQuery(".preload-logo").addClass('fadeOutLeft');
		jQuery(".back-logo").addClass('fadeOutRight');
		jQuery(".preload-gif").addClass('fadeOutUp');
    
});

/* ==============================================
Counter Up
=============================================== */
 jQuery(document).ready(function($) {
            $('.counter').counterUp({
                delay: 10,
                time: 800
            });
        });
 
 
/* ==============================================
Fit Vids
=============================================== */
/* $(document).ready(function(){
    // Target your .container, .wrapper, .post, etc.
    $("#thing-with-videos").fitVids();
  });




jQuery(document).ready(function($){

var height_video = $(window).width();
var height_responsive = (height_video / 1.78011) + 1;
$('.video_slide').css("height",height_responsive);



$(window).resize(function() {

var height_video = $(window).width();
var height_responsive = (height_video / 1.78011) + 1;
$('.video_slide').css("height",height_responsive);

});
}); */
 
/* ==============================================
Parallax
=============================================== */

$(document).ready(function(){
      $(window).stellar({ 
	  horizontalScrolling: false,
	  });  
});

/* ==============================================
FlexSlider v2.2.2
=============================================== */

/* $(window).load(function() {
  $('.flexslider').flexslider({
    animation: "slide"
  });
}); */ 

/* ==============================================
Contact Form
=============================================== */

jQuery(document).ready(function(){

	$('#contactform').submit(function(){

		var action = $(this).attr('action');

		$("#message").slideUp(750,function() {
		$('#message').hide();

 		$('#submit')
			.after('<img src="img/ajax-loader.gif" class="loader" />')
			.attr('disabled','disabled');

		$.post(action, {
			name: $('#name').val(),
			email: $('#email').val(),
			phone: $('#phone').val(),
			comments: $('#comments').val(),
		},
			function(data){
				document.getElementById('message').innerHTML = data;
				$('#message').slideDown('slow');
				$('#contactform img.loader').fadeOut('slow',function(){$(this).remove()});
				$('#submit').removeAttr('disabled');
				if(data.match('success') != null) $('#contactform').slideUp('slow');

			}
		);

		});

		return false;

	});

});

/* ==============================================
Google Maps
=============================================== */

$("#map").gmap3({
    marker:{     
	address:"93 Worth St, New York, NY", 
	options:{ icon: "img/marker.png"}},
    map:{
    options:{
	styles: [ {
	stylers: [ { "saturation":-100 }, { "lightness": 0 }, { "gamma": 0.5 }]},
	],
	zoom: 13,
	scrollwheel:false,
	draggable: true }
	}
	});	

/* ==============================================
Back to Top
=============================================== */

$(window).scroll(function(){
		if($(window).scrollTop() > 300){
			$("#back-to-top").fadeIn(600);
		} else{
			$("#back-to-top").fadeOut(600);
		}
	});
	
	$('#back-to-top, .back-to-top').click(function() {
		  $('html, body').animate({ scrollTop:0 }, '1000');
		  return false;
	});

/* ==============================================
Backstretch - v2.0.4
=============================================== */
    
$(".home-fullscreen-slider").backstretch([
	"img/slide-1.jpg",
	"img/slide-2.jpg",
	"img/slide-3.jpg"
], {
	fade: 750,
	duration: 4000
});
    
/* ==============================================
Portfolio
=============================================== */

(function ($, window, document, undefined) {

    var gridContainer = $('#grid-container'),
        filtersContainer = $('#filters-container');

	// init cubeportfolio
    gridContainer.cubeportfolio({

        defaultFilter: '*',

        animationType: 'flipOut',

        gapHorizontal: 25,

        gapVertical: 25,

        gridAdjustment: 'responsive',

        caption: 'overlayBottomReveal',

        displayType: 'lazyLoading',

        displayTypeSpeed: 100,

        // lightbox
        lightboxDelegate: '.cbp-lightbox',
        lightboxGallery: true,
        lightboxTitleSrc: 'data-title',
        lightboxShowCounter: true,

        // singlePage popup
        singlePageDelegate: '.cbp-singlePage',
        singlePageDeeplinking: true,
        singlePageStickyNavigation: true,
        singlePageShowCounter: true,
        singlePageCallback: function (url, element) {

            // to update singlePage content use the following method: this.updateSinglePage(yourContent)
            var t = this;

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'html',
                timeout: 5000
            })
            .done(function(result) {
                t.updateSinglePage(result);
            })
            .fail(function() {
                t.updateSinglePage("Error! Please refresh the page!");
            });

        },

        // single page inline
        singlePageInlineDelegate: '.cbp-singlePageInline',
        singlePageInlinePosition: 'above',
        singlePageInlineShowCounter: true,
        singlePageInlineInFocus: true,
        singlePageInlineCallback: function(url, element) {
            // to update singlePage Inline content use the following method: this.updateSinglePageInline(yourContent)
        }
    });

    // add listener for filters click
    filtersContainer.on('click', '.cbp-filter-item', function (e) {

        var me = $(this), wrap;

        // get cubeportfolio data and check if is still animating (reposition) the items.
        if ( !$.data(gridContainer[0], 'cubeportfolio').isAnimating ) {

            if ( filtersContainer.hasClass('cbp-l-filters-dropdown') ) {
                wrap = $('.cbp-l-filters-dropdownWrap');

                wrap.find('.cbp-filter-item').removeClass('cbp-filter-item-active');

                wrap.find('.cbp-l-filters-dropdownHeader').text(me.text());

                me.addClass('cbp-filter-item-active');
            } else {
                me.addClass('cbp-filter-item-active').siblings().removeClass('cbp-filter-item-active');
            }

        }

        // filter the items
        gridContainer.cubeportfolio('filter', me.data('filter'), function () {});

    });

    // activate counters
    gridContainer.cubeportfolio('showCounter', filtersContainer.find('.cbp-filter-item'));


    // add listener for load more click
    $('.cbp-l-loadMore-button-link').on('click', function(e) {

        e.preventDefault();

        var clicks, me = $(this), oMsg;

        if (me.hasClass('cbp-l-loadMore-button-stop')) return;

        // get the number of times the loadMore link has been clicked
        clicks = $.data(this, 'numberOfClicks');
        clicks = (clicks)? ++clicks : 1;
        $.data(this, 'numberOfClicks', clicks);

        // set loading status
        oMsg = me.text();
        me.text('LOADING...');

        // perform ajax request
        $.ajax({
            url: me.attr('href'),
            type: 'GET',
            dataType: 'HTML'
        })
        .done( function (result) {
            var items, itemsNext;

            // find current container
            items = $(result).filter( function () {
                return $(this).is('div' + '.cbp-loadMore-block' + clicks);
            });

            gridContainer.cubeportfolio('appendItems', items.html(),
                 function () {
                    // put the original message back
                    me.text(oMsg);

                    // check if we have more works
                    itemsNext = $(result).filter( function () {
                        return $(this).is('div' + '.cbp-loadMore-block' + (clicks + 1));
                    });

                    if (itemsNext.length === 0) {
                        me.text('NO MORE WORKS');
                        me.addClass('cbp-l-loadMore-button-stop');
                    }

                 });

        })
        .fail(function() {
            // error
        });

    });

})(jQuery, window, document);
    
/* ==============================================
Smooth scrolling to anchor for home buttons
=============================================== */

$(function() {
    $('.btn-home a,.move a').bind('click', function(event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: $($anchor.attr('href')).offset().top - 70
        }, 1500, 'easeOutExpo');
        event.preventDefault();
    });
});

/* ==============================================
Smooth Scroll To Anchor
=============================================== */

//jQuery for page scrolling feature - requires jQuery Easing plugin
$(function() {
    $('#main-nav a').bind('click', function(event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: $($anchor.attr('href')).offset().top - 70
        }, 1500, 'easeOutExpo');
        event.preventDefault();
    });
});
    
/* ==============================================
Active Menu Item on Page Scroll
=============================================== */   
    
var sections = $('section')
  , nav = $('nav')
  , nav_height = nav.outerHeight();
 
$(window).on('scroll', function () {
  var cur_pos = $(this).scrollTop();
 
  sections.each(function() {
    var top = $(this).offset().top - nav_height,
        bottom = top + $(this).outerHeight();
 
    if (cur_pos >= top && cur_pos <= bottom) {
      nav.find('a').removeClass('current');
      sections.removeClass('current');
 
      $(this).addClass('current');
      nav.find('a[href="#'+$(this).attr('id')+'"]').addClass('current');
    }
  });
});

