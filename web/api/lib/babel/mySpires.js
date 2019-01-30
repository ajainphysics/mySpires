"use strict";

var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/**
 * @file This file is part of the core mySpires-API. It defines useful mySpires classes which parallel the respective PHP classes in mySpires.php. They talk to each other via "/api" on mySpires server.
 *
 * @author Akash Jain
 */

var mySpires = function () {
    function mySpires() {
        _classCallCheck(this, mySpires);
    }

    _createClass(mySpires, null, [{
        key: "prepare",


        /**
         * Some properties might require a little bit of waiting. For that purpose, prepare mySpires with .prepare() and
         * use the promise to use prepared properties.
         * Note that subsequent calling of .prepare() will not prepare again, but will return the old promise.
         * So to be sure, prepare whenever you use a property which needs preparation.
         * @return {Promise} Resolves when prepared.
         */
        value: function prepare() {
            var _this = this;

            if (this.preparing === undefined) {
                this.preparing = new Promise(function (resolve) {
                    _this.api().then(function (data) {
                        this.user = data.user;
                        this.tagauthors = data.tagauthors;
                        this.taglist = Object.keys(this.tagauthors);
                        this.tagopts = data.tagopts;
                        resolve();
                    }.bind(_this));
                });
            }
            return this.preparing;
        }

        /**
         * Returns path to the mySpires-API server.
         * @returns {string} - Path to mySpires API server
         */

    }, {
        key: "api",


        /**
         * Calls a file from the mySpires-API and returns the response.
         * @param {object} [args] - Options to pass. To be read as $_POST array by the API
         * @returns {Promise} - A promise which resolves to the response from the API
         */
        value: function api(args) {
            var _this2 = this;

            return new Promise(function (resolve, reject) {
                $.ajax({
                    type: "POST",
                    url: _this2.server + "api/",
                    data: args,
                    success: function (response) {
                        if (response) {
                            if (response.maintenance) {
                                $.post(this.server + "api/maintenance.php");
                                resolve(response.data);
                            } else resolve(response);
                        } else reject({
                            mySpires: "API sent an inappropriate reply!",
                            args: args
                        });
                    }.bind(_this2),
                    dataType: "json",
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function error() {
                        reject({
                            mySpires: "API did not reply!",
                            args: args
                        });
                    }
                });
            });
        }
    }, {
        key: "tag",
        value: function tag(_tag) {
            var _this3 = this;

            return new Promise(function (resolve) {
                _this3.api({ tag: _tag }).then(function (results) {
                    resolve(new mySpires_Records(results));
                }).catch(console.log);
            });
        }
    }, {
        key: "timeframe",
        value: function timeframe(_timeframe) {
            var _this4 = this;

            return new Promise(function (resolve) {
                _this4.api({ timeframe: _timeframe }).then(function (results) {
                    resolve(new mySpires_Records(results));
                }).catch(console.log);
            });
        }
    }, {
        key: "server",
        get: function get() {
            // return "http://localhost/~akash/mySpires/server/"; // TODO: Local
            // return "https://www.maths.dur.ac.uk/~tjtm88/mySpires/";
            return "https://myspires.ajainphysics.com/";
            // return "/"; // Experimental
        }
    }]);

    return mySpires;
}();

/**
 * This class parallels the PHP.mySpires_Record class. It interprets the result sent out by the mySpires API and attaches essential manipulation tools.
 *
 * While using mySpires_Record, always wait for the .busy promise to resolve before accessing the values. In built functions should automatically take this into account, by adding operations to a pending queue. For example after a bunch of operations of the kind
 * <code>
 *     $record = (new mySpires_record).load("1", "id").set("comments", "jain").save();
 * </code>
 * you should wait to access properties
 * <code>
 *     $record.busy.then(function() {
 *         console.log($record.comments);
 *     });
 * </code>
 *
 * @constructor
 */


