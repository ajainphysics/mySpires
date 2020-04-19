mySpires_Plugin.auth().then(() => {
    $(function () {
        draw_mySpires_bar();
    });
});

function draw_mySpires_bar() {
    mySpires_Plugin.analyze_url().then(function(query){
        if(!query) return;

        if(query.source.includes("arxiv_")) {
            $("body").addClass("mySpires-arxiv");
            arxiv_content(query);
        }

        else if(query.source.includes("inspire_")) {
            $("body").addClass("mySpires-inspire");
            inspire_content();
        }

        else if(query.source.includes("ads_")) {
            $("body").addClass("mySpires-ads");
            ads_content();
        }

        else if(query.source === "overleaf_project") {
            create_cite_window();

            browser.runtime.onMessage.addListener(request => {
                if(request.type === "toggle-cite-window") {
                    let $citeWrapper = $(".myspires-cite-wrapper");

                    if($citeWrapper.is(":visible")) {
                        $citeWrapper.hide();
                        $(".ace_text-input").focus();
                    } else {
                        $citeWrapper.show();
                        $(".myspires-search-bar .search-field").focus();
                    }

                    return Promise.resolve(true);
                }
            });
        }
    }).catch(console.log);
}

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

let cite_ready = Promise.resolve();

function create_cite_window() {
    $("body").prepend(
        "<div class='myspires-cite-wrapper mySpires-context'>" +
        "  <div class='myspires-cite-background'></div>" +
        "  <div class='myspires-cite-window'>" +
        "    <header>" +
        "      <h1>mySpires.</h1>" +
        "      <span class='cite-counter'></span>" +
        "    </header>" +
        "    <article>" +
        "    </article>" +
        "    <footer>" +
        "      <a class='btn btn-primary btn-sm cite-button'>Cite</a>" +
        "      <a class='cite-link' href='#'>Copy BibTeX</a>" +
        "    </footer>" +
        "  </div>" +
        "</div>");

    let $citeWrapper = $(".myspires-cite-wrapper");

    $(".myspires-cite-background").click(function(){$citeWrapper.hide();});

    $citeWrapper.on("keypress", (e) => {
        if (e.key === "Escape") {
            e.preventDefault();
            $citeWrapper.hide();
        }
    });

    $(".myspires-cite-window .cite-button").click(function(e) {
        e.preventDefault();

        $citeWrapper.hide();
        $(".ace_text-input").focus();

        cite_ready = new Promise((resolve, reject) => {
            cite_ready.then(function () {
                let citeArray = Object.values(mySpires_Search.selected);

                if (citeArray.length) {
                    navigator.clipboard.writeText(citeArray.join(", ").trim()).then(function () {
                        document.execCommand("paste");
                        reset_cite();
                        resolve();
                    }).catch(reject);
                }
            }).catch(reject);
        });
    });

    let reset_cite = function() {
        mySpires_Search.selected = {};
        mySpires_Search.$results.find(".mySpires-box").removeClass("selected");
        $(".myspires-cite-window .cite-counter").hide().html("");
    };


    mySpires_Search.default_click = function (e,a) {
        if(!a) return;
        if($(e.target).parents(".links-wrapper").length) return;
        if($(e.target).hasClass("paper-tag")) return;

        e.preventDefault();
        cite_ready = new Promise((resolve, reject) => {
            cite_ready.then(function() {
                if(a.bar.record.status === "unsaved") a.bar.save();

                a.bar.record.busy.then(function () {
                    let key = a.bar.record.bibkey;
                    let id = a.bar.record.id;
                    if (!key || !id) {
                        reject();
                        return;
                    }

                    if (mySpires_Search.selected[id]) {
                        delete mySpires_Search.selected[id];
                        a.box.removeClass("selected");
                    } else {
                        mySpires_Search.selected[id] = key;
                        a.box.addClass("selected");
                    }

                    let $counter = $(".myspires-cite-window .cite-counter");

                    let length = Object.keys(mySpires_Search.selected).length;
                    if (length === 0) $counter.hide().html("");
                    else {
                        if (length > 9) $counter.addClass("shrink");
                        else $counter.removeClass("shrink");

                        $counter.html(length).show();
                    }

                    resolve();
                }).catch(reject);
            }).catch(reject);
        });

        if(!e.ctrlKey && !e.metaKey) {
            $(".myspires-cite-window .cite-button").click();
        }
    };

    mySpires_Search.default_select = function(a) {
        a.box.addClass("selected");
    };

    mySpires_Search.draw($(".myspires-cite-window article"));
}

