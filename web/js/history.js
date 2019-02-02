// TODO: Write a more robust tag, authors retrieval system.

const mySpiresHistory = {};

$(function () {
    mySpiresHistory.$loadMore = $(".load-more-boxes .btn");
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
                total = Number(response.total);

            if(total) {
                $("#residual-history-message").show();
            } else {
                $("#empty-history-message").show();
            }

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

$(function () {
    $(".enable-history-button").click((e) => {
        e.preventDefault();
        mySpires.api({set_history_status: 1}).then((response) => {
            if(response) location.reload();
            else console.log("Something went wrong while activating history!");
        }).catch(console.log)
    });

    $(".purge-history-button").click((e) => {
        e.preventDefault();
        if(confirm("Are you sure you want to delete all your history? This action cannot be undone.")) {
            mySpires.api({purge_history: 1}).then((response) => {
                if(response) location.reload();
                else console.log("Something went wrong while purging history!");
            }).catch(console.log)
        }
    });
});