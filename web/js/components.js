const subtagBox = {
    loadSubtag: function (subtag, type) {
        let subtagParents = subtag.split("/");
        let subtagName = subtagParents[subtagParents.length - 1];
        let subtagName_ = subtagName.replace(/ /g, "_");
        let tagSmallClass = "";

        if(subtagName.length > 20) tagSmallClass = "tag-name-small";

        let typeclass = "";
        if(type) typeclass = "subtag-" + type;

        $(".subtags").append("<button id='subtag-" + subtagName_ + "' type='button' " +
            "class='btn btn-sm btn-outline-dark subtag " + typeclass + "'>" + subtagName + "</button>");

        let $subtagBox = $("#subtag-" + subtagName_);

        $subtagBox.click(function() {
            window.location = "?tag=" + subtag;
        });
    }
};

// Whenever the window is resized, fix the aspect ratio of thumbnails.
$(window).resize(function () {
    $(".paper-box .img-wrapper").height(thumbHeight());
});

function sortPapers(method, order, within) {
    if (method === undefined) method = $("#filter-method-box select").val();
    if (order === undefined) order = $("#filter-sort-button").attr("data-order");
    if (within === undefined) within = ".paper-boxes";

    if (method === "modified") {
        $(within + ' div.paper-box:visible').sort(function (a, b) {
            a = mySpires_Boxes[$(a).attr("id").split("-")[1]].record.updated;
            b = mySpires_Boxes[$(b).attr("id").split("-")[1]].record.updated;

            if (order === "asc") return (a < b) ? -1 : (a > b) ? 1 : 0;
            else return (a < b) ? 1 : (a > b) ? -1 : 0;
        }).appendTo(within);
    }
    else if (method === "published") {
        $(within + ' div.paper-box:visible').sort(function (a, b) {
            a = mySpires_Boxes[$(a).attr("id").split("-")[1]].record.published;
            b = mySpires_Boxes[$(b).attr("id").split("-")[1]].record.published;
            if (order === "asc") return (a < b) ? -1 : (a > b) ? 1 : 0;
            else return (a < b) ? 1 : (a > b) ? -1 : 0;
        }).appendTo(within);
    }

    return true;
}

function utf8_decode (strData) {
    let tmpArr = [];
    let i = 0;
    let c1 = 0;
    let seqlen = 0;

    strData += '';

    while (i < strData.length) {
        c1 = strData.charCodeAt(i) & 0xFF;
        seqlen = 0;

        // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
        if (c1 <= 0xBF) {
            c1 = (c1 & 0x7F);
            seqlen = 1
        } else if (c1 <= 0xDF) {
            c1 = (c1 & 0x1F);
            seqlen = 2
        } else if (c1 <= 0xEF) {
            c1 = (c1 & 0x0F);
            seqlen = 3
        } else {
            c1 = (c1 & 0x07);
            seqlen = 4
        }

        for (let ai = 1; ai < seqlen; ++ai) {
            c1 = ((c1 << 0x06) | (strData.charCodeAt(ai + i) & 0x3F))
        }

        if (seqlen === 4) {
            c1 -= 0x10000;
            tmpArr.push(String.fromCharCode(0xD800 | ((c1 >> 10) & 0x3FF)));
            tmpArr.push(String.fromCharCode(0xDC00 | (c1 & 0x3FF)));
        } else {
            tmpArr.push(String.fromCharCode(c1));
        }

        i += seqlen;
    }

    return tmpArr.join('');
}