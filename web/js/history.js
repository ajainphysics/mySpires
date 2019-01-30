// TODO: Write a more robust tag, authors retrieval system.

const mySpiresHistory = {};

$(function () {
    mySpiresHistory.$loadMore = $("#history-load-more .btn");
    mySpiresHistory.totalLoaded = 0;
    mySpiresHistory.chunks = 40;

    mySpiresHistory.$loadMore.click(mySpiresHistory.load);

    mySpiresHistory.load();
});


mySpiresHistory.load = function() {
    mySpiresHistory.$loadMore.fadeOut();

    let range = (mySpiresHistory.totalLoaded + 1) + "-" + (mySpiresHistory.totalLoaded + mySpiresHistory.chunks);

    return new Promise((resolve) => {
        mySpires.history(range).then(function(response) {
            let records = response.records,
                total = response.total;

            jQuery.each(records, function (index, record) {
                let a = new mySpires_Box(record);
                a.box.fadeIn();
            });

            sortPapers("modified", "desc");

            mySpiresHistory.totalLoaded = Object.keys(mySpires_Boxes).length;

            if(mySpiresHistory.totalLoaded < total)
                mySpiresHistory.$loadMore.fadeIn().css("display", "block");

            resolve();
        });
    });
};

/*

function tagShow(timeframe) {
    let $currentSection = $("#section-" + timeframe);

    return new Promise((resolve) => {
        mySpires.timeframe(timeframe).then(function(records) {
            if(Object.keys(records).length === 0) {
                $currentSection.remove();
                resolve();
                return;
            }

            if(firstSection === true) firstSection = false;

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
    let $currentSection = $("#section-" + timeframe);
    $currentSection.toggleClass("active");

    if($currentSection.hasClass("active")) {
        $currentSection.find(".openable-arrow").toggleClass("fa-angle-double-down fa-angle-double-up");

        let $ele = $currentSection.find(".paper-box").first();
        let trigger = setInterval(function() {
            $ele.show(300);
            $ele = $ele.next();
            if($ele.length === 0) clearInterval(trigger);
        }, 10);
    } else {
        $currentSection.find(".openable-arrow").toggleClass("fa-angle-double-down fa-angle-double-up");
        $currentSection.find(".paper-box").hide(300)
    }
}

$(function () {
    let tagShowPromise = [];

    $(".history-section").each(function () {
        let timeframe = $(this).attr("id").split("section-")[1];
        tagShowPromise.push(tagShow(timeframe));
    });

    Promise.all(tagShowPromise).then(function() {
        sectionShow($(".history-title").first().attr("id").split("title-")[1]);
    });

    $(".history-title").click(function () {
        let timeframe = $(this).attr("id").split("title-")[1];
        sectionShow(timeframe);
    });
});

*/