var mySpires_Record = function () {
    /**
     * The only way to construct a mySpires_Record object is via the results sent out from the mySpires servers. You can either directly feed in the JSON sub-element corresponding to a result, or feed in parameters to perform the search.
     */
    function mySpires_Record(query, field, source) {
        _classCallCheck(this, mySpires_Record);

        this.busy = Promise.resolve();

        switch (typeof query === "undefined" ? "undefined" : _typeof(query)) {
            case "string":
                this.load(query, field, source);
                break;
            case "object":
                this.data(query);
        }
    }

    /**
     * Loads a PHP.mySpires_Record object sent by the mySpires server.
     * @param {Object} record - Record object form mySpires server.
     */


    _createClass(mySpires_Record, [{
        key: "data",
        value: function data(record) {
            if (!record) return;

            this.id = record.id;
            this.inspire = record.inspire;
            this.arxiv = record.arxiv;
            this.arxiv_v = record.arxiv_v;
            this.bibkey = record.bibkey;
            this.title = record.title;
            this.author = record.author;
            this.abstract = record.abstract;
            this.published = record.published;
            this.tags = record.tags;
            this.comments = record.comments;
            this.updated = record.updated;
            this.status = record.status;
        }

        /**
         * Load data from the mySpires server.
         * @param {string} query - Query to be sent.
         * @param {string} [field] - Field of query. Default value is "id".
         * @param {string} source - Source of query. Default value is "".
         */

    }, {
        key: "load",
        value: function load(query) {
            var _this5 = this;

            var field = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : "id";
            var source = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : "";

            query = query.toString().split(",")[0].trim(); // TODO: Fallback?

            // Continue after the current task has finished.
            this.busy = new Promise(function (resolve, reject) {
                _this5.busy.then(function () {
                    mySpires.api({ q: query, field: field, source: source }).then(function (results) {
                        var result = results[query];
                        // If the result does not exist, attach the query to it so that it can be saved later.
                        if (!result) {
                            result = {};
                            result[field] = query;
                        }
                        this.data(result);
                        resolve();
                    }.bind(this)).catch(reject);
                }.bind(_this5));
            });
            return this;
        }

        /**
         * This function sets a property after the queue of operations has finished.
         * Always use this function to set properties, to avoid any clashes with pending ajax operations.
         * @param {string} property - Property to change.
         * @param {string} value - New value of the property.
         * @returns {mySpires_Record} - Returns for chaining.
         */

    }, {
        key: "set",
        value: function set(property, value) {
            this.busy.then(function () {
                this[property] = value;
            }.bind(this));
            return this;
        }

        /**
         * Saves or updates a record in user's library.
         * @returns {mySpires_Record} Returns for chaining.
         */

    }, {
        key: "save",
        value: function save() {
            var _this6 = this;

            // Set to busy again
            this.busy = new Promise(function (resolve, reject) {
                _this6.busy.then(function () {
                    // Continue after last task has finished.
                    var opts = void 0;
                    if (this.id) opts = { save: this.id, field: "id" };else if (this.inspire) opts = { save: this.inspire, field: "inspire" };else if (this.arxiv) opts = { save: this.arxiv, field: "arxiv" };else {
                        reject("No identifier found to save the record.");
                        return;
                    }

                    opts.tags = this.tags;
                    opts.comments = this.comments;

                    mySpires.api(opts).then(function (results) {
                        this.data(results[opts.save]);
                        resolve();
                    }.bind(this)).catch(reject);
                }.bind(_this6));
            });
            return this;
        }

        /**
         * Removes an entry from user's library.
         * @returns {mySpires_Record} Returns for chaining.
         */

    }, {
        key: "remove",
        value: function remove() {
            var _this7 = this;

            this.busy = new Promise(function (resolve) {
                _this7.busy.then(function () {
                    // Continue after last task has finished.
                    if (this.id) {
                        mySpires.api({
                            remove: this.id,
                            field: "id"
                        }).then(function (results) {
                            this.data(results[this.id]);
                            resolve();
                        }.bind(this)).catch(console.log);
                    }
                }.bind(_this7));
            });
            return this;
        }
    }]);

    return mySpires_Record;
}();

var mySpires_Records =
/**
 *
 * @param {Object} records
 */
function mySpires_Records(records) {
    _classCallCheck(this, mySpires_Records);

    var _iteratorNormalCompletion = true;
    var _didIteratorError = false;
    var _iteratorError = undefined;

    try {
        for (var _iterator = Object.keys(records)[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
            var key = _step.value;

            this[key] = new mySpires_Record(records[key]);
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
};
//# sourceMappingURL=mySpires.js.map