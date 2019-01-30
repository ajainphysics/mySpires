/**
 * Once a record has been obtained, this class designs the mySpires-bar and loads tags and comments into it.
 * It also associates required javascript functions to various DOM elements of the bar.
 * This class assumes that there is a "box" corresponding to the entire record on the page, location of which you will
 *  pass during the construction, and that the box contains an div with class .mySpires-bar.
 */
class mySpires_Bar {
    /**
     * @param {mySpires_Record|Object} record - The record to be loaded, can either be a processed mySpires_Record or a
     *                                          raw JSON object returned by the API.
     * @param {Object} [box] - jQuery location of the element corresponding to the record. Make sure that it contains a
     *                       div with class ".mySpires-bar" where the bar will be loaded.
     */
    constructor(record, box) {
        if (!record) this.record = new mySpires_Record();
        else if(record instanceof mySpires_Record) this.record = record;
        else this.record = new mySpires_Record(record);

        if(!box) box = $("body");

        this.box = box;
        this.bar = box.find(".mySpires-bar");

        this.bar.append(
            "<form class='mySpires-form'>" +
            "    <div class='toolbar'>" +
            "        <div class='tooltitle'>mySpires.</div> " +
            "        <div class='tags-wrapper'></div>" +
            "        <button type='button' class='save-button btn btn-sm btn-outline-success'>Save</button>" +
            "        <button type='button' class='restore-button btn btn-sm btn-outline-success'>Restore</button>" +
            "        <button type='button' class='erase-button btn btn-sm btn-outline-danger'>Erase</button>" +
            "        <div class='rot-dots edit-button'>" +
            "            <div class='rot-dots-wrapper'>" +
            "                <div class='ball'></div>" +
            "                <div class='ball'></div>" +
            "                <div class='ball'></div>" +
            "            </div>" +
            "        </div>" +
            "    </div>" +
            "    <p class='paper-comments'></p>" +
            "    <div class='form-input'>" +
            "        <input class='tags-input form-control' name='tags' placeholder='Tags separated with comma'>" +
            "        <textarea name='comments' placeholder='Comments' rows='2' class='comments-input form-control'></textarea>" +
            "        <p class='text-danger tags-warning'>Tags can only contain alphanumeric characters, spaces, and hyphens, along with forward slash to implement directory structure. Use comma to separate multiple tags.</p>" +
            "        <div class='form-buttons'>" +
            "            <button class='submit-button btn btn-outline-success btn-sm'>Save</button>" +
            "            <button type='reset' class='cancel-button btn btn-outline-warning btn-sm'>Cancel</button>" +
            "            <button type='button' class='remove-button btn btn-sm btn-outline-danger'>Remove</button>" +
            "        </div>" +
            "    </div>" +
            "</form>"
        );

        this.tags = this.bar.find(".tags-wrapper");
        this.comments = this.bar.find(".paper-comments");

        this.tagsWarning = this.bar.find(".tags-warning");

        this.editButton = this.bar.find(".edit-button");
        this.saveButton = this.bar.find(".save-button");
        this.restoreButton = this.bar.find(".restore-button");
        this.eraseButton = this.bar.find(".erase-button");
        this.removeButton = this.bar.find(".remove-button");

        this.form = this.bar.find(".mySpires-form");
        // this.formButtons = this.bar.find(".form-buttons");
        this.formInput = this.bar.find(".form-input");

        // this.submitButton = this.bar.find(".submit-button");
        this.cancelButton = this.bar.find(".cancel-button");
        this.newTags = this.bar.find(".tags-input");
        this.newComments = this.bar.find(".comments-input");


        /* ============================== Edit Form ============================== */

        // This provides an auto-completion library for tags.
        this.awesomeplete = new Awesomplete(this.newTags.get(0));

        // This nice function adds the tag every time comma is pressed in newTags field.
        // You will still need to explicitly save though, the adding is only temporary.
        this.newTags.on("keyup", (e) => {
            if(this.mode !== "edit") return; // The following should only be done in edit mode.
            if(this.validateNewTags()) {
                if (e.keyCode === 188) { // KeyCode For comma is 188
                    this.addTags(this.newTags.val()).newTags.val("");
                }
            }
        });

        this.newComments.on("keypress", (e) => {
            if(this.mode !== "edit") return; // The following should only be done in edit mode.
            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                this.form.submit();
            }
        });

        // On submitting the edit form, the record will be updated to new values.
        // Tags and comments fields will be updated, and viewMode will be turned on.
        this.form.submit((e) => {
            e.preventDefault();
            this.record.busy.then(() => {
                if(this.validateNewTags()) {
                    this.addTags(this.newTags.val()).newTags.val(""); // Add residual tags and reset input.
                    this.record.comments = this.newComments.val().trim(); // Add comments
                    this.comments.html(this.record.comments); // Reset comments input
                    this.save(); // Save and turn on the view mode.
                }
            });
        });

