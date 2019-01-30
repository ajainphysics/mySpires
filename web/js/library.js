// TODO: Write a more robust tag, authors retrieval system.

function goto_tag(tag, replace = false) {
    if(!tag) tag = "";

    let url;
    if(!tag) url = mySpires.server + "library.php";
    else url = mySpires.server + "library.php?tag=" + encodeURIComponent(tag);

    if(replace) history.replaceState({tag: tag}, "", url);
    else history.pushState({tag: tag}, "", url);
    dispatchEvent(new PopStateEvent('popstate', {state: {tag: tag}}));
}

let load_library_counter = 0;
let tagParentAwesomeplete;

$(function() {
    tagParentAwesomeplete = new Awesomplete($("#edit-tag-parent").get(0))
});

function load_library(tag) {
    let load_library_instance = ++load_library_counter;

    let tag_;
    if(!tag) {
        tag = "";
        tag_ = "Untagged";
    }
    else {
        tag_ = tag.replace(/ /g, "_").replace(/\//g, "__");
    }

    let $busy_loader = $(".busy-loader").show();

    let $body = $("body"),
        $titleNav = $("#title-nav").hide(),
        $pageTitle = $("#page-title"),
        $heading = $("#page-title h2"),
        $breadcrumb = $("#page-breadcrumb"),
        $parentPageLink = $("#parent-page-link"),
        $starredTags = $(".starred-tags").html(""),
        $subtags = $(".subtags").html(""),
        $recordsTitle = $(".paper-boxes .records-header").hide(),
        $recordsHeading = $(".paper-boxes .records-header h3").html(""),
        $editTagForm = $("#edit-tag-form").trigger("reset").slideUp(),
        $editTagButton = $("#edit-tag-btn"),
        $editTagSaveButton = $("#edit-tag-save-btn"),
        $editTagResetButton = $("#edit-tag-reset-btn"),
        $editTagDeleteButton = $("#edit-tag-delete-btn"),
        $editTagParent = $("#edit-tag-parent").removeClass("is-invalid"),
        $editTagName = $("#edit-tag-name").removeClass("is-invalid"),
        $editTagDescription = $("#edit-tag-description"),
        $editTagExistsWarning = $("#edit-tag-exists-warning").hide(),
        $editTagNameInvalidWarning = $("#edit-tag-name-invalid-warning").hide(),
        $editTagParentInvalidWarning = $("#edit-tag-parent-invalid-warning").hide(),
        $tagDescription =  $("#tag-description").html("").hide();

    $body.removeClass(function (index, className) {
        return (className.match(/(^|\s)library-tag-\S+/g) || []).join(' ');
    });

    $(".paper-box").remove();

    mySpires.prepare().then(function() {
        let properties = mySpires.tagsinfo[tag];
        if(properties === undefined) {
            goto_tag("", true);
            return;
        }

        // Title and Breadcrumb

        if(!tag) {
            $body.addClass("library-tag-Untagged");
            $pageTitle.addClass("main-title");
            $heading.html("Library");
            $breadcrumb.html("");
            $parentPageLink.addClass("fa-hdd").removeClass("fa-chevron-left");

            $titleNav.css("display", "flex");
        }
        else {
            $body.addClass("library-tag-" + tag_);

            let tagEx = tag.split("/");

            let subtitleLink = "";
            let breadcrumb = "<a href='/library.php' data-tag=''>Library</a>";
            for(let t of tagEx) {
                if(subtitleLink !== "") subtitleLink = subtitleLink + "/";
                subtitleLink += t;
                breadcrumb += " / <a href='/library.php?tag=" + subtitleLink + "' data-tag='" + subtitleLink + "'>" + t + "</a>";
            }

            $pageTitle.removeClass("main-title");
            $heading.html(
                tagEx[tagEx.length - 1] + " <i id='tag-star' class='far fa-star'></i>"
            );
            $breadcrumb.html(breadcrumb);

            $breadcrumb.children("a").off("click").click(function(e) {
                e.preventDefault();
                goto_tag($(this).attr("data-tag"));
            });

            let $star = $("#page-title h2 i");

            if(properties.starred) $star.removeClass("far").addClass("fas");
            $star.click(() => {
                $busy_loader.show();
                mySpires.api({star_tag: tag, val: Number(!properties.starred)}).then(() => {
                    mySpires.prepare(true).then(function() {
                        goto_tag(tag);
                        $busy_loader.hide();
                        if(properties.starred)
                            foot_alert("Tag <span class='alert-link'>" + tag + "</span> was unstarred.");
                        else
                            foot_alert("Tag <span class='alert-link'>" + tag + "</span> was starred.");
                    });
                }).catch(console.log)
            });

            $parentPageLink.removeClass("fa-hdd").addClass("fa-chevron-left");
            $parentPageLink.off("click").click(function() {
                let i = tag.lastIndexOf("/");
                if(i === -1) goto_tag("");
                else goto_tag(tag.substring(0, i));
            });

            $titleNav.css("display", "flex");

            if(properties.description)
                $tagDescription.html(properties.description).show();
        }

        // Subtags

        let subtags = jQuery.grep(mySpires.taglist, function(t) {
            if(!tag) return (t && t.indexOf("/") === -1);
            else return (t.indexOf(tag + "/") === 0 && t.split(tag + "/")[1].indexOf("/") === -1);
        });

        let starred = jQuery.grep(mySpires.taglist, function(t) {
            return Boolean(mySpires.tagsinfo[t].starred);
        });

        let starredTags_count = 0,
            subtags_count = 0;

        for(let t of mySpires.taglist) {
            if(load_library_counter !== load_library_instance) break;

            let parents = t.split("/");
            let name = parents[parents.length - 1];
            let t_ = t.replace(/ /g, "_").replace(/\//g, "__");

            let $where, theme;
            if(!tag && starred.includes(t)) {
                $where = $starredTags;
                theme = "success";
                name = t.replace(/\//g, " / ");
                starredTags_count++;
            }
            else if(subtags.includes(t)) {
                $where = $subtags;
                theme = "dark";
                subtags_count++;
            }

            if($where) {
                $where.append("<button id='subtag_" + t_ + "' type='button' " +
                    "class='btn btn-sm btn-outline-" +  theme + " subtag'>" + name + "</button>");

                $("#subtag_" + t_).click(() => {goto_tag(t)});
            }
        }

        if(starredTags_count > 0) {
            $starredTags.prepend("<h4 class='tags-heading'>Starred Tags (" + starredTags_count + ")</h4>");
        }
        if(subtags_count > 0) {
            if(!tag && starredTags_count > 0)
                $subtags.prepend("<h4 class='tags-heading'>Other Tags (" + subtags_count + ")</h4>");
            else if(!tag)
                $subtags.prepend("<h4 class='tags-heading'>Tags (" + subtags_count + ")</h4>");
            else
                $subtags.prepend("<h4 class='tags-heading'>Subtags (" + subtags_count + ")</h4>");
        }

        // Records

        mySpires.tag(tag).then(function (records) {

            let keys = Object.keys(records);
            keys.sort(function (a, b) {
                let t = records[a].updated.split(/[- :]/);
                let aa = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);
                t = records[b].updated.split(/[- :]/);
                let bb = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);

                return (aa > bb) ? -1 : (aa < bb) ? 1 : 0;
            });


            if (keys.length !== 0) {
                if (!tag) $recordsHeading.html("Untagged (" + keys.length + ")");
                else $recordsHeading.html("Records (" + keys.length + ")");

                $recordsTitle.show();

                let i = 0;
                let trigger = setInterval(function () {
                    if(keys[i] === undefined || load_library_counter !== load_library_instance) {
                        clearInterval(trigger);
                        return;
                    }
                    let entry = records[keys[i++]];
                    let a = new mySpires_Box(entry);
                    a.box.fadeIn();
                }, 10);

                $("#filter-authors").html("").select2({
                    placeholder: 'Select an option' + tag,
                    containerCssClass: "selectors",
                    data: ["All Authors"].concat(mySpires.tagsinfo[tag].surnames)
                });
            }

            $busy_loader.hide();
        });

        // Author Filter

        $("#filter-authors").off("change").change(() => {
            let author = $("#filter-authors").val();

            $("div.paper-box").hide();

            if (author === "All Authors") {
                $("div.paper-box.tag-" + tag_).show();
            } else {
                $("div.paper-box.tag-" + tag_ + ".author-" + author).show();
            }

            return true;
        });

        // Date Sort

        let $filterMethodSelect = $("#filter-method-box select"),
            $filterSortButton = $("#filter-sort-button");

        $filterMethodSelect.off("change").change(function(){sortPapers()});
        $filterSortButton.off("click").click(function () {
            let order = $(this).attr("data-order");

            if (order === "asc") $filterSortButton.attr("data-order", "desc").html("<i class='fas fa-sort-numeric-down'></i>");
            else $filterSortButton.attr("data-order", "asc").html("<i class='fas fa-sort-numeric-up'></i>");

            sortPapers();
        });

        // Tag Editing


        if(tag) {
            $editTagButton.off("click").click((e) => {
                e.preventDefault();

                let i = (tag).indexOf("/", -1);

                if(i === -1) {
                    $editTagParent.val("");
                    $editTagName.val(tag);
                } else {
                    $editTagParent.val(tag.slice(0,i));
                    $editTagName.val(tag.slice(i+1));
                }

                $editTagDescription.val(properties.description);

                $editTagForm.slideDown();
            });

            $editTagSaveButton.off("click").click((e) => {
                e.preventDefault();
                $editTagForm.submit();
            });

            $editTagResetButton.off("click").click((e) => {
                e.preventDefault();
                $editTagForm.slideUp();
            });

            $editTagDeleteButton.off("click").click((e) => {
                e.preventDefault();
                if (confirm("This action cannot be undone. Deleting a tag does not delete the records with that tag. Do you really want to delete the tag '" + tag + "' from your database?")) {
                    $busy_loader.show();
                    mySpires.api({delete_tag: tag}).then(function () {
                        mySpires.prepare(true).then(() => {
                            $editTagForm.slideUp();
                            goto_tag("", true);
                            $busy_loader.hide();
                            foot_alert("Tag <span class='alert-link'>" + tag + "</span> was deleted from your database.");
                        });
                    }).catch(console.log)
                }
            });

            function validateTagParent() {
                $editTagExistsWarning.hide();
                $editTagName.removeClass("is-invalid");
                if(/^[ a-z0-9-\/]*$/i.test($editTagParent.val())) {
                    $editTagParent.removeClass("is-invalid");
                    $editTagParentInvalidWarning.hide();
                    return true;
                } else {
                    $editTagParent.addClass("is-invalid");
                    $editTagParentInvalidWarning.show();
                    return false;
                }
            }

            function validateTagName() {
                $editTagExistsWarning.hide();
                if(/^[ a-z0-9-]*$/i.test($editTagName.val())) {
                    $editTagName.removeClass("is-invalid");
                    $editTagNameInvalidWarning.hide();
                    return true;
                } else {
                    $editTagName.addClass("is-invalid");
                    $editTagNameInvalidWarning.show();
                    return false;
                }
            }

            $editTagParent.off("keyup").on("keyup", validateTagParent);
            $editTagName.off("keyup").on("keyup", validateTagName);

            $editTagDescription.on("keypress", (e) => {
                if (e.keyCode === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $editTagForm.submit();
                }
            });

            tagParentAwesomeplete.list = $.grep(mySpires.taglist, (t) => {
                return t !== tag;
            });

            $editTagForm.off("submit").submit((e) => {
                e.preventDefault();
                if(validateTagName() && validateTagParent()) {
                    let new_tag;
                    if($editTagParent.val())
                        new_tag = $editTagParent.val() + "/" + $editTagName.val();
                    else
                        new_tag = $editTagName.val();

                    $busy_loader.show();

                    let wait = new Promise((resolve) => {
                        if (new_tag && new_tag !== tag) {
                            mySpires.api({rename_tag: tag, new_name: new_tag}).then((ret_tag) => {
                                foot_alert("Tag <span class='alert-link'>" + tag + "</span> was renamed to <span class='alert-link'>" + ret_tag + "</span>.");
                                resolve(ret_tag);
                            }).catch(console.log)
                        } else resolve(tag);
                    });

                    wait.then((ret_tag) => {
                        if (ret_tag) {
                            mySpires.api({describe_tag: ret_tag, val: $editTagDescription.val()}).then(() => {
                                mySpires.prepare(true).then(() => {
                                    goto_tag(ret_tag, true);
                                    $busy_loader.hide();
                                    $editTagForm.slideUp();
                                });
                            });
                        } else {
                            $editTagName.addClass("is-invalid");
                            $editTagExistsWarning.show();
                            $busy_loader.hide();
                        }
                    });
                }
            });
        }
    });
}

$(function() {
    goto_tag((new URLSearchParams(window.location.search)).get("tag"), true);
});

window.onpopstate = (e) => {
    if(e.state && e.state.tag !== undefined) {
        e.preventDefault();
        window.scrollTo(0, 0);
        load_library(e.state.tag)
    }
};