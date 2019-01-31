"use strict";

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var InspireRecords = function () {
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
    function InspireRecords(query) {
        var _this = this;

        var xopts = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

        _classCallCheck(this, InspireRecords);

        this.records = [];

        this.boldAuthor = xopts.boldAuthor;
        if (this.boldAuthor && this.boldAuthor.split(",").length === 2) {
            var temp = this.boldAuthor.split(",");
            this.boldAuthor = temp[1].trim() + " " + temp[0].trim();
        }

        this.busy = new Promise(function (resolve, reject) {
            RefSpires.fetch(query, xopts).then(function (results) {
                this.records = [];
                var _iteratorNormalCompletion = true;
                var _didIteratorError = false;
                var _iteratorError = undefined;

                try {
                    for (var _iterator = results.records[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
                        var record = _step.value;

                        this.records.push(new InspireRecord(record));
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

                this.totResults = results.totResults;
                resolve();
            }.bind(_this)).catch(reject);
        });
    }

    _createClass(InspireRecords, [{
        key: "referenceList",
        value: function referenceList() {
            var list = [];
            var _iteratorNormalCompletion2 = true;
            var _didIteratorError2 = false;
            var _iteratorError2 = undefined;

            try {
                for (var _iterator2 = this.records[Symbol.iterator](), _step2; !(_iteratorNormalCompletion2 = (_step2 = _iterator2.next()).done); _iteratorNormalCompletion2 = true) {
                    var record = _step2.value;

                    var author = record.author;
                    if (this.boldAuthor) author = author.replace(this.boldAuthor, "<strong>" + this.boldAuthor + "</strong>");

                    var ref = author + ", <em>" + record.title + ",</em> " + "[<a href='https://arxiv.org/abs/" + record.arxiv + "'>arXiv:" + record.arxiv + "</a>]";

                    list.push(ref);
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

            return list;
        }
    }]);

    return InspireRecords;
}();

var InspireRecord = function InspireRecord(query) {
    var _this2 = this;

    var xopts = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

    _classCallCheck(this, InspireRecord);

    if (typeof query === "string") {
        this.busy = new Promise(function (resolve, reject) {
            RefSpires.fetch(query, xopts).then(function (results) {
                this.data = results.records[0];
                resolve();
            }.bind(_this2)).catch(reject);
        });
    } else {
        this.data = query;
        this.busy = Promise.resolve();
    }

    this.busy.then(function () {
        var keys = Object.keys(_this2.data);
        var _iteratorNormalCompletion3 = true;
        var _didIteratorError3 = false;
        var _iteratorError3 = undefined;

        try {
            for (var _iterator3 = keys[Symbol.iterator](), _step3; !(_iteratorNormalCompletion3 = (_step3 = _iterator3.next()).done); _iteratorNormalCompletion3 = true) {
                var key = _step3.value;

                _this2[key] = _this2.data[key];
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
    });
};

var RefSpires = function () {
    function RefSpires() {
        _classCallCheck(this, RefSpires);
    }

    _createClass(RefSpires, null, [{
        key: "load",
        value: function load() {
            var opts = this.opts;
            if (!opts) opts = {};

            $(".inspireList:not(.loaded)").each(function () {
                var query = $(this).attr("data-query");
                var xopts = $(this).attr("data-opts");
                if (xopts) {
                    xopts = JSON.parse(xopts);
                    var keys = Object.keys(xopts);
                    var _iteratorNormalCompletion4 = true;
                    var _didIteratorError4 = false;
                    var _iteratorError4 = undefined;

                    try {
                        for (var _iterator4 = keys[Symbol.iterator](), _step4; !(_iteratorNormalCompletion4 = (_step4 = _iterator4.next()).done); _iteratorNormalCompletion4 = true) {
                            var key = _step4.value;

                            opts[key] = xopts[key];
                        }
                    } catch (err) {
                        _didIteratorError4 = true;
                        _iteratorError4 = err;
                    } finally {
                        try {
                            if (!_iteratorNormalCompletion4 && _iterator4.return) {
                                _iterator4.return();
                            }
                        } finally {
                            if (_didIteratorError4) {
                                throw _iteratorError4;
                            }
                        }
                    }
                }

                var inspireRecords = new InspireRecords(query, opts);

                inspireRecords.busy.then(function () {
                    var refList = inspireRecords.referenceList();
                    var i = 0;
                    var _iteratorNormalCompletion5 = true;
                    var _didIteratorError5 = false;
                    var _iteratorError5 = undefined;

                    try {
                        for (var _iterator5 = refList[Symbol.iterator](), _step5; !(_iteratorNormalCompletion5 = (_step5 = _iterator5.next()).done); _iteratorNormalCompletion5 = true) {
                            var ref = _step5.value;

                            var itemId = "";
                            if (opts.itemIdPrefix) {
                                itemId = opts.itemIdPrefix + "-" + i++;
                            }
                            var li = "<li id='" + itemId + "' class='" + opts.itemClasses + "'>" + ref + ".</li>";
                            $(this).append(li);
                        }
                    } catch (err) {
                        _didIteratorError5 = true;
                        _iteratorError5 = err;
                    } finally {
                        try {
                            if (!_iteratorNormalCompletion5 && _iterator5.return) {
                                _iterator5.return();
                            }
                        } finally {
                            if (_didIteratorError5) {
                                throw _iteratorError5;
                            }
                        }
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

    }, {
        key: "fetch",
        value: function fetch(query) {
            var xopts = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};

            var opts = {
                p: query + " and ac 1->10",
                sf: "earliestdate",
                of: "xm"
            };

            if (xopts.sf) opts.sf = xopts.sf; // If 'sort format' is provided, override.
            if (xopts.so) opts.so = xopts.so; // If 'sort format' is provided, override.
            if (xopts.rg) opts.rg = xopts.rg;
            if (xopts.jrec) opts.jrec = xopts.jrec;

            if (!xopts.fields) xopts.fields = "arxiv,bibkey,title,author,date";
            if (xopts.addFields) xopts.fields = xopts.fields + "," + xopts.addFields;

            var otArray = ["909"];
            var fieldArray = xopts.fields.split(",");
            var _iteratorNormalCompletion6 = true;
            var _didIteratorError6 = false;
            var _iteratorError6 = undefined;

            try {
                for (var _iterator6 = fieldArray[Symbol.iterator](), _step6; !(_iteratorNormalCompletion6 = (_step6 = _iterator6.next()).done); _iteratorNormalCompletion6 = true) {
                    var field = _step6.value;

                    switch (field.trim()) {
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
            } catch (err) {
                _didIteratorError6 = true;
                _iteratorError6 = err;
            } finally {
                try {
                    if (!_iteratorNormalCompletion6 && _iterator6.return) {
                        _iterator6.return();
                    }
                } finally {
                    if (_didIteratorError6) {
                        throw _iteratorError6;
                    }
                }
            }

            opts.ot = otArray.join();

            return new Promise(function (resolve, reject) {
                $.ajax({
                    type: "GET",
                    url: "https://inspirehep.net/search",
                    data: opts,
                    success: function success(results) {
                        var records = [];
                        $(results).find("record").each(function () {
                            if ($(this).find("[tag='909'] [code='p']").html() !== "INSPIRE:HEP") return true;

                            var record = {};

                            // Inspire ID
                            record.inspire = $(this).find("[tag='001']").html();

                            // Identifiers
                            var rawArxiv = void 0,
                                rawInspire = void 0,
                                rawSpires = void 0;
                            $(this).find("[tag='035']").each(function () {
                                if ($(this).find("[code='9']").html() === "arXiv") {
                                    rawArxiv = $(this).find("[code='a']").html();
                                } else if ($(this).find("[code='9']").html() === "INSPIRETeX") {
                                    rawInspire = $(this).find("[code='a']").html();
                                } else if ($(this).find("[code='9']").html() === "SPIRESTeX") {
                                    rawSpires = $(this).find("[code='a']").html();
                                }
                            });
                            if (rawArxiv) record.arxiv = rawArxiv.split(":").slice(-1)[0];
                            if (rawInspire) record.bibkey = rawInspire;else if (rawSpires) record.bibkey = rawSpires;

                            // Title
                            var title = $(this).find("[tag='245'] [code='a']").html();
                            if (title) record.title = title;

                            // Authors
                            var authorsInverted = [],
                                authors = [];
                            $(this).find("[tag='100'], [tag='700']").each(function () {
                                var authorInverted = $(this).find("[code='a']").html();
                                authorsInverted.push(authorInverted);
                                var temp = authorInverted.split(",");
                                if (temp.length > 1) authors.push(temp[1].trim() + " " + temp[0].trim());else authors.push(authorInverted);
                            });
                            if (authors.length > 0) {
                                record.authorsInverted = authorsInverted;
                                record.authors = authors;
                                record.author = authors.join(", ");
                            }

                            // Abstract
                            var abstract = $(this).find("[tag='520'] [code='a']").html();
                            if (abstract) record.abstract = abstract;

                            // Date
                            var date = $(this).find("[tag='269'] [code='c']").html();
                            if (!date) date = $(this).find("[tag='260'] [code='c']").html();
                            if (!date) date = $(this).find("[tag='502'] [code='d']").html();
                            if (date) {
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

                        var totResults = 0;
                        $(results).contents().filter(function () {
                            return this.nodeType === 8;
                        }).each(function (i, e) {
                            totResults = Number(e.nodeValue.split("Search-Engine-Total-Number-Of-Results:")[1].trim());
                        });

                        resolve({ records: records, totResults: totResults });
                    },
                    dataType: "xml",
                    error: reject
                });
            });
        }
    }]);

    return RefSpires;
}();
//# sourceMappingURL=refSpires.js.map