mySpires_Plugin.auth().then(() => {
    let query = mySpires_Plugin.analyze_url();

    if(query) {
        if(query.source === "arxiv_abs") {
            $("body").addClass("mySpires-arxiv-abs");
            $(".dateline").after("<div class='mySpires-bar'></div>");
            mySpires_Plugin.api(query).then(response => {
                let bar = new mySpires_Bar(response[query.q]);
                respond_to_refresh(bar);
            }).catch(console.log);
        }

        else if(query.source === "inspire_record") {
            $("body").addClass("mySpires-inspire-record");
            $(".authorlink").last().nextAll("div").first().before("<div class='mySpires-bar'></div>");

            mySpires_Plugin.api(query).then(response => {
                let bar = new mySpires_Bar(response[query.q], $(".detailed_record_info"));
                respond_to_refresh(bar);
            }).catch(console.log);
        }

        else if(query.source === "inspire_search") {
            // Extract INSPIRE IDs from the webpage
            let idArray = [];
            $("abbr.unapi-id").each(function () {
                idArray.push($(this).attr("title"))
            });

            mySpires_Plugin.api({q: idArray.join(), field: "inspire"}).then(function(results) {
                $("body").addClass("mySpires-inspire-search");

                let records = new mySpires_Records(results);

                let recordNo = 0;
                $("div.record_body").each(function () {
                    let $box = $(this).after("<div class='mySpires-bar smallbar'></div>").closest("tr");
                    let record = records[idArray[recordNo]];
                    if(!record) {
                        record = {inspire: idArray[recordNo]};
                    }

                    new mySpires_Bar(record, $box);

                    recordNo++;
                });
            });
        }

        else if(query.source  === "arxiv_search") {
            let idArray = [];
            $("p.list-title > a").each(function() {
                idArray.push($(this).html().split("arXiv:")[1]);
            });

            mySpires_Plugin.api({q: idArray.join(), field: "arxiv"}).then(function(results) {
                $("body").addClass("mySpires-arxiv-search");

                let records = new mySpires_Records(results);

                let recordNo = 0;
                $("li.arxiv-result").each(function () {
                    let $box = $(this).append("<div class='mySpires-bar smallbar'></div>");
                    let record = records[idArray[recordNo]];
                    if(!record) {
                        record = {arxiv: idArray[recordNo]};
                    }

                    new mySpires_Bar(record, $box);

                    recordNo++;
                });
            });
        }

        else if(query.source  === "arxiv_list") {
            let idArray = [];

            $("span.list-identifier > a[title='Abstract']").each(function() {
                idArray.push($(this).html().split("arXiv:")[1]);
            });

            mySpires_Plugin.api({q: idArray.join(), field: "arxiv"}).then(function(results) {
                $("body").addClass("mySpires-arxiv-list");

                let records = new mySpires_Records(results);

                let recordNo = 0;
                $("dd > div.meta").each(function () {
                    let $box = $(this).append("<div class='mySpires-bar smallbar'></div>");
                    let record = records[idArray[recordNo]];
                    if(!record) {
                        record = {arxiv: idArray[recordNo]};
                    }

                    new mySpires_Bar(record, $box);

                    recordNo++;
                });
            });
        }
    }

});

$("head").append(
    "<style>" +
    "  @font-face {" +
    "    font-family: 'Lato';" +
    "    font-style: normal;" +
    "    font-weight: 300;" +
    "    src: url("+ browser.extension.getURL("fonts/Lato-Light.ttf") +");" +
    "  }" +
    "  @font-face {" +
    "    font-family: 'Lato';" +
    "    font-style: normal;" +
    "    font-weight: 400;" +
    "    src: url("+ browser.extension.getURL("fonts/Lato-Regular.ttf") +");" +
    "  }" +
    "  @font-face {" +
    "    font-family: 'Lato';" +
    "    font-style: normal;" +
    "    font-weight: 700;" +
    "    src: url("+ browser.extension.getURL("fonts/Lato-Bold.ttf") +");" +
    "  }" +
    "</style>"
).append($('<link rel="stylesheet" type="text/css"/>')
    .attr('href', 'https://use.fontawesome.com/51c88e55b6.css'));

function sendPageInfo(query, source) {
    if(query instanceof mySpires_Bar) {
        browser.runtime.onMessage.addListener(request => {
            if (request.type === "pageinfo") {
                return new Promise((resolve) => {
                    query.record.busy.then(function() {
                        resolve({record: query.record, source: source, from: 0})
                    });
                });
            } else if(request.type === "refresh") {
                query.refreshValues(request.message);
                return Promise.resolve(true);
            }
        });
    } else {
        browser.runtime.onMessage.addListener((request) => {
            if (request.type === "pageinfo") {
                return Promise.resolve({
                    query: query,
                    source: source,
                    from: 1
                });
            }
        });
    }
}

function respond_to_refresh(query) {
    browser.runtime.onMessage.addListener(request => {
        if(request.type === "refresh") {
            query.refreshValues(request.message);
            return Promise.resolve(true);
        }
    });
}