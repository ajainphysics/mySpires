/**
 * @file Contains library for mySpires-plugin
 * @author Akash Jain
 */

/*=====================================================================*
 * mySpires class
 *=====================================================================*/
/**
 * This class provides an interface to talk with mySpires-API. It contains fundamental functions for the functioning
 * of the plugin.
 */
class mySpires_Plugin {
    /**
     * @param url - URL to analyze
     * @returns Promise
     */
    static analyze_url(url) {
        let hostname, pathname;
        if(url) {
            hostname = url.split("://")[1].split("/")[0].split("?")[0];
            pathname = url.split(hostname)[1].split("?")[0];
        } else {
            hostname = window.location.hostname;
            pathname = window.location.pathname;
        }

        if(hostname === "arxiv.org") {
            if(pathname.includes("/abs/")) {
                let arxiv = pathname.split("/abs/")[1].split("v")[0];
                return Promise.resolve({q: arxiv, field: "arxiv", source: "arxiv_abs"});
            }
            else if(pathname.includes("/pdf/")) {
                let arxiv = pathname.split("/pdf/")[1].split(".pdf")[0];
                return Promise.resolve({q: arxiv, field: "arxiv", source: "arxiv_pdf"});
            }
            else if (pathname.split("/")[1] === "search") {
                return Promise.resolve({source: "arxiv_search"});
            }
            else if (pathname.split("/")[1] === "list") {
                return Promise.resolve({source: "arxiv_list"});
            }
        }

        else if(hostname === "inspirehep.net") {
            let pieces = pathname.split("/");
            if (pieces[1] === "literature" && pieces.length === 3) {
                let inspire = strip_hash(pathname.split("/literature/")[1]);
                return Promise.resolve({q: inspire, field: "inspire", source: "inspire_record"});
            }
            else if (pieces[1] === "literature" && pieces.length === 2) {
                return Promise.resolve({source: "inspire_search"});
            }
            else if (pieces[1] === "authors" && pieces.length === 3) {
                return Promise.resolve({source: "inspire_author"});
            } else {
                return Promise.resolve({source: "inspire_"});
            }
        }

        else if(hostname === "old.inspirehep.net") {
            if(pathname.includes("/record/")) {
                let inspire = strip_hash(pathname.split("/record/")[1]);
                return Promise.resolve({q: inspire, field: "inspire", source: "inspire_record"});
            }
        }


        else if(hostname === "ui.adsabs.harvard.edu") {
            if (pathname.includes("/abs/")) {
                let ads = strip_hash(pathname.split("/abs/")[1].split('/')[0]);
                if(ads) return Promise.resolve({q: ads, field: "ads", source: "ads_record"});
            } else {
                return Promise.resolve({source: "ads_"});
            }
        }

        else if(hostname === "scipost.org") {
            if(pathname.includes("/pdf")) {
                let doi = "10.21468" + pathname.split("/pdf")[0];
                return Promise.resolve({q: doi, field: "doi", source: "scipost"});
            }
        }

        else if(hostname === "www.sciencedirect.com") {
            if (pathname.includes("/pii/")) {
                let pii = strip_hash(pathname.split("/pii/")[1]);
                return new Promise((resolve) => {
                    $.get(mySpires.server + "api/elsevier_pii.php",
                        {query: pii},
                        function (doi) {
                            resolve({q: doi, field: "doi", source: "sciencedirect"});
                        }
                    )}).catch(console.log)
            }
        }

        else if(hostname === "www.hindawi.com") {
            if(pathname.includes("/journals/ahep/")) {
                let doi = "10.1155/" + strip_hash(pathname.split("/journals/ahep/")[1]).slice(0,-1);
                return Promise.resolve({q: doi, field: "doi", source: "scipost"});
            }
        }

        else if(hostname === "www.overleaf.com")  {
            if (pathname.includes("/project/")) {
                let overleaf = pathname.split("/project/")[1];
                return Promise.resolve({source: "overleaf_project", overleaf_id: overleaf});
            }
        }

        // Check for doi
        if(pathname.includes("/10.")) {
            let doi;
            let doi_ = pathname.slice(pathname.indexOf("/10.")+1).split("#")[0].split("?")[0];
            let e = doi_.split("/");
            if(e[0].search(/^[\d.]+$/) !== -1) {
                doi = doi_; // doi was not encoded
                if(e[e.length-1]==="") doi = doi_.slice(0,-1); // remove trailing slash
            } else {
                doi = e[0]; // doi was encoded
            }

            // Known suffixes without trailing slash
            let doi_suffixes = {
                "link.springer.com":  [".pdf"],
                "iopscience.iop.org": ["/meta", "/pdf"]
            };
            if(doi_suffixes[hostname]) {
                for (let s of doi_suffixes[hostname]) {
                    doi = doi.split(s)[0];
                }
            }

            return Promise.resolve({q: decodeURIComponent(doi), field: "doi", source: hostname});
        }

        return Promise.reject();
    }

