"use strict";

// TODO: Write a more robust tag, authors retrieval system.

var firstSection = true;

function tagShow(timeframe) {
    var $currentSection = $("#section-" + timeframe);

    return new Promise(function (resolve) {
        mySpires.timeframe(timeframe).then(function (records) {
            if (Object.keys(records).length === 0) {
                $currentSection.remove();
                resolve();
                return;
            }

            if (firstSection === true) firstSection = false;

            jQuery.each(records, function (index, record) {
                new mySpires_Box(record, "#section-" + timeframe);
                // entryBox.loadEntry(entry, "#section-" + timeframe);
            });

            sortPapers("modified", "desc", "#section-" + timeframe);

            $currentSection.find(".history-title").fadeIn(200);
            resolve();
        });
    });
}

function sectionShow(timeframe) {
    var $currentSection = $("#section-" + timeframe);
    $currentSection.toggleClass("active");

    if ($currentSection.hasClass("active")) {
        $currentSection.find(".openable-arrow").toggleClass("fa-angle-double-down fa-angle-double-up");

        var $ele = $currentSection.find(".paper-box").first();
        var trigger = setInterval(function () {
            $ele.show(300);
            $ele = $ele.next();
            if ($ele.length === 0) clearInterval(trigger);
        }, 10);
    } else {
        $currentSection.find(".openable-arrow").toggleClass("fa-angle-double-down fa-angle-double-up");
        $currentSection.find(".paper-box").hide(300);
    }
}

$(function () {
    var tagShowPromise = [];

    $(".history-section").each(function () {
        var timeframe = $(this).attr("id").split("section-")[1];
        tagShowPromise.push(tagShow(timeframe));
    });

    Promise.all(tagShowPromise).then(function () {
        sectionShow($(".history-title").first().attr("id").split("title-")[1]);
    });

    $(".history-title").click(function () {
        var timeframe = $(this).attr("id").split("title-")[1];
        sectionShow(timeframe);
    });
});

function authorShow() {
    var tag = $("#filter-tags").val();
    tag = tag.replace(/ /g, "_");

    var author = $("#filter-authors").val();

    $("div.paper-box").hide();

    if (author === "All Authors") {
        $("div.paper-box.tag-" + tag).show();
    } else {
        $("div.paper-box.tag-" + tag + ".author-" + author).show();
    }

    // sortPapers("modified", "asc");

    return true;
}

$("#filter-authors").change(authorShow);

function sortPapers(method, order, within) {
    if (method === undefined) method = $("#filter-sort").val();
    if (order === undefined) order = $("#filter-sort-button").attr("order");
    if (within === undefined) within = ".paper-boxes";

    if (method === "modified") {
        $(within + ' div.paper-box:visible').sort(function (a, b) {
            a = entryBox[$(a).attr("id").split("-")[1]].modifiedDate;
            b = entryBox[$(b).attr("id").split("-")[1]].modifiedDate;
            if (order === "asc") return a < b ? -1 : a > b ? 1 : 0;else return a < b ? 1 : a > b ? -1 : 0;
        }).appendTo(within);
    } else if (method === "published") {
        $(within + ' div.paper-box:visible').sort(function (a, b) {
            a = entryBox[$(a).attr("id").split("-")[1]].publishedDate;
            b = entryBox[$(b).attr("id").split("-")[1]].publishedDate;
            if (order === "asc") return a < b ? -1 : a > b ? 1 : 0;else return a < b ? 1 : a > b ? -1 : 0;
        }).appendTo(within);
    }

    return true;
}
//# sourceMappingURL=history.js.map