        // ============================== Editing Buttons ============================== //

        this.saveButton.click((e) => {
            e.preventDefault();
            this.save();
        });

        this.restoreButton.click((e) => {
            e.preventDefault();
            this.save();
        });

        this.removeButton.click((e) => {
            e.preventDefault();
            this.remove();
        });

        this.eraseButton.click((e) => {
            e.preventDefault();
            this.erase();
        });

        this.editButton.click((e) => {
            e.preventDefault();
            if(!this.editButton.hasClass("busy")) {
                this.mode = "edit";
                this.refreshValues();
            }
        });

        this.onupdate = false;

        this.refreshValues();

    }

    validateNewTags() {
        if(/^[ a-z0-9-,\/]*$/i.test(this.newTags.val())) {
            this.newTags.removeClass("is-invalid");
            this.tagsWarning.hide();
            return true;
        }

        this.newTags.addClass("is-invalid");
        this.tagsWarning.show();
        return false;
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
    refreshValues(record) {
        if (record) {
            if(record instanceof mySpires_Record) this.record = record;
            else this.record = new mySpires_Record(record);
        }

        mySpires.prepare().then(() => {
            this.awesomeplete.list = mySpires.taglist; // This one is using Awesomplete
        });

        this.record.busy.then(() => {
            if(!this.mode) this.mode = "view"; // If mode is not set, "view" will be assumed.

            // Backup the original tags.
            let tags = this.record.tags;
            if(tags) tags = tags.trim();
            else tags = "";

            // Start afresh - remove all the tags from the record, tags-wrapper and the classes in box.
            this.record.tags = "";
            this.tags.html("");
            this.box.removeClass(function (index, className) {
                return (className.match(/(^|\s)tag-\S+/g) || []).join(' ');
            });

            // Now add all the backed-up tags.
            if(tags === "") this.addTags("Untagged");
            else this.addTags(tags);

            // Reset the comments
            this.comments.html(this.record.comments);
            this.newComments.html(this.record.comments);

            // Set classes in box and set the mode.
            if(this.mode === "edit") {
                this.box.addClass("saved").removeClass("unsaved binned");
                this.editMode();
            } else if (this.record.status === "saved") {
                this.box.addClass("saved").removeClass("unsaved binned");
                this.viewMode();
            } else if (this.record.status === "binned") {
                this.box.addClass("binned").removeClass("saved unsaved");
                this.viewMode();
            } else {
                this.box.addClass("unsaved").removeClass("saved binned");
                this.viewMode();
            }

            this.bar.fadeIn("fast");
        });
    }

    /**
     * Activates the editMode of the box.
     * @return {mySpires_Bar} Returns for chaining.
     */
    editMode() {
        if(this.editButton.is(":visible")) this.editButton.fadeOut();
        if(this.comments.is(":visible")) this.comments.slideUp();

        if(this.formInput.is(":hidden")) {
            this.formInput.slideDown(function () {
                this.newTags.focus();
            }.bind(this));
        }

        let originalTags = this.record.tags,
            originalComments = this.record.comments;
        this.cancelButton.click(() => {
            this.mode = "view";
            this.record.tags = originalTags;
            this.record.comments = originalComments;
            this.refreshValues();
        });

        return this;
    }

    /**
     * Activates the viewMode of the box.
     * It is an internal function, only called by .refreshValues();
     * @return {mySpires_Bar} Returns for chaining.
     */
    viewMode() {
        this.tags.find(".delete-tag").hide();

        if(this.formInput.is(":visible")) this.formInput.slideUp();

        if(this.record.status === "saved") {
            if(this.editButton.is(":hidden")) this.editButton.fadeIn();
        } else {
            if(this.editButton.is(":visible")) this.editButton.fadeOut();
        }

        if(this.comments.is(":hidden")) this.comments.slideDown();

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
    save() {
        this.record.busy.then(() => {
            this.record.status = "saved";

            this.editButton.addClass("busy");

            this.mode = "view";
            this.refreshValues(); // Refresh the values.

            this.record.save().busy.then(() => {
                if (this.onupdate) this.onupdate();
                this.refreshValues(); // Refresh the values.
                this.editButton.removeClass("busy");
            });
        });
        return this;
    }

    /**
     * Tells the API to remove the record, but keeps the box.
     * @returns {mySpires_Bar} Returns for chaining.
     */
    remove() {
        this.editButton.addClass("busy");

        this.mode = "view";
        this.refreshValues(); // Refresh the values.

        this.record.remove().busy.then(() => {
            if (this.onupdate) this.onupdate();
            this.refreshValues(); // Refresh the values.
            this.editButton.removeClass("busy");
        });
        return this;
    }

    erase() {
        this.editButton.addClass("busy");

        this.mode = "view";
        this.refreshValues(); // Refresh the values.

        this.record.erase().busy.then(() => {
            if (this.onupdate) this.onupdate();
            this.refreshValues(); // Refresh the values.
            this.editButton.removeClass("busy");
        });
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
    tagExists(tag) {
        tag = tag.trim();
        let tagArray = $.map(this.record.tags.split(","), $.trim);
        return ($.inArray(tag, tagArray) !== -1);
    }

    /**
     * Removes a tag from the record.
     * Note that this does not save the record in the database. That needs to be done explicitly later.
     * @param {string} tag The tag to remove.
     * @returns {mySpires_Bar} Returns for chaining.
     */
    removeTag(tag) {
        tag = tag.trim();
        let tag_ = tag.replace(/ /g, "_").replace(/\//g, "__");

        // Go through the current tags and remove if match found.
        let tagArray = $.map(this.record.tags.split(","), $.trim);
        tagArray = jQuery.grep(tagArray, function (value) {
            return value !== tag;
        });
        this.record.tags = tagArray.join(", ");

        this.box.removeClass("tag-" + tag_);
        this.tags.children(".paper-tag-" + tag_).remove();

        if(tagArray.length === 0) this.addTags("Untagged");

        return this;
    }

    /**
     * Adds a tag to the record.
     * Note that this does not save the record in the database. That needs to be done explicitly later.
     * @param {string} tags - The tag to add.
     * @returns {mySpires_Bar} Returns for chaining
     */
    addTags(tags) {
        tags = tags.replace(/[^ a-z0-9-,\/]+/ig, "");
        tags = tags.split(",")
            .map((t) => {
                return t.split("/").map((p) => {
                    return p.split(" ").filter(Boolean).join(" ");
                }).filter(Boolean).join("/");
            }).filter(Boolean).join(",");

        let tagArr = tags.split(",");

        // Purify the tagArr
        tagArr = $.grep($.map(tagArr, $.trim), function (n) {
            return n !== ""
        });

        if(tagArr.length !== 0) this.removeTag("Untagged");

        $.map(tagArr, (tag) => {
            tag = tag.replace(/,\s*$/, "").trim();
            let tag_ = tag.replace(/ /g, "_").replace(/\//g, "__");

            if (tag && !this.tagExists(tag)) {
                if (tag !== "Untagged") {
                    let tagArray = $.map(this.record.tags.split(","), $.trim);
                    tagArray.push(tag);
                    tagArray = $.grep(tagArray, function (n) {
                        return n !== ""
                    });
                    this.record.tags = tagArray.join(", ");
                }

                let tagLink = mySpires.server + "library.php?tag=" + tag,
                    tagParents = tag.split("/"),
                    tagLevel = tagParents.length - 1,
                    // tagName = tag.substr(tag.lastIndexOf("/") + 1);
                    tagName = tagParents[tagLevel];
                // if(tag === "Untagged") tagLink = "library.php";

                let tagBars = "";
                for(let i = 1; i <= tagLevel; i++) {
                    tagBars += "<span>&nbsp;</span>";
                }

                this.box.addClass("tag-" + tag_);

                if (tag === "Untagged") {
                    this.tags.append(
                        "<button type='button' class='btn btn-sm btn-outline-secondary paper-tag paper-tag-Untagged' title='Untagged' >Untagged</button> ");
                } else {
                    this.tags.append(
                        "<button type='button' " +
                        "  class='btn btn-sm btn-outline-dark paper-tag paper-tag-" + tag_ + "' " +
                        "  data-toggle='tooltip' data-placement='bottom' title='" + tag + "' >" +
                        "<span class='tag-level-bars'>" + tagBars + "</span>" +
                        tagName +
                        " <i class='fa fa-times delete-tag'></i></button> ");

                    if(this.mode === "edit") {
                        this.tags.find(".delete-tag").css("display", "inline-block");
                        this.tags.find(".paper-tag-" + tag_).off("click").on("click", (e) => {
                            e.preventDefault();
                            this.removeTag(tag);
                        });
                    } else {
                        this.tags.find(".paper-tag-" + tag_).off("click").on("click", function (e) {
                            e.preventDefault();
                            if(window.location.hostname === mySpires.hostname)
                                if(window.location.pathname === "/library.php") {
                                    let url = mySpires.server + "library.php?tag=" + encodeURIComponent(tag);
                                    history.pushState({tag: tag}, "", url);
                                    dispatchEvent(new PopStateEvent('popstate', {state: {tag: tag}}));
                                }
                                else window.location.href = tagLink;
                            else
                                window.open(tagLink, "_blank");
                        });
                    }
                }
            }

        });

        $(function () {
            $('[data-toggle="tooltip"]').each(function() {
                $(this).tooltip({
                    container: $(this)
                });
            })
        });

        return this;
    }
}