    static api(args) {
        return browser.runtime.sendMessage({
            type: "api",
            args: args
        });
    }

    static ping(message) {
        browser.runtime.sendMessage({
            type: "ping",
            message: message
        }).then(console.log).catch(console.log);
    }

    static auth(lenient = false) {
        return browser.runtime.sendMessage({
            type: "auth",
            lenient: lenient
        });
    }
}

function utf8_decode (strData) {
    let tmpArr = [];
    let i = 0;
    let c1 = 0;
    let seqlen = 0;

    strData += '';

    while (i < strData.length) {
        c1 = strData.charCodeAt(i) & 0xFF;
        seqlen = 0;

        // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
        if (c1 <= 0xBF) {
            c1 = (c1 & 0x7F);
            seqlen = 1
        } else if (c1 <= 0xDF) {
            c1 = (c1 & 0x1F);
            seqlen = 2
        } else if (c1 <= 0xEF) {
            c1 = (c1 & 0x0F);
            seqlen = 3
        } else {
            c1 = (c1 & 0x07);
            seqlen = 4
        }

        for (let ai = 1; ai < seqlen; ++ai) {
            c1 = ((c1 << 0x06) | (strData.charCodeAt(ai + i) & 0x3F))
        }

        if (seqlen === 4) {
            c1 -= 0x10000;
            tmpArr.push(String.fromCharCode(0xD800 | ((c1 >> 10) & 0x3FF)));
            tmpArr.push(String.fromCharCode(0xDC00 | (c1 & 0x3FF)));
        } else {
            tmpArr.push(String.fromCharCode(c1));
        }

        i += seqlen;
    }

    return tmpArr.join('');
}

function active_tab() {
    return new Promise((resolve, reject) => {
        browser.tabs.query({
            currentWindow: true,
            active: true
        }).then(function (tabs) {
            resolve(tabs[0]);
        }).catch(reject)
    });
}

function ask_page(type, message) {
    return new Promise((resolve, reject) => {
        active_tab().then((tab) => {
            browser.tabs.sendMessage(tab.id, {
                type: type,
                message: message
            }).then(response => {
                resolve(response);
            }).catch(function () {
                reject("No answer received!")
            });
        }).catch(reject)
    });
}

class mySpires_Search {
    static draw($where) {
        $where.addClass("mySpires-context");

        $where.append(
            "<form class='myspires-search-bar' target='pseudo-search-target' action='about:blank' autocomplete='on'>" +
            "  <div class='form-group'>" +
            "    <input class='search-field form-control form-control-sm' name='q' type='text' placeholder='Search' autofocus>" +
            "    <div>" +
            "      <button type='submit' class='search-button btn btn-primary btn-sm'>Go</button>" +
            "    </div>" +
            "  </div>" +
            "</form>" +
            "<div class='myspires-search-results mySpires-boxes-minimal mySpires-boxes-rows'></div>"
        );

        mySpires_Search.$field = $where.find(".myspires-search-bar .search-field");
        mySpires_Search.$form = $where.find(".myspires-search-bar");
        mySpires_Search.$results = $where.find(".myspires-search-results");
        mySpires_Search.$button = $where.find(".myspires-search-bar .search-button");

        let $searchField = mySpires_Search.$field;

        $searchField.on("keyup", function (e) {
            mySpires_Search.activate();

            if(e.key === "Escape") {
                if($(this).val().trim()) $(this).val("");
                else mySpires_Search.escape();
            }

            if($searchField.val().length < 4) return;

            let search_inspire = !($searchField.val().trim().split("find")[0]);
            if ((/^[a-zA-Z0-9- ]$/.test(e.key) || e.key === "Backspace" || e.key === "Delete") && !search_inspire)
                mySpires_Search.load();
        });

        mySpires_Search.$form.submit((e) => {
            e.preventDefault();
            mySpires_Search.load();
        });

        $where.keydown((e) => {
            if(e.key === "ArrowDown") {
                e.preventDefault();
                let $focus = $(":focus");
                if($focus.hasClass("search-field")) {
                    mySpires_Search.$results.children(".mySpires-box").first().focus();
                } else if($focus.hasClass("mySpires-box")) {
                    $focus.next().focus();
                }
            } else if(e.key === "ArrowUp") {
                e.preventDefault();
                let $focus = $(":focus");
                if($focus.hasClass("mySpires-box")) {
                    if($focus.is(":first-child"))
                        mySpires_Search.$field.focus();
                    else
                        $focus.prev().focus();
                }
            }
        });

        mySpires_Search.$results.keydown((e) => {
            if(e.key === "Enter") {
                e.preventDefault();
                let $focus = $(":focus");
                if($focus.hasClass("mySpires-box")) {
                    $focus.click();
                }
            }
        });

        mySpires_Search.selected = {};
        mySpires_Search.counter = 0;
    }

