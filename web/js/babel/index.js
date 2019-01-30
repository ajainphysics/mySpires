"use strict";

var mySpiresSearch = {};
mySpiresSearch.busy = Promise.resolve();
mySpiresSearch.records = {};
mySpiresSearch.savedSearches = {};

mySpiresSearch.urgentBreak = function () {
    if (mySpiresSearch.urgentBreakPromise) mySpiresSearch.urgentBreakPromise();
    mySpiresSearch.busy.then(function () {
        mySpiresSearch.urgentFlag = false;
        mySpiresSearch.urgent = new Promise(function (resolve) {
            mySpiresSearch.urgentBreakPromise = function () {
                resolve("urgent");
                mySpiresSearch.urgentFlag = true;
                console.log("Breaking promises urgently!");
            };
        });
    });
};
mySpiresSearch.urgentBreak();

$(function () {
    mySpiresSearch.$searchBox = $("#welcome-search");
    mySpiresSearch.$searchInput = mySpiresSearch.$searchBox.find(".searchfield");
    mySpiresSearch.$searchBarReset = mySpiresSearch.$searchBox.find(".search-bar-reset");
    mySpiresSearch.$searchBtn = mySpiresSearch.$searchBox.find(".searchbtn");
    mySpiresSearch.$pagination = $(".search-pagination");
    mySpiresSearch.$paginationFirst = mySpiresSearch.$pagination.find(".search-pagination-first");
    mySpiresSearch.$paginationPrevious = mySpiresSearch.$pagination.find(".search-pagination-previous");
    mySpiresSearch.$paginationNext = mySpiresSearch.$pagination.find(".search-pagination-next");
    mySpiresSearch.$paginationLast = mySpiresSearch.$pagination.find(".search-pagination-last");

    Object.defineProperties(mySpiresSearch, {
        rg: {
            get: function get() {
                return Number(mySpiresSearch.$pagination.attr("data-rg"));
            },
            set: function set(val) {
                mySpiresSearch.$pagination.attr("data-rg", Number(val));
            }
        },
        jrec: {
            get: function get() {
                return Number(mySpiresSearch.$pagination.attr("data-jrec"));
            },
            set: function set(val) {
                mySpiresSearch.$pagination.attr("data-jrec", Number(val));
            }
        },
        totalResults: {
            get: function get() {
                return Number(mySpiresSearch.$pagination.attr("data-total-results"));
            },
            set: function set(val) {
                mySpiresSearch.$pagination.attr("data-total-results", Number(val));
            }
        },

        searchQuery: {
            get: function get() {
                return mySpiresSearch.$searchInput.val();
            },
            set: function set(val) {
                mySpiresSearch.$searchInput.val(val);
            }
        }
    });

    mySpiresSearch.$searchBox.submit(function () {
        mySpiresSearch.change();
    });

    mySpiresSearch.$searchBarReset.click(function () {
        mySpiresSearch.$searchInput.val("").focus();
    });

    mySpiresSearch.$paginationFirst.click(function (e) {
        e.preventDefault();
        if ($(this).hasClass("disabled")) return;
        mySpiresSearch.change();
    });
    mySpiresSearch.$paginationPrevious.click(function (e) {
        e.preventDefault();
        if ($(this).hasClass("disabled")) return;
        mySpiresSearch.change("", mySpiresSearch.jrec - mySpiresSearch.rg);
    });
    mySpiresSearch.$paginationNext.click(function (e) {
        e.preventDefault();
        if ($(this).hasClass("disabled")) return;
        mySpiresSearch.change("", mySpiresSearch.jrec + mySpiresSearch.rg);
    });
    mySpiresSearch.$paginationLast.click(function (e) {
        e.preventDefault();
        if ($(this).hasClass("disabled")) return;
        mySpiresSearch.change("", mySpiresSearch.rg * Math.floor(mySpiresSearch.totalResults / mySpiresSearch.rg) + 1);
    });

    mySpiresSearch.firstSearchQuery = mySpiresSearch.searchQuery;

    mySpiresSearch.set();
});

