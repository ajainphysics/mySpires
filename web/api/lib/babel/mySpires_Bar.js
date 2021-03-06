"use strict";

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/**
 * Once a record has been obtained, this class designs the mySpires-bar and loads tags and comments into it.
 * It also associates required javascript functions to various DOM elements of the bar.
 * This class assumes that there is a "box" corresponding to the entire record on the page, location of which you will
 *  pass during the construction, and that the box contains an div with class .mySpires-bar.
 */
var mySpires_Bar = function () {
    /**
     * @param {mySpires_Record|Object} record - The record to be loaded, can either be a processed mySpires_Record or a
     *                                          raw JSON object returned by the API.
     * @param {Object} [box] - jQuery location of the element corresponding to the record. Make sure that it contains a
     *                       div with class ".mySpires-bar" where the bar will be loaded.
     */
    function mySpires_Bar(record, box) {
        _classCallCheck(this, mySpires_Bar);

        if (!record) this.record = new mySpires_Record();else if (record instanceof mySpires_Record) this.record = record;else this.record = new mySpires_Record(record);

        if (!box) box = $("body");

        this.box = box;
        this.bar = box.find(".mySpires-bar");

        this.bar.append("<form class='mySpires-form'>" + "    <div class='toolbar'>" + "        <div class='tooltitle'>mySpires.</div> " + "        <div class='tags-wrapper'></div>" + "        <button type='button' class='save-button btn btn-sm btn-outline-success'>Save</button>" + "        <div class='rot-dots edit-button'>" + "            <div class='rot-dots-wrapper'>" + "                <div class='ball'></div>" + "                <div class='ball'></div>" + "                <div class='ball'></div>" + "            </div>" + "        </div>" + "    </div>" + "    <p class='paper-comments'></p>" + "    <div class='form-input'>" + "        <input class='tags-input form-control' name='tags' placeholder='Add a Tag...'>" + "        <textarea name='comments' placeholder='Comments' rows='2' class='comments-input form-control'></textarea>" + "        <div class='form-buttons'>" + "            <button class='submit-button btn btn-outline-success btn-sm'>Save</button>" + "            <button type='reset' class='cancel-button btn btn-outline-warning btn-sm'>Cancel</button>" + "            <button type='button' class='remove-button btn btn-sm btn-outline-danger'>Remove</button>" + "        </div>" + "    </div>" + "</form>");

        this.tags = this.bar.find(".tags-wrapper");
        this.comments = this.bar.find(".paper-comments");

        this.editButton = this.bar.find(".edit-button");
        this.saveButton = this.bar.find(".save-button");
        // this.deleteButton = this.bar.find(".delete-button");
        this.removeButton = this.bar.find(".remove-button");

        this.form = this.bar.find(".mySpires-form");
        this.formButtons = this.bar.find(".form-buttons");
        this.formInput = this.bar.find(".form-input");

        this.submitButton = this.bar.find(".submit-button");
        this.cancelButton = this.bar.find(".cancel-button");
        this.newTags = this.bar.find(".tags-input");
        this.newComments = this.bar.find(".comments-input");

        /* ============================== Edit Form ============================== */

        // This provides an auto-completion library for tags.
        mySpires.prepare().then(function () {
            // this.newTags.autocomplete({source: entryTags});
            new Awesomplete(this.newTags.get(0)).list = mySpires.taglist; // This one is using Awesomplete
        }.bind(this));

        // This nice function adds the tag every time comma is pressed in newTags field.
        // You will still need to explicitly save though, the adding is only temporary.
        this.newTags.on("keyup", function (e) {
            if (this.mode !== "edit") return; // The following should only be done in edit mode.
            if (e.keyCode === 188) {
                // KeyCode For comma is 188
                this.addTags(this.newTags.val()).newTags.val("");
            }
        }.bind(this));

        this.newComments.on("keypress", function (e) {
            if (this.mode !== "edit") return; // The following should only be done in edit mode.
            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                this.form.submit();
            }
        }.bind(this));

        // On submitting the edit form, the record will be updated to new values.
        // Tags and comments fields will be updated, and viewMode will be turned on.
        this.form.submit(function (e) {
            e.preventDefault();
            this.record.busy.then(function () {
                this.addTags(this.newTags.val()).newTags.val(""); // Add residual tags and reset input.
                this.record.comments = this.newComments.val().trim(); // Add comments
                this.comments.html(this.record.comments); // Reset comments input
                this.save(); // Save and turn on the view mode.
            }.bind(this));
        }.bind(this));

        // ============================== Editing Buttons ============================== //

        this.saveButton.click(function (e) {
            e.preventDefault();
            this.save();
        }.bind(this));

        this.removeButton.click(function (e) {
            e.preventDefault();
            this.remove();
        }.bind(this));

        this.editButton.click(function (e) {
            e.preventDefault();
            if (!this.editButton.hasClass("busy")) {
                this.mode = "edit";
                this.refreshValues();
            }
        }.bind(this));

        this.onupdate = false;

        this.refreshValues();
    }

    /* ============================== Display Modes ============================== //
    //
    // The following methods toggle edit and view modes.
    */

    /**
     * Sets the DOM valus to record values.
     * @param {mySpires_Record|Object} [record] If record is passed, the old record will be replaced before refresh.
     *                                          If record is not mySpires_Record, raw mySpires_Record will be assumed.
     */


    _createClass(mySpires_Bar, [{
        key: "refreshValues",
        value: function refreshValues(record) {
            if (record) {
                if (record instanceof mySpires_Record) this.record = record;else this.record = new mySpires_Record(record);
            }

            this.record.busy.then(function () {
                if (!this.mode) this.mode = "view"; // If mode is not set, "view" will be assumed.

                // Backup the original tags.
                var tags = this.record.tags;
                if (tags) tags = tags.trim();else tags = "";

                // Start afresh - remove all the tags from the record, tags-wrapper and the classes in box.
                this.record.tags = "";
                this.tags.html("");
                this.box.removeClass(function (index, className) {
                    return (className.match(/(^|\s)tag-\S+/g) || []).join(' ');
                });

                // Now add all the backed-up tags.
                if (tags === "") this.addTags("Untagged");else this.addTags(tags);

                // Reset the comments
                this.comments.html(this.record.comments);
                this.newComments.html(this.record.comments);

                // Set classes in box and set the mode.
                if (this.mode === "edit") {
                    this.box.addClass("saved").removeClass("unsaved");
                    this.editMode();
                } else if (this.record.status === "saved") {
                    this.box.addClass("saved").removeClass("unsaved");
                    this.viewMode();
                } else {
                    this.box.addClass("unsaved").removeClass("saved");
                    this.viewMode();
                }

                this.bar.fadeIn("fast");
            }.bind(this));
        }

        /**
         * Activates the editMode of the box.
         * @return {mySpires_Bar} Returns for chaining.
         */

    }, {
        key: "editMode",
        value: function editMode() {
            if (this.editButton.is(":visible")) this.editButton.fadeOut();
            if (this.comments.is(":visible")) this.comments.slideUp();

            if (this.formInput.is(":hidden")) {
                this.formInput.slideDown(function () {
                    this.newTags.focus();
                }.bind(this));
            }

            var originalTags = this.record.tags,
                originalComments = this.record.comments;
            this.cancelButton.click(function () {
                this.mode = "view";
                this.record.tags = originalTags;
                this.record.comments = originalComments;
                this.refreshValues();
            }.bind(this));

            return this;
        }

        /**
         * Activates the viewMode of the box.
         * It is an internal function, only called by .refreshValues();
         * @return {mySpires_Bar} Returns for chaining.
         */

    }, {
        key: "viewMode",
        value: function viewMode() {
            this.tags.find(".delete-tag").hide();

            if (this.formInput.is(":visible")) this.formInput.slideUp();

            if (this.record.status === "saved") {
                if (this.editButton.is(":hidden")) this.editButton.fadeIn();
            } else {
                if (this.editButton.is(":visible")) this.editButton.fadeOut();
            }

            if (this.comments.is(":hidden")) this.comments.slideDown();

            // if (!this.record.tags.trim() && this.record.status === "saved") this.addTags("Untagged");

            return this;
        }

        /* ============================== Action Methods ============================== //
        //
        // The following methods deal with permanent actions such as save, delete, edit etc.
        */

        /**
         * Tells the API to save a record.
         * @returns {mySpires_Bar} Returns for chaining.
         */

    }, {
        key: "save",
        value: function save() {
            this.record.busy.then(function () {
                this.record.status = "saved";

                this.editButton.addClass("busy");

                this.mode = "view";
                this.refreshValues(); // Refresh the values.

                this.record.save().busy.then(function () {
                    if (this.onupdate) this.onupdate();
                    this.refreshValues(); // Refresh the values.
                    this.editButton.removeClass("busy");
                }.bind(this));
            }.bind(this));
            return this;
        }

        /**
         * Tells the API to remove the record, but keeps the box.
         * @returns {mySpires_Bar} Returns for chaining.
         */

    }, {
        key: "remove",
        value: function remove() {
            this.editButton.addClass("busy");

            this.mode = "view";
            this.refreshValues(); // Refresh the values.

            this.record.remove().busy.then(function () {
                if (this.onupdate) this.onupdate();
                this.refreshValues(); // Refresh the values.
                this.editButton.removeClass("busy");
            }.bind(this));
            return this;
        }

        /* ============================== DOM content Methods ============================== //
        //
        // The following methods deal with temporary manipulations.
        */

        /**
         * Checks if a tag exists in the record.
         * @param {string} tag The tag to test.
         * @returns {boolean} Returns true if tag exists, false otherwise.
         */

    }, {
        key: "tagExists",
        value: function tagExists(tag) {
            tag = tag.trim();
            var tagArray = $.map(this.record.tags.split(","), $.trim);
            return $.inArray(tag, tagArray) !== -1;
        }

        /**
         * Removes a tag from the record.
         * Note that this does not save the record in the database. That needs to be done explicitly later.
         * @param {string} tag The tag to remove.
         * @returns {mySpires_Bar} Returns for chaining.
         */

    }, {
        key: "removeTag",
        value: function removeTag(tag) {
            tag = tag.trim();
            var tag_ = tag.replace(/ /g, "_").replace(/\//g, "__");

            // Go through the current tags and remove if match found.
            var tagArray = $.map(this.record.tags.split(","), $.trim);
            tagArray = jQuery.grep(tagArray, function (value) {
                return value !== tag;
            });
            this.record.tags = tagArray.join(", ");

            this.box.removeClass("tag-" + tag_);
            this.tags.children(".paper-tag-" + tag_).remove();

            if (tagArray.length === 0) this.addTags("Untagged");

            return this;
        }

        /**
         * Adds a tag to the record.
         * Note that this does not save the record in the database. That needs to be done explicitly later.
         * @param {string|Array} tags - The tag to add.
         * @returns {mySpires_Bar} Returns for chaining
         */

    }, {
        key: "addTags",
        value: function addTags(tags) {
            var tagArr = void 0;
            if (typeof tags === "string") tagArr = tags.split(",");else tagArr = tags;

            // Purify the tagArr
            tagArr = $.grep($.map(tagArr, $.trim), function (n) {
                return n !== "";
            });

            if (tagArr.length !== 0) this.removeTag("Untagged");

            $.map(tagArr, function (tag) {
                tag = tag.replace(/,\s*$/, "").trim();
                var tag_ = tag.replace(/ /g, "_").replace(/\//g, "__");

                if (tag && !this.tagExists(tag)) {
                    if (tag !== "Untagged") {
                        var tagArray = $.map(this.record.tags.split(","), $.trim);
                        tagArray.push(tag);
                        tagArray = $.grep(tagArray, function (n) {
                            return n !== "";
                        });
                        this.record.tags = tagArray.join(", ");
                    }

                    var tagLink = "library.php?tag=" + tag,
                        tagParents = tag.split("/"),
                        tagLevel = tagParents.length - 1,

                    // tagName = tag.substr(tag.lastIndexOf("/") + 1);
                    tagName = tagParents[tagLevel];
                    // if(tag === "Untagged") tagLink = "library.php";

                    var tagBars = "";
                    for (var i = 1; i <= tagLevel; i++) {
                        tagBars += "<span>&nbsp;</span>";
                    }

                    this.box.addClass("tag-" + tag_);

                    if (tag === "Untagged") {
                        this.tags.append("<button type='button' class='btn btn-sm btn-outline-secondary paper-tag paper-tag-Untagged' title='Untagged' >Untagged</button> ");
                    } else {
                        this.tags.append("<button type='button' " + "  class='btn btn-sm btn-outline-dark paper-tag paper-tag-" + tag_ + "' " + "  data-toggle='tooltip' data-placement='bottom' title='" + tag + "' >" + "<span class='tag-level-bars'>" + tagBars + "</span>" + tagName + " <i class='fa fa-times delete-tag'></i></button> ");

                        if (this.mode === "edit") {
                            this.tags.find(".delete-tag").css("display", "inline-block");
                            this.tags.find(".paper-tag-" + tag_).off("click").on("click", function (e) {
                                e.preventDefault();
                                this.removeTag(tag);
                            }.bind(this));
                        } else {
                            this.tags.find(".paper-tag-" + tag_).off("click").on("click", function (e) {
                                e.preventDefault();
                                window.location = tagLink;
                            });
                        }
                    }
                }
            }.bind(this));

            $(function () {
                $('[data-toggle="tooltip"]').each(function () {
                    $(this).tooltip({
                        container: $(this)
                    });
                });
            });

            return this;
        }
    }]);

    return mySpires_Bar;
}();
//# sourceMappingURL=mySpires_Bar.js.map