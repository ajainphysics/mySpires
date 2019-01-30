// TODO: Write a more robust tag, authors retrieval system.

const mySpiresBin = {};

$(function () {
    mySpiresBin.$loadMore = $(".load-more-boxes .btn");
    mySpiresBin.totalLoaded = 0;
    mySpiresBin.chunks = 40;

    mySpiresBin.$loadMore.click(mySpiresBin.load);

    mySpiresBin.load();
});


mySpiresBin.load = function() {
    mySpiresBin.$loadMore.fadeOut();

    let range = (mySpiresBin.totalLoaded + 1) + "-" + (mySpiresBin.totalLoaded + mySpiresBin.chunks);

    return new Promise((resolve) => {
        mySpires.bin(range).then(function(response) {
            let records = response.records,
                total = response.total;

            console.log(records);

            jQuery.each(records, function (index, record) {
                let a = new mySpires_Box(record);
                a.box.fadeIn();
            });

            sortPapers("modified", "desc");

            mySpiresBin.totalLoaded = Object.keys(mySpires_Boxes).length;

            if(mySpiresBin.totalLoaded < total)
                mySpiresBin.$loadMore.fadeIn().css("display", "block");

            resolve();
        });
    });
};