function arxiv_content(query) {
    if(query.source === "arxiv_abs") {
        $("body").addClass("mySpires-arxiv-abs");
        $(".authors").after("<div class='mySpires-bar'></div>");
        mySpires_Plugin.api(query).then(response => {
            let bar = new mySpires_Bar(response[query.q]);
            respond_to_refresh(bar);
        }).catch(console.log);
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
                let $box = $(this);
                $box.find(".authors").after("<div class='mySpires-bar'></div>");
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
                let $box = $(this);
                $box.find(".list-authors").after("<div class='mySpires-bar'></div>");
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

function inspire_content() {
    let draw_bar_inspire_delayed = setInterval(draw_bar_inspire, 10);
    let draw_bar_inspire_multi_delayed = setInterval(draw_bar_inspire_multi, 10);
    let last_mutation = new Date();

    let inspire_observer = new MutationObserver(function(mutations){
        let new_mutation = new Date();
        if(new_mutation - last_mutation < 1000) return;

        for(let mutation of mutations) {
            if(mutation.type === "childList"
                && ($(mutation.target).is(".ant-col, .ant-row, .pa2"))) {

                clearInterval(draw_bar_inspire_delayed);
                clearInterval(draw_bar_inspire_multi_delayed);
                draw_bar_inspire_delayed = setInterval(draw_bar_inspire, 10);
                draw_bar_inspire_multi_delayed = setInterval(draw_bar_inspire_multi, 10);

                last_mutation = new_mutation;
                break;
            }
        }
    });
    inspire_observer.observe($("main.ant-layout-content")[0], {childList: true, subtree: true});

    let draw_bar_inspire_call = 0;
    function draw_bar_inspire() {
        draw_bar_inspire_call++;
        let $box = $(".__Literature__ .ant-card-body").first();
        if ($box.length) {
            mySpires_Plugin.analyze_url().then(function (query) {
                if(query.source !== "inspire_record") return;
                mySpires_Plugin.api(query).then(response => {
                    if(!$box.find(".mySpires-bar").length) {
                        $box.find(".mt3").before("<div class='mySpires-bar'></div>");
                        let bar = new mySpires_Bar(response[query.q], $box);
                        respond_to_refresh(bar);
                    }
                }).catch(console.log);
            });
            clearInterval(draw_bar_inspire_delayed);
            draw_bar_inspire_call = 0;
        }
        if(draw_bar_inspire_call > 1000) {
            clearInterval(draw_bar_inspire_delayed);
            draw_bar_inspire_call = 0;
        }
    }

    let draw_bar_inspire_multi_call = 0;
    function draw_bar_inspire_multi() {
        draw_bar_inspire_multi_call++;

        let idArray = [];
        $("[data-test-id='search-results'] [data-test-id='literature-result-title-link']").each(function () {
            idArray.push($(this).attr("href").split("/literature/")[1])
        });

        if (idArray.length) {
            mySpires_Plugin.api({q: idArray.join(), field: "inspire"}).then(function (results) {
                let records = new mySpires_Records(results);
                let recordNo = 0;
                $("[data-test-id='search-results'] [data-test-id='literature-result-item']").each(function () {
                    let $box = $(this).closest("div.pa2");
                    if(!$box.find(".mySpires-bar").length) {
                        $(this).after("<div class='mySpires-bar'></div>");
                        let record = records[idArray[recordNo]];
                        if (!record) record = {inspire: idArray[recordNo]};
                        new mySpires_Bar(record, $box);
                    }
                    recordNo++;
                });
            });
            clearInterval(draw_bar_inspire_multi_delayed);
            draw_bar_inspire_multi_call = 0;
        }
        if(draw_bar_inspire_multi_call > 1000) {
            clearInterval(draw_bar_inspire_multi_delayed);
            draw_bar_inspire_multi_call = 0;
        }
    }
}

function ads_content() {
    let draw_bar_ads_delayed = setInterval(draw_bar_ads, 10);
    let draw_bar_ads_multi_delayed = setInterval(draw_bar_ads_multi, 10);
    let last_mutation = new Date();

    let ads_observer = new MutationObserver(function(mutations){
        let new_mutation = new Date();
        if(new_mutation - last_mutation < 1000) return;

        for(let mutation of mutations) {
            if(mutation.type === "childList"
                && ($(mutation.target).is(".list-of-things, .s-abstract-metadata"))) {
                clearInterval(draw_bar_ads_delayed);
                clearInterval(draw_bar_ads_multi_delayed);
                draw_bar_ads_delayed = setInterval(draw_bar_ads, 10);
                draw_bar_ads_multi_delayed = setInterval(draw_bar_ads_multi, 10);

                last_mutation = new_mutation;
                break;
            }
        }
    });
    ads_observer.observe($("div#app-container")[0], {childList: true, subtree: true});

    let draw_bar_ads_call = 0;
    function draw_bar_ads() {
        draw_bar_ads_call++;
        let $box = $(".s-abstract-metadata");
        if ($box.length) {
            console.log(2);
            mySpires_Plugin.analyze_url().then(function (query) {
                if(query.source !== "ads_record") return;
                mySpires_Plugin.api(query).then(response => {
                    if(!$box.find(".mySpires-bar").length) {
                        $box.find("#authors-and-aff").after("<div class='mySpires-bar smallbar'></div>");
                        let bar = new mySpires_Bar(response[query.q], $box);
                        respond_to_refresh(bar);
                    }
                }).catch(console.log);
            });
            clearInterval(draw_bar_ads_delayed);
            draw_bar_ads_call = 0;
        }
        if(draw_bar_ads_call > 1000) {
            clearInterval(draw_bar_ads_delayed);
            draw_bar_ads_call = 0;
        }
    }

    let draw_bar_ads_multi_call = 0;
    function draw_bar_ads_multi() {
        draw_bar_ads_multi_call++;

        let idArray = [];
        $("li .identifier a").each(function () {
            idArray.push($(this).html().trim())
        });

        if (idArray.length) {
            mySpires_Plugin.api({q: idArray.join(), field: "ads"}).then(function (results) {
                let records = new mySpires_Records(results);
                let recordNo = 0;
                $(".results-list li > div").each(function () {
                    let $box = $(this);
                    if(!$box.find(".mySpires-bar").length) {
                        $(this).find(".highlight-row")
                            .before("<div class='row'><div class='col-xs-10 col-xs-offset-1'><div class='mySpires-bar smallbar'></div></div></div>");
                        let record = records[idArray[recordNo]];
                        if (!record) record = {ads: idArray[recordNo]};
                        new mySpires_Bar(record, $box);
                    }
                    recordNo++;
                });
            });
            clearInterval(draw_bar_ads_multi_delayed);
            draw_bar_ads_multi_call = 0;
        }
        if(draw_bar_ads_multi_call > 1000) {
            clearInterval(draw_bar_ads_multi_delayed);
            draw_bar_ads_multi_call = 0;
        }
    }
}