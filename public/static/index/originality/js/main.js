(function(a){var b=a(window);b.on("load",function(){a("#loading").fadeOut(500)});a("#mobile-menu").meanmenu({meanMenuContainer:".mobile-menu",meanScreenWidth:"991",meanExpand:['<i class="fal fa-plus"></i>'],});a("#sidebar-toggle").on("click",function(){a(".sidebar__area").addClass("sidebar-opened");a(".body-overlay").addClass("opened")});a(".sidebar__close-btn").on("click",function(){a(".sidebar__area").removeClass("sidebar-opened");a(".body-overlay").removeClass("opened")});a(".searchOpen").on("click",function(){a(".search-wrapper").addClass("search-open");a(".body-overlay").addClass("opened")});a(".search-close").on("click",function(){a(".search-wrapper").removeClass("search-open");a(".body-overlay").removeClass("opened")});b.on("scroll",function(){var c=a(window).scrollTop();if(c<100){a("#header-sticky").removeClass("sticky")}else{a("#header-sticky").addClass("sticky")}});a("[data-background").each(function(){a(this).css("background-image","url( "+a(this).attr("data-background")+"  )")});a(".grid").imagesLoaded(function(){var c=a(".grid").isotope({itemSelector:".grid-item",percentPosition:true,masonry:{columnWidth:".grid-item",}});a(".masonary-menu").on("click","button",function(){var d=a(this).attr("data-filter");c.isotope({filter:d})});a(".masonary-menu button").on("click",function(d){a(this).siblings(".active").removeClass("active");a(this).addClass("active");d.preventDefault()})});new WOW().init();a(".counter").counterUp({delay:10,time:2000});if(a(".scene").length>0){a(".scene").parallax({scalarX:10,scalarY:15,})}a(".hover__active").on("mouseenter",function(){a(this).addClass("active").parent().siblings().find(".hover__active").removeClass("active")})})(jQuery);