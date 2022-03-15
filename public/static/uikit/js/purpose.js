var Layout = function() {
    function e(e) {
        $(".sidenav-toggler").addClass("active"), $(".sidenav-toggler").data("action", "sidenav-unpin"), $("body").addClass("sidenav-pinned ready"), $("body").find(".main-content").append('<div class="sidenav-mask mask-body d-xl-none" data-action="sidenav-unpin" data-target=' + e.data("target") + " />"), $(e.data("target")).addClass("show"), localStorage.setItem("sidenav-state", "pinned")
    }
    function t(e) {
        $(".sidenav-toggler").removeClass("active"), $(".sidenav-toggler").data("action", "sidenav-pin"), $("body").removeClass("sidenav-pinned"), $("body").addClass("ready"), $("body").find(".sidenav-mask").remove(), $(e.data("target")).removeClass("show"), localStorage.setItem("sidenav-state", "unpinned")
    }
    var a = localStorage.getItem("sidenav-state") ? localStorage.getItem("sidenav-state") : "pinned";
    if ($(window).on({
        "load resize": function() {
            $(window).width() < 1200 ? t($(".sidenav-toggler")) : "pinned" == a ? e($(".sidenav-toggler")) : "unpinned" == a && t($(".sidenav-toggler"))
        }
    }), $("body").on("click", "[data-action]", function(a) {
        a.preventDefault();
        var n = $(this),
            o = n.data("action"),
            i = n.data("target");
        switch (o) {
            case "offcanvas-open":
                i = n.data("target"), $(i).addClass("open"), $("body").append('<div class="body-backdrop" data-action="offcanvas-close" data-target=' + i + " />");
                break;
            case "offcanvas-close":
                i = n.data("target"), $(i).removeClass("open"), $("body").find(".body-backdrop").remove();
                break;
            case "aside-open":
                i = n.data("target"), n.addClass("active"), $(i).addClass("show"), $("body").append('<div class="mask-body mask-body-light" data-action="aside-close" data-target=' + i + " />");
                break;
            case "aside-close":
                i = n.data("target"), n.removeClass("active"), $(i).removeClass("show"), $("body").find(".body-backdrop").remove();
                break;
            case "omnisearch-open":
                i = n.data("target"), n.addClass("active"), $(i).addClass("show"), $(i).find(".form-control").focus(), $("body").addClass("omnisearch-open").append('<div class="mask-body mask-body-dark" data-action="omnisearch-close" data-target="' + i + '" />');
                break;
            case "omnisearch-close":
                i = n.data("target"), $('[data-action="search-open"]').removeClass("active"), $(i).removeClass("show"), $("body").removeClass("omnisearch-open").find(".mask-body").remove();
                break;
            case "search-open":
                i = n.data("target"), n.addClass("active"), $(i).addClass("show"), $(i).find(".form-control").focus();
                break;
            case "search-close":
                i = n.data("target"), $('[data-action="search-open"]').removeClass("active"), $(i).removeClass("show");
                break;
            case "sidenav-pin":
                e(n);
                break;
            case "sidenav-unpin":
                t(n)
        }
    }), $("[data-offset-top]").length) {
        var n = $("[data-offset-top]"),
            o = $(n.data("offset-top")).height();
        n.css({
            "padding-top": o + "px"
        })
    }
}(),PasswordText=function(){var e=$('[data-toggle="password-text"]');e.length&&e.on("click",function(e){var t,a;t=$(this),"password"==(a=$(t.data("target"))).attr("type")?a.attr("type","text"):a.attr("type","password")})}();