    static activate() {}

    static escape() {}

    static load(q) {
        mySpires_Search.$field.focus();
        let $loader = $(".busy-loader");

        if (typeof q === 'undefined') q = mySpires_Search.$field.val().trim();
        else mySpires_Search.$field.val(q);

        let search_inspire = !(q.trim().split("find")[0]);

        if (search_inspire) q = mySpires_Search.$field.val().trim();
        else q = mySpires_Search.$field.val().replace(/[^0-9a-z\s]/gi, ' ').trim();

        if(!search_inspire) {
            q = q.split(" ").filter(function (qBit) {
                return qBit.length > 2;
            }).join(" ");
        }
        if(!q) return;

        let load_search_instance = ++(mySpires_Search.counter);

        mySpires_Search.activate();

        mySpires_Search.$results.find(".mySpires-box").remove();
        mySpires_Search.$results.slideDown();
        $loader.show();
        mySpires_Search.$button.html("<i class='fa fa-spinner fa-spin' aria-hidden='true'></i>");

        let args;
        if(search_inspire) args = {search: q};
        else args = {lookup: q};

        mySpires.api(args).then((results) => {
            let records = Object.values(results);
            let sort_factor = "updated";
            if(search_inspire) sort_factor = "published";

            records.sort(function (a, b) {
                let aa = a[sort_factor],
                    bb = b[sort_factor];
                return (aa < bb) ? 1 : (aa > bb) ? -1 : 0;
            });

            for(let record of records.slice(0,12)) {
                if(load_search_instance === mySpires_Search.counter) {
                    let a = new mySpires_Box(record, mySpires_Search.$results);
                    a.box.attr("tabindex",-1).show();

                    a.box.click(function (e) {
                        if(mySpires_Search.default_click)
                            mySpires_Search.default_click(e, a);
                    });

                    if(mySpires_Search.selected[a.record.id] && mySpires_Search.default_select)
                        mySpires_Search.default_select(a);

                    if(!search_inspire) {
                        a.bar.record.busy.then(() => {
                            let title = a.title.find("a").html();
                            let comments = a.bar.comments.html();

                            let qArray = q.split(" ");
                            for (let qBit of qArray) {
                                let pattern = new RegExp("(" + qBit + ")", "gi");
                                title = title.replace(pattern, "\[\(\[$1\]\)\]");
                                comments = comments.replace(pattern, "\[\(\[$1\]\)\]");

                                a.bar.tags.find(".paper-tag").each(function () {
                                    let tag = $(this).attr("data-original-title");
                                    if (!tag) tag = $(this).attr("title");

                                    if (pattern.test(tag))
                                        $(this).addClass("mark");
                                });

                                a.authors.find(".paper-author").each(function () {
                                    let author = $(this).attr("data-original-title");
                                    if (!author) author = $(this).attr("title");

                                    if (pattern.test(author))
                                        $(this).addClass("mark");
                                });
                            }

                            title = title.replace(/(\[\(\[)/g, "<mark>").replace(/(]\)])/g, "</mark>");
                            a.title.find("a").html(title);

                            comments = comments.replace(/(\[\(\[)/g, "<mark>").replace(/(]\)])/g, "</mark>");
                            a.bar.comments.html(comments);
                        });
                    }
                }
            }

            $loader.hide();
            mySpires_Search.$button.html("Go");
        })
    }
}

function strip_hash($str) {
    return decodeURIComponent($str.split("#")[0].split("?")[0]);
}