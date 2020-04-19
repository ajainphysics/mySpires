/**
 * @file This file is part of the core mySpires-API. It defines useful mySpires classes which parallel the respective PHP classes in mySpires.php. They talk to each other via "/api" on mySpires server.
 *
 * @author Akash Jain
 *
 */

/**
 * @typedef {Object} taginfo
 * @property {array} surnames
 * @property {array} authors
 * @property {int} starred
 */

/**
 * @class
 *
 * @prop {[taginfo]} mySpires.tagsinfo
 */
class mySpires {

    /**
     * Some properties might require a little bit of waiting. For that purpose, prepare mySpires with .prepare() and
     * use the promise to use prepared properties.
     * Note that subsequent calling of .prepare() will not prepare again, but will return the old promise.
     * So to be sure, prepare whenever you use a property which needs preparation.
     * @return {Promise} Resolves when prepared.
     */
    static prepare(force = false) {
        if(this.preparing === undefined || force) {
            this.preparing = new Promise((resolve) => {
                this.api().then((data) => {
                    this.user = data.user;
                    this.tagsinfo = data.tagsinfo;
                    this.taglist = Object.keys(this.tagsinfo);
                    resolve();
                });
            });
        }
        return this.preparing;
    }

    static get is_plugin_content_script() {
        if(typeof browser !== 'undefined' && typeof browser.extension !== 'undefined') {
            if(typeof browser.extension.getBackgroundPage === 'undefined') return true;
            else if(browser.extension.getBackgroundPage() !== window) return true;
        }
        return false;
    }

    static get hostname() {
        if(location.host === "dev.myspires.ajainphysics.com") return location.host;
        return "myspires.ajainphysics.com"
    }

    /**
     * Returns path to the mySpires-API server.
     * @returns {string} - Path to mySpires API server
     */
    static get server() {
        return "https://" + this.hostname + "/";
    }

    static get content_server() {
        return "https://cdn.ajainphysics.com/myspires_content/"
    }

    /**
     * Calls a file from the mySpires-API and returns the response.
     * @param {object} [args] - Options to pass. To be read as $_POST array by the API
     * @returns {Promise} - A promise which resolves to the response from the API
     */
    static api(args) {
        // Tunnel through the background page if on a content script.
        if(this.is_plugin_content_script) return mySpires_Plugin.api(args);

        return new Promise((resolve, reject) => {
            $.ajax({
                type: "POST",
                url: this.server + "api/",
                data: args,
                success: function (response) {
                    if(response.maintenance) {
                        $.post(mySpires.server + "api/mysa.php");
                        resolve(response.data);
                    }
                    else resolve(response);
                },
                dataType: "json",
                xhrFields: {
                    withCredentials: true
                },
                error: function() {
                    reject({
                        mySpires: "API did not reply!",
                        args: args
                    });
                }
            });
        });
    }

    static tag(tag) {
        return new Promise((resolve) => {
            this.api({tag: tag}).then(function (results) {
                resolve(new mySpires_Records(results));
            }).catch(console.log);
        })
    }

    static timeframe(timeframe) {
        return new Promise((resolve) => {
            this.api({timeframe: timeframe}).then(function (results) {
                resolve(new mySpires_Records(results));
            }).catch(console.log);
        })
    }

    static history(range) {
        return new Promise((resolve) => {
            this.api({history: range}).then(function (results) {
                resolve({records: new mySpires_Records(results.data), total: results.total});
            }).catch(console.log);
        })
    }

    static bin(range) {
        return new Promise((resolve) => {
            this.api({bin: range}).then(function (results) {
                resolve({records: new mySpires_Records(results.data), total: results.total});
            }).catch(console.log);
        })
    }
}

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
class mySpires_Record {
    /**
     * The only way to construct a mySpires_Record object is via the results sent out from the mySpires servers. You can either directly feed in the JSON sub-element corresponding to a result, or feed in parameters to perform the search.
     */
    constructor(query, field, source) {
        this.busy = Promise.resolve();
        this.fields = ["id", "inspire", "arxiv", "ads"];

        switch(typeof(query)) {
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
    data(record) {
        if(!record) return;

        this.id = record.id;
        this.inspire = record.inspire;
        this.ads = record.ads;
        this.arxiv = record.arxiv;
        this.arxiv_v = record.arxiv_v;
        this.bibkey = record.bibkey;
        this.title = record.title;
        this.author = record.author;
        this.author_id = record.author_id;
        this.abstract = record.abstract;
        this.published = record.published;
        this.doi = record.doi;
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
    load(query, field = "id", source = "") {
        query = query.toString().split(",")[0].trim(); // TODO: Fallback?

        // Continue after the current task has finished.
        this.busy = new Promise((resolve, reject) => {
            this.busy.then(() => {
                mySpires.api({q: query, field: field, source: source }).then((results) => {
                    let result = results[query];
                    // If the result does not exist, attach the query to it so that it can be saved later.
                    if(!result) {
                        result = {};
                        result[field] = query;
                    }
                    this.data(result);
                    resolve();
                }).catch(reject);
            });
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
    set(property, value) {
        this.busy.then(function() {
            this[property] = value;
        }.bind(this));
        return this;
    }

    /**
     * Saves or updates a record in user's library.
     * @returns {mySpires_Record} Returns for chaining.
     */
    save() {
        // Set to busy again
        this.busy = new Promise((resolve, reject) => {
            this.busy.then(() => { // Continue after last task has finished.
                let opts;
                for(let field of this.fields) {
                    if(this[field]) {
                        opts = {save: this[field], field: field};
                        break;
                    }
                }
                if(!opts) {
                    reject("No identifier found to save the record.");
                    return;
                }

                opts.tags = this.tags;
                opts.comments = this.comments;

                mySpires.api(opts).then((results) => {
                    this.data(results[opts.save]);
                    resolve();
                }).catch(reject);
            });
        });
        return this;
    }

    /**
     * Removes an entry from user's library.
     * @returns {mySpires_Record} Returns for chaining.
     */
    remove() {
        this.busy = new Promise((resolve) => {
            this.busy.then(() => { // Continue after last task has finished.
                if(this.id) {
                    mySpires.api({
                        remove: this.id,
                        field: "id"
                    }).then((results) => {
                        this.data(results[this.id]);
                        resolve();
                    }).catch(console.log);
                }
            });
        });
        return this;
    }

    /**
     * Removes an entry from user's library.
     * @returns {mySpires_Record} Returns for chaining.
     */
    erase() {
        this.busy = new Promise((resolve) => {
            this.busy.then(() => { // Continue after last task has finished.
                if(this.id) {
                    mySpires.api({
                        erase: this.id,
                        field: "id"
                    }).then((results) => {
                        this.data(results[this.id]);
                        resolve();
                    }).catch(console.log);
                }
            });
        });
        return this;
    }
}

class mySpires_Records
{
    /**
     *
     * @param {Object} records
     */
    constructor(records) {
        for(let key of Object.keys(records)) {
            this[key] = new mySpires_Record(records[key]);
        }
    }
}