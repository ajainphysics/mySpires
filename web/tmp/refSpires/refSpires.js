"use strict";

class InspireRecords {
    /**
     *
     * @param {string} query The query to search at Inspire.
     * @param {Object} [xopts] Options.
     * @param {string} [xopts.boldAuthor] The author name to bold in reference lists.
     * @param {string} [xopts.sf] The sort format to use. [see INSPIRE-API]
     * @param {string} [xopts.so] The sort order to use. [see INSPIRE-API]
     * @param {number} [xopts.rg] Where to start search. [see INSPIRE-API]
     * @param {number} [xopts.jrec] The chunk of data to return. [see INSPIRE-API]
     * @param {string} [xopts.fields] Comma separated list of fields to search for.
     * @param {string} [xopts.addFields] Add fields to the default ones.
     */
    constructor(query, xopts = {}) {
        this.records = [];

        this.boldAuthor = xopts.boldAuthor;
        if(this.boldAuthor && this.boldAuthor.split(",").length === 2) {
            let temp = this.boldAuthor.split(",");
            this.boldAuthor = temp[1].trim() + " " + temp[0].trim();
        }

        this.busy = new Promise((resolve, reject) => {
            RefSpires.fetch(query, xopts).then(function (results) {
                this.records = [];
                for(let record of results.records) {
                    this.records.push(new InspireRecord(record));
                }
                this.totResults = results.totResults;
                resolve();
            }.bind(this)).catch(reject);
        });
    }

    referenceList() {
        let list = [];
        for(let record of this.records) {
            let author = record.author;
            if(this.boldAuthor)
                author = author
                    .replace(this.boldAuthor, "<strong>" + this.boldAuthor + "</strong>");

            let ref =
                author + ", <em>"
                + record.title + ",</em> "
                + "[<a href='https://arxiv.org/abs/" + record.arxiv + "'>arXiv:"
                + record.arxiv + "</a>]";

            list.push(ref);
        }

        return list;
    }
}


class InspireRecord {
    constructor(query, xopts = {}) {
        if(typeof query === "string") {
            this.busy = new Promise((resolve, reject) => {
                RefSpires.fetch(query, xopts).then(function (results) {
                    this.data = results.records[0];
                    resolve();
                }.bind(this)).catch(reject);
            });
        }
        else {
            this.data = query;
            this.busy = Promise.resolve();
        }

        this.busy.then(() => {
            let keys = Object.keys(this.data);
            for(let key of keys) {
                this[key] = this.data[key];
            }
        });
    }
}

class RefSpires {
    static load() {
        let opts = this.opts;
        if(!opts) opts = {};

        $(".inspireList:not(.loaded)").each(function() {
            let query = $(this).attr("data-query");
            let xopts = $(this).attr("data-opts");
            if(xopts) {
                xopts = JSON.parse(xopts);
                let keys = Object.keys(xopts);
                for(let key of keys) {
                    opts[key] = xopts[key];
                }
            }

            let inspireRecords = new InspireRecords(query, opts);

            inspireRecords.busy.then(function() {
                let refList = inspireRecords.referenceList();
                let i = 0;
                for(let ref of refList) {
                    let itemId = "";
                    if(opts.itemIdPrefix) {
                        itemId = opts.itemIdPrefix + "-" + i++;
                    }
                    let li = "<li id='"
                        + itemId
                        + "' class='" + opts.itemClasses + "'>" + ref + ".</li>";
                    $(this).append(li);
                }
            }.bind(this)).catch(console.log);

            $(this).addClass("loaded");
        });
    }

