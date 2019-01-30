"use strict";

// TODO: Write a more robust tag, authors retrieval system.

$(function () {
    var query = $("#sharequery").val();

    var $spinner = $(".paper-spinner-wrapper").show();
    mySpires.api({ share: query }).then(function (data) {
        var tag = data.opts.tag,
            records = data.records;

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

    return;

    if (subtags.length === 0) $(".subtags .type-header").hide();else if (tag === "Untagged") $(".subtags .type-header h3").html("Tags (" + subtags.length + ")");else $(".subtags .type-header h3").html("Subtags (" + subtags.length + ")");

    for (var i in subtags) {
        var subtag = void 0;
        if (tag === "Untagged") {
            subtag = subtags[i];
        } else {
            subtag = tag + "/" + subtags[i].substr(tag.length + 1);
        }
        subtagBox.loadSubtag(subtag);
    }
});

function tagShow() {
    var tag = $("#filter-tags").val();

    if (tag.trim().length === 0) {
        tag = "Untagged";
        $("#filter-tags").val(tag);
    }

    $("div.paper-box").remove();

    var $spinner = $(".paper-spinner-wrapper").show();

    mySpires.tag(tag).then(function (records) {});

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

$("#filter-authors").change(authorShow);

function sortPapers() {
    console.log(1);

    var method = $("#filter-sort").val();
    var order = $("#filter-sort-button").attr("order");

    if (method === "published") {
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
});
//# sourceMappingURL=share.js.map