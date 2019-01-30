const mySpiresCite = {};
mySpiresCite.busy = Promise.resolve();

mySpiresCite.urgentBreak = function() {
    if(mySpiresCite.urgentBreakPromise) mySpiresCite.urgentBreakPromise();
    mySpiresCite.busy.then(function() {
        mySpiresCite.urgentFlag = false;
        mySpiresCite.urgent = new Promise((resolve) => {
            mySpiresCite.urgentBreakPromise = function() {
                resolve("urgent");
                mySpiresCite.urgentFlag = true;
                console.log("Breaking promises urgently!");
            }
        });
    });
};
mySpiresCite.urgentBreak();

$(function() {
    mySpiresCite.$searchBox = $("#welcome-search");
    mySpiresCite.$searchInput = mySpiresCite.$searchBox.find(".searchfield");
    mySpiresCite.$searchBarReset = mySpiresCite.$searchBox.find(".search-bar-reset");
    mySpiresCite.$searchBtn = mySpiresCite.$searchBox.find(".searchbtn");

    Object.defineProperties(mySpiresCite, {
        searchQuery: {
            get: function() {return mySpiresCite.$searchInput.val()},
            set: function(val) {mySpiresCite.$searchInput.val(val)}
        }
    });

    mySpiresCite.$searchBox.submit(function() {
        mySpiresCite.loadCitations();
    });

    mySpiresCite.$searchBarReset.click(function() {
        mySpiresCite.$searchInput.val("").focus();
    });

    mySpiresCite.firstSearchQuery = mySpiresCite.searchQuery;

    mySpiresCite.loadCitations();
});

/**
 * Loads a query string from INSPIRE into cache.
 * @param {string} searchQuery The query to be searched for on INSPIRE.
 * @param {int} jrec Where to start the results.
 * @return {boolean}
 */
mySpiresCite.loadCitations = function () {
    let userID = mySpiresCite.searchQuery;
    if(!userID) return false;
    let searchQuery = "find ea " + userID;
    let jrec = 1;
    let rg = 25;

    mySpiresCite.busy = new Promise((resolve, reject) => {mySpiresCite.busy.then(function() {
        // If urgent resolves, resolve the promise and move on.
        mySpiresCite.urgent.then(resolve);
        if (mySpiresCite.urgentFlag) return; // If urgent, abort!

        $(".busy-loader").show();
        mySpiresCite.$searchBtn.html("<i class='fa fa-spinner fa-spin'></i>");

        $("#cite-results").html("");

        let results = new InspireRecords(searchQuery, {rg: rg, jrec: jrec});
        results.busy.then(function () {
            let citationPromiseList = [];
            let citationList = [];
            let n = 0;

            for (let record of results.records) {
                let query = "find refersto recid " + record.inspire + " and d > today - 365 and not ea " + userID;
                let citations = new InspireRecords(query, {
                    rg: 250,
                    jrec: 1
                });
                citationPromiseList.push(citations.busy);

                citations.busy.then(function () {
                    for (let cite of citations.records) {
                        cite.refersto = record;
                        citationList.push(cite);
                    }
                });
            }

            Promise.all(citationPromiseList).then(function () {
                citationList.sort(function(a,b) {
                    return b.date - a.date;
                });

                let map = {};
                let counter = 0;
                let citationGroups = [];
                for(let cite of citationList) {
                    if(!map[cite.refersto.inspire]) {
                        map[cite.refersto.inspire] = counter++;
                        citationGroups[map[cite.refersto.inspire]] = [];
                    }
                    citationGroups[map[cite.refersto.inspire]].push(cite);
                }
                console.log(citationGroups);

                mySpiresCite.searchResults = $("#cite-results");

                for (let citationGroup of citationGroups) {
                    mySpiresCite.searchResults.append("<h6>" + citationGroup[0].refersto.title + "</h6>");

                    for (let cite of citationGroup) {
                        let monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                        let displayDate = monthNames[cite.date.getMonth()] + " " + cite.date.getFullYear();

                        let authorSurnames = $.map(cite.authors, function (a) {
                            let arr = a.split(" ");
                            return arr[arr.length - 1];
                        });

                        mySpiresCite.searchResults.append(
                            "<div class='cite-element'>" +
                            "<span class='published-date'>" + displayDate + "</span> " +
                            "<a href='https://inspirehep.net/record/" + cite.inspire + "'>" +
                            cite.title +
                            "</a>, " +
                            authorSurnames.join(", ") +
                            "</div>"
                        );
                    }
                    mySpiresCite.searchResults.append("<br>");
                }

                $(".busy-loader").hide();
                mySpiresCite.$searchBtn.html("Go");
            });

            // mySpiresCite.savedSearches[searchURI] = {
            //    totalResults: results.totResults,
            //    timestamp: (new Date()).getTime(),
            //    results: inspireArray
            // };

            resolve();
        }).catch(reject);


    }).catch(reject)});

    return true;
};