    /**
     * Fetches results from INSPIRE and returns an xml.
     * @param {string} query The query to search at Inspire.
     * @param {Object} [xopts] Options.
     * @param {string} [xopts.sf] The sort format to use. [see INSPIRE-API]
     * @param {string} [xopts.so] The sort order to use. [see INSPIRE-API]
     * @param {number} [xopts.rg] Where to start search. [see INSPIRE-API]
     * @param {number} [xopts.jrec] The chunk of data to return. [see INSPIRE-API]
     * @param {string} [xopts.fields] Comma separated list of fields to search for.
     * @param {string} [xopts.addFields] Add fields to the default ones.
     */
    static fetch(query, xopts = {}) {
        let opts = {
            p: query + " and ac 1->10",
            sf: "earliestdate",
            of: "xm"
        };

        if(xopts.sf) opts.sf = xopts.sf; // If 'sort format' is provided, override.
        if(xopts.so) opts.so = xopts.so; // If 'sort format' is provided, override.
        if(xopts.rg) opts.rg = xopts.rg;
        if(xopts.jrec) opts.jrec = xopts.jrec;

        if(!xopts.fields) xopts.fields = "arxiv,bibkey,title,author,date";
        if(xopts.addFields) xopts.fields = xopts.fields + "," + xopts.addFields;

        let otArray = ["909"];
        let fieldArray = xopts.fields.split(",");
        for(let field of fieldArray) {
            switch(field.trim()) {
                case "bibkey":
                case "arxiv":
                    otArray.push("035,");
                    break;
                case "title":
                    otArray.push("245,");
                    break;
                case "author":
                case "authors":
                    otArray.push("100,700,");
                    break;
                case "date":
                    otArray.push("269,260,502,");
                    break;
                case "abstract":
                    otArray.push("520,");
                    break;
            }
        }
        opts.ot = otArray.join();

        return new Promise((resolve, reject) => {
            $.ajax({
                type: "GET",
                url: "https://inspirehep.net/search",
                data: opts,
                success: function(results) {
                    let records = [];
                    $(results).find("record").each(function () {
                        if($(this).find("[tag='909'] [code='p']").html() !== "INSPIRE:HEP") return true;

                        let record = {};

                        // Inspire ID
                        record.inspire = $(this).find("[tag='001']").html();

                        // Identifiers
                        let rawArxiv, rawInspire, rawSpires;
                        $(this).find("[tag='035']").each(function() {
                            if($(this).find("[code='9']").html() === "arXiv") {
                                rawArxiv = $(this).find("[code='a']").html();
                            } else if($(this).find("[code='9']").html() === "INSPIRETeX") {
                                rawInspire = $(this).find("[code='a']").html();
                            } else if($(this).find("[code='9']").html() === "SPIRESTeX") {
                                rawSpires = $(this).find("[code='a']").html();
                            }
                        });
                        if(rawArxiv) record.arxiv = rawArxiv.split(":").slice(-1)[0];
                        if(rawInspire) record.bibkey = rawInspire;
                        else if(rawSpires) record.bibkey = rawSpires;

                        // Title
                        let title = $(this).find("[tag='245'] [code='a']").html();
                        if(title) record.title = title;

                        // Authors
                        let authorsInverted = [],
                            authors = [];
                        $(this).find("[tag='100'], [tag='700']").each(function() {
                            let authorInverted = $(this).find("[code='a']").html();
                            authorsInverted.push(authorInverted);
                            let temp = authorInverted.split(",");
                            if(temp.length > 1) authors.push(temp[1].trim() + " " + temp[0].trim());
                            else authors.push(authorInverted);
                        });
                        if(authors.length > 0) {
                            record.authorsInverted = authorsInverted;
                            record.authors = authors;
                            record.author = authors.join(", ");
                        }

                        // Abstract
                        let abstract = $(this).find("[tag='520'] [code='a']").html();
                        if(abstract) record.abstract = abstract;

                        // Date
                        let date = $(this).find("[tag='269'] [code='c']").html();
                        if(!date) date = $(this).find("[tag='260'] [code='c']").html();
                        if(!date) date = $(this).find("[tag='502'] [code='d']").html();
                        if(date) {
                            switch (date.split("-").length) {
                                case 1:
                                    date = date + "-01-01";
                                    break;
                                case 2:
                                    date = date + "-01";
                                    break;
                            }

                            record.published = date;
                            record.date = new Date(date);
                        }

                        records.push(record);
                    });

                    let totResults = 0;
                    $(results).contents().filter(function () {
                        return this.nodeType === 8;
                    }).each(function (i, e) {
                        totResults = Number(e.nodeValue.split("Search-Engine-Total-Number-Of-Results:")[1].trim());
                    });

                    resolve({records: records, totResults: totResults});
                },
                dataType: "xml",
                error: reject
            });
        })
    }
}
