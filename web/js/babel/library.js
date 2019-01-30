"use strict";

// TODO: Write a more robust tag, authors retrieval system.

$(function () {
    mySpires.prepare().then(function () {
        var tag = $("#filter-tags").val();

        if (tag.trim().length === 0) {
            tag = "Untagged";
            $("#filter-tags").val(tag);
        }

        if (tag === "Untagged") {
            $("#page-title h2").html("Library");
        } else {
            var tagEx = tag.split("/");

            var subtitleLink = "";
            var subtitle = "<a href='?'>Library</a>";
            var _iteratorNormalCompletion = true;
            var _didIteratorError = false;
            var _iteratorError = undefined;

            try {
                for (var _iterator = tagEx[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
                    var t = _step.value;

                    if (subtitleLink !== "") subtitleLink = subtitleLink + "/";
                    subtitleLink = subtitleLink + t;
                    subtitle = subtitle + " / <a href='?tag=" + subtitleLink + "'>" + t + "</a>";
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

            $("#page-title").removeClass("main-title");
            $("#page-title h2").html(tagEx[tagEx.length - 1]);
            $("#page-breadcrumb").html(subtitle);

            $("#parent-page-link").toggleClass("fa-hdd-o fa-chevron-left").click(function () {
                var i = tag.lastIndexOf("/");
                switch (i) {
                    case -1:
                        window.location = "?";
                        break;
                    default:
                        window.location = window.location = "?tag=" + tag.substring(0, i);
                }
            });
        }

        var subtags = jQuery.map(mySpires.taglist, function (t) {
            if (tag === "Untagged") {
                if (t.trim().length !== 0) return t.split("/")[0];else return false;
            } else if (t.indexOf(tag + "/") === 0) {
                var s = t.substr(tag.length + 1);
                return tag + "/" + s.split("/")[0];
            } else {
                return false;
            }
        });

        subtags = jQuery.grep(subtags, function (t, i) {
            if (t !== false && subtags.indexOf(t) === i) return true;
        });

        if (subtags.length === 0) $(".subtags .type-header").hide();else if (tag === "Untagged") $(".subtags .type-header h3").html("Tags (" + subtags.length + ")");else $(".subtags .type-header h3").html("Subtags (" + subtags.length + ")");

        for (var i in subtags) {
            var subtag = subtags[i];

            if (mySpires.tagopts[subtag] && mySpires.tagopts[subtag]["type"]) subtagBox.loadSubtag(subtag, mySpires.tagopts[subtag]["type"]);else subtagBox.loadSubtag(subtag);
        }
    });
});

function tagShow() {
    var tag = $("#filter-tags").val();

    if (tag.trim().length === 0) {
        tag = "Untagged";
        $("#filter-tags").val(tag);
    }

    $("div.paper-box").remove();

    var $spinner = $(".paper-spinner-wrapper").show();

    mySpires.tag(tag).then(function (records) {

        var keys = Object.keys(records);
        keys.sort(function (a, b) {
            var t = records[a].updated.split(/[- :]/);
            a = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);
            t = records[b].updated.split(/[- :]/);
            b = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);

            return a > b ? -1 : a < b ? 1 : 0;
        });

        if (tag === "Untagged") $(".paper-boxes .type-header h3").html("Untagged (" + keys.length + ")");else $(".paper-boxes .type-header h3").html("Records (" + keys.length + ")");

        if (keys.length !== 0) {
            var i = 0;
            var trigger = setInterval(function () {
                var entry = records[keys[i]];

                var a = new mySpires_Box(entry);
                a.box.fadeIn();

                if (keys[++i] === undefined) clearInterval(trigger);
            }, 10);

            if (tag === "Untagged") entryTagsAuthors[tag] = entryTagsAuthors[""];
            $("#filter-authors").html("").select2({
                placeholder: 'Select an option' + tag,
                containerCssClass: "selectors",
                data: ["All Authors"].concat(entryTagsAuthors[tag])
            });
        }

        $spinner.hide();
    });

    return true;
}

function authorShow() {
    var tag = $("#filter-tags").val();
    tag = tag.replace(/ /g, "_").replace(/\//g, "__");

    var author = $("#filter-authors").val();

    $("div.paper-box").hide();

    if (author === "All Authors") {
        $("div.paper-box.tag-" + tag).show();
    } else {
        $("div.paper-box.tag-" + tag + ".author-" + author).show();
    }

    // sortPapers();

    return true;
}

$(function () {
    $(tagShow);
    $("#filter-tags").change(tagShow);
    $("#filter-authors").change(authorShow);
});

function sortPapers() {
    console.log(1);

    var method = $("#filter-sort").val();
    var order = $("#filter-sort-button").attr("order");

    if (method === "modified") {
        $('div.paper-box:visible').sort(function (a, b) {
            a = entryBox[$(a).attr("id").split("-")[1]].modifiedDate;
            b = entryBox[$(b).attr("id").split("-")[1]].modifiedDate;
            if (order === "asc") return a < b ? -1 : a > b ? 1 : 0;else return a < b ? 1 : a > b ? -1 : 0;
        }).appendTo(".paper-boxes");
    } else if (method === "published") {
        $('div.paper-box:visible').sort(function (a, b) {
            a = entryBox[$(a).attr("id").split("-")[1]].publishedDate;
            b = entryBox[$(b).attr("id").split("-")[1]].publishedDate;
            if (order === "asc") return a < b ? -1 : a > b ? 1 : 0;else return a < b ? 1 : a > b ? -1 : 0;
        }).appendTo(".paper-boxes");
    }

    return true;
}

// $(sortPapers);
$(function () {
    $("#filter-sort").change(sortPapers);
    $("#filter-sort-button").click(function () {
        var order = $(this).attr("order");

        if (order === "asc") $("#filter-sort-button").attr("order", "desc").html("<i class='fa fa-sort-numeric-desc'></i>");else $("#filter-sort-button").attr("order", "asc").html("<i class='fa fa-sort-numeric-asc'></i>");

        sortPapers();
    });

    return true;
});
//# sourceMappingURL=library.js.map