mySpiresSearch.MARC = function (record, tag) {
    var code = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

    if (code) return record.find("[tag='" + tag + "'] [code='" + code + "']").html();else return record.find("[tag='" + tag + "']").html();
};

/**
 * Loads a query string from INSPIRE into cache.
 * @param {string} searchQuery The query to be searched for on INSPIRE.
 * @param {int} jrec Where to start the results.
 * @return {boolean}
 */
mySpiresSearch.loadINSPIRE = function (searchQuery) {
    var jrec = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;

    if (jrec <= 0) jrec = 1;
    var rg = 25;
    if (!searchQuery) return false;

    // Get the saved results.
    var searchURI = encodeURI(searchQuery + "&rg=" + rg + "&jrec=" + jrec);
    var savedSearch = mySpiresSearch.savedSearches[searchURI];
    // If results are available and were obtained less than 12 hours ago, do nothing.
    if (savedSearch && new Date().getTime() - savedSearch.timestamp < 12 * 60 * 60 * 1000) return false;

    mySpiresSearch.busy = new Promise(function (resolve, reject) {
        mySpiresSearch.busy.then(function () {
            // If urgent resolves, resolve the promise and move on.
            mySpiresSearch.urgent.then(resolve);
            if (mySpiresSearch.urgentFlag) return; // If urgent, abort!

            var results = new InspireRecords(searchQuery, { rg: rg, jrec: jrec, addFields: "abstract" });
            results.busy.then(function () {
                var inspireArray = [];
                var _iteratorNormalCompletion = true;
                var _didIteratorError = false;
                var _iteratorError = undefined;

                try {
                    for (var _iterator = results.records[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
                        var record = _step.value;

                        inspireArray.push(record.inspire);
                        mySpiresSearch.records[record.inspire] = new mySpires_Record(record);
                    }
                } catch (err) {
                    _didIteratorError = true;
                    _iteratorError = err;
                } finally {
                    try {
                        if (!_iteratorNormalCompletion && _iterator.return) {
                            _iterator.return();
                        }
                    } finally {
                        if (_didIteratorError) {
                            throw _iteratorError;
                        }
                    }
                }

                mySpiresSearch.savedSearches[searchURI] = {
                    totalResults: results.totResults,
                    timestamp: new Date().getTime(),
                    results: inspireArray
                };

                resolve();
            }).catch(reject);
        }).catch(reject);
    });

    return true;
};

mySpiresSearch.set = function () {
    var searchQuery = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : "";
    var jrec = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;

    mySpiresSearch.urgentBreak();

    if (jrec <= 0) jrec = 1;
    var rg = 25;

    if (searchQuery) mySpiresSearch.searchQuery = searchQuery;else searchQuery = mySpiresSearch.searchQuery;
    if (!searchQuery) return false; // Don't search if there is nothing to search

    // let searchURI = encodeURI("search=" + searchQuery + "&rg=" + rg + "&jrec=" + jrec);
    var searchURI = encodeURI(searchQuery + "&rg=" + rg + "&jrec=" + jrec);

    // The loading markers
    $(".busy-loader").show();
    mySpiresSearch.$searchBtn.html("<i class='fa fa-spinner fa-spin'></i>");

    // Set the query string in the searchbox (useful when called by a button or history).
    mySpiresSearch.$searchInput.val("").val(searchQuery).blur();

    // mySpiresSearch.load(searchQuery, jrec); // Load the results // From the server
    mySpiresSearch.loadINSPIRE(searchQuery, jrec);

    mySpiresSearch.busy = new Promise(function (resolve, reject) {
        mySpiresSearch.busy.then(function () {
            var savedSearch = mySpiresSearch.savedSearches[searchURI]; // Pick up the search results
            var records = savedSearch.results.map(function (id) {
                return mySpiresSearch.records[id];
            }); // Fetch the records
            var totalResults = savedSearch.totalResults; // Fetch the total results

            // Reset the search results DOM
            mySpiresSearch.$pagination.hide();
            $(".search-results").html("");

            var promiseArray = [];

            var _loop = function _loop(record) {
                record.busy.then(function () {
                    var publishedDate = new Date(record.published);
                    var monthNamesSmall = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                    var dspPublishedDate = monthNamesSmall[publishedDate.getMonth()] + " " + publishedDate.getDate() + ", " + publishedDate.getFullYear();

                    $("#welcome-search-results").append('<div id="search-result-' + record.inspire + '" class="search-result">' + '    <div class="id-wrapper">' + '        <span class="inspire-id"><a href="https://inspirehep.net/record/' + record.inspire + '" target="_blank">' + '            inspirehep/' + record.inspire + '        </a></span>' + '        <span class="id-bullet">&bull;</span>' + '        <span class="arxiv-id"><a href="https://arxiv.org/abs/' + record.arxiv + '" target="_blank">' + '            arXiv:' + record.arxiv + '        </a></span>' + '    </div>' + '    <div class="title-wrapper">' + '        <h2 class="paper-title">' + '        <a href="https://inspirehep.net/record/' + record.inspire + '" target="_blank">' + record.title + '</a>' + '    </h2></div>' + '    <div class="author-wrapper"><p class="paper-authors">' + '        ' + record.author + '    </p></div>' + '    <div class="date-wrapper">' + '        <span class="published-date">' + dspPublishedDate + '</span>' + '    </div>' + '    <div class="links-wrapper">' + '</div>' + '    <div class="mySpires-bar homeserver"></div>' + '    <div class="abstract-wrapper folded">' + '        <div class="paper-abstract">' + record.abstract + '</div>' + '        <div class="fadeout"></div>' + '    </div>' + '</div>');

                    var $box = $("#search-result-" + record.inspire);

                    var $links = $box.find(".links-wrapper");

                    if (record.arxiv) $links.append('<a href="https://arxiv.org/pdf/' + record.arxiv + '" target="_blank">' + 'PDF <span class="sep">|</span> </a>');

                    $links.append('<a href="http://inspirehep.net/record/' + record.inspire + '/export/hx"' + ' target="_blank">BibTeX <span class="sep">|</span> </a>');

                    $links.append('<a class="references-link" href="#">' + 'References <span class="sep">|</span> </a>');
                    $links.find(".references-link").click(function (e) {
                        e.preventDefault();
                        mySpiresSearch.change("find citedby recid " + record.inspire);
                    });

                    if (record.citation_count) {
                        $links.append('<a class="citations-link" href="#">' + 'Citations (' + record.citation_count + ')</a>');
                        $links.find(".citations-link").click(function (e) {
                            e.preventDefault();
                            mySpiresSearch.change("find refersto recid " + record.inspire);
                        });
                    }

                    $links.find("a:last-child span.sep").remove();

                    if (!record.abstract) {
                        $box.find(".abstract-wrapper").remove();
                    } else {
                        var dragging = false;
                        var touchStartTime = void 0;
                        $box.find(".abstract-wrapper").on("touchstart", function () {
                            dragging = false;
                            touchStartTime = new Date().getTime();
                        }).on("touchmove", function () {
                            dragging = true;
                        }).on("touchend", function () {
                            var touchTime = new Date().getTime() - touchStartTime;
                            if (!dragging && touchTime < 1000) $(this).off("click").toggleClass("folded");
                        }).on("click", function () {
                            $(this).toggleClass("folded");
                        });
                    }

                    // new mySpires_Bar(record, $box);
                }).catch(reject);

                promiseArray.push(record.busy);
            };

            var _iteratorNormalCompletion2 = true;
            var _didIteratorError2 = false;
            var _iteratorError2 = undefined;

            try {
                for (var _iterator2 = records[Symbol.iterator](), _step2; !(_iteratorNormalCompletion2 = (_step2 = _iterator2.next()).done); _iteratorNormalCompletion2 = true) {
                    var record = _step2.value;

                    _loop(record);
                }
            } catch (err) {
                _didIteratorError2 = true;
                _iteratorError2 = err;
            } finally {
                try {
                    if (!_iteratorNormalCompletion2 && _iterator2.return) {
                        _iterator2.return();
                    }
                } finally {
                    if (_didIteratorError2) {
                        throw _iteratorError2;
                    }
                }
            }

            Promise.all(promiseArray).then(function () {
                $(".busy-loader").hide();
                mySpiresSearch.$searchBtn.html("Go");

                if (totalResults > rg) {
                    mySpiresSearch.rg = rg;
                    mySpiresSearch.jrec = jrec;
                    mySpiresSearch.totalResults = totalResults;

                    mySpiresSearch.$pagination.find(".search-pagination-status a").html(jrec + " - " + (rg + jrec - 1) + " of " + totalResults);

                    if (jrec !== 1) {
                        mySpiresSearch.$paginationFirst.removeClass("disabled");
                        mySpiresSearch.$paginationPrevious.removeClass("disabled");
                    } else {
                        mySpiresSearch.$paginationFirst.addClass("disabled");
                        mySpiresSearch.$paginationPrevious.addClass("disabled");
                    }

                    if (jrec + rg - 1 < totalResults) {
                        mySpiresSearch.$paginationLast.removeClass("disabled");
                        mySpiresSearch.$paginationNext.removeClass("disabled");
                    } else {
                        mySpiresSearch.$paginationLast.addClass("disabled");
                        mySpiresSearch.$paginationNext.addClass("disabled");
                    }

                    mySpiresSearch.$pagination.show();
                }

                MathJax.Hub.Queue(["Typeset", MathJax.Hub]);

                mySpires.api({ q: savedSearch.results.join(), field: "inspire" }).then(function (records) {
                    var _iteratorNormalCompletion3 = true;
                    var _didIteratorError3 = false;
                    var _iteratorError3 = undefined;

                    try {
                        for (var _iterator3 = savedSearch.results[Symbol.iterator](), _step3; !(_iteratorNormalCompletion3 = (_step3 = _iterator3.next()).done); _iteratorNormalCompletion3 = true) {
                            var inspire = _step3.value;

                            // If a saved record is found, replace it.
                            if (records[inspire]) mySpiresSearch.records[inspire] = new mySpires_Record(records[inspire]);

                            // Draw the mySpires bar.
                            new mySpires_Bar(mySpiresSearch.records[inspire], $("#search-result-" + inspire));
                        }
                    } catch (err) {
                        _didIteratorError3 = true;
                        _iteratorError3 = err;
                    } finally {
                        try {
                            if (!_iteratorNormalCompletion3 && _iterator3.return) {
                                _iterator3.return();
                            }
                        } finally {
                            if (_didIteratorError3) {
                                throw _iteratorError3;
                            }
                        }
                    }

                    resolve();
                }).catch(reject);
            }).catch(reject);

            /*
            // Do some forward planning
            mySpiresSearch.load(searchQuery, jrec + rg);
            mySpiresSearch.load(searchQuery, jrec - rg);
            for(let record of records) {
                mySpiresSearch.load("find citedby recid " + record.inspire);
                if(record.citation_count)
                    mySpiresSearch.load("find refersto recid " + record.inspire);
            }
            */
        }).catch(reject);
    });
    return true;
};

mySpiresSearch.change = function () {
    var searchQuery = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : "";
    var jrec = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;

    if (mySpiresSearch.set(searchQuery, jrec)) {
        mySpiresSearch.busy.then(function () {
            searchQuery = mySpiresSearch.searchQuery;
            window.history.pushState({ search: searchQuery, jrec: jrec }, "", "/?q=" + searchQuery);
        });
        return true;
    } else return false;
};

window.onpopstate = function (e) {
    var searchQuery = void 0;
    var jrec = void 0;
    if (e.state && e.state.search) {
        searchQuery = e.state.search;
        jrec = e.state.jrec;
    } else {
        searchQuery = mySpiresSearch.firstSearchQuery;
        jrec = 1;
    }

    mySpiresSearch.set(searchQuery, jrec);
};
//# sourceMappingURL=index.js.map