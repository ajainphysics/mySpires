"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/**
 * This class constructs relevant HTML elements for records.
 */
var mySpires_Box =
/**
 * @param {mySpires_Record} record - The record to be loaded.
 * @param {string} [where] - Location where the box should be loaded.
 */
function mySpires_Box(record, where) {
    _classCallCheck(this, mySpires_Box);

    this.record = record;
    if (where === undefined) where = ".paper-boxes";

    this.page = $("body").attr("id").split("page-")[1];

    // Let us first place the physical box, where results will be loaded.
    $(where).append("<div id='record-" + record.id + "' class='col-md-4 col-lg-3 paper-box " + record.status + "'>" + "<div class='paper-wrapper'></div></div>");
    this.box = $("#record-" + record.id);

    // Create DOM elements for the record.
    this.box.find(".paper-wrapper").append("<div class='img-wrapper'></div>" + "<div class='title-wrapper'>" + "<p class='paper-title' data-toggle='tooltip' data-placement='top' title='Title'></p>" + "</div>" + "<div class='author-wrapper'>" + "<p class='paper-authors'></p>" + "</div>" + "<div class='date-wrapper'>" + "<span class='published-date' data-toggle='tooltip' data-placement='top' title='Publication On'>" + "</span>" + "<span class='date-bullet'>&bull;</span>" + "<span class='modified-date' data-toggle='tooltip' data-placement='top' title='Viewed On'></span> </div>" + "<div class='mySpires-bar homeserver smallbar'></div>");

    // Save all the DOMs defined above as properties of the object, to be used later.

    this.thumbnail = this.box.find(".img-wrapper");
    this.title = this.box.find('.paper-title');
    this.published = this.box.find('.published-date');
    this.modified = this.box.find('.modified-date');
    this.dateBullet = this.box.find('.date-bullet');
    this.authors = this.box.find(".paper-authors");

    /* ============================== Thumbnail ============================== */

    // Make sure that the thumbnails dimensions are appropriate.
    // $(".img-wrapper").height(1 / 1.618 * $(".paper-box").width()); // Set the thumbnail height
    // console.log(1 / 1.618 * $(".paper-box").width());
    this.thumbnail.height(thumbHeight());

    // If arxiv record exists, display the corresponding pdf image as thumbnail.
    if (record.arxiv) {
        this.thumbnail.append("<a href='https://arxiv.org/pdf/" + record.arxiv + ".pdf' target='_blank'>" + "<img src='thumbnails/" + record.id + ".jpg' class='img-thumbnail img-fluid'></a>");
    }

    // Set up hover animations for the thumbnail.
    this.thumbnail.hover(function () {
        $(this).find(".edit-buttons").toggle();
        $(this).find("img").toggleClass("lifted", 500);
    });

    /* ============================== Title ============================== */

    // Isolate the last word of the title.
    var titleLastWord = record.title.split(" ");
    titleLastWord = titleLastWord[titleLastWord.length - 1];
    // var remainingTitle = entry.title.slice(0, 0 - titleLastWord.length);

    // Figure out the title link.
    var titleLink = "";
    if (record.inspire) titleLink = "href = 'http://inspirehep.net/record/" + record.inspire + "'";

    // Display the title
    this.title.attr("title", utf8_decode(record.title)).html("<a " + titleLink + " target='_blank'>" + utf8_decode(record.title) + "</a>" + "");
    // <span class='title-last-word'><a " + titleLink + " target='_blank'>" + titleLastWord + "</a></span>
    // Setup ellipsis for long titles. TODO: This is too resource consuming.
    // this.title.parent().dotdotdot({wrap: 'letter', after: 'span.title-last-word'});

    /* ============================== Authors ============================== */

    // Convert the string of author names to a string of surnames and display it.
    var authorArray = record.author.split(", "),
        surnameArray = $.map(authorArray, function (a) {
        var arr = a.split(" ");
        return arr[arr.length - 1];
    });
    this.authors.html(utf8_decode(surnameArray.join(", ")));

    // Setup ellipsis for too long author lists. TODO: This is too resource consuming.
    //        this.authors.parent().dotdotdot({ellipsis: ' et al. '});

    // Add author surnames as classes to the box for use by filters.
    this.box.addClass($.map(surnameArray, function (s) {
        return "author-" + s;
    }).join(" "));

    /* ============================== Date ============================== */

    // Transform the modified and published dates from the record to a more useful format.
    var t = record.updated.split(/[- :]/),
        modifiedDate = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]),
        publishedDate = new Date(record.published);

    // Find out how much time has passed since the record was last modified.
    var today = new Date(),
        daysPast = Math.floor((today.getTime() - modifiedDate.getTime()) / (1000 * 60 * 60 * 24));

    // Define month and week names to be used in a bit.
    var monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
        monthNamesSmall = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
        dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

    // Display published date in the format Jan 2017.
    this.published.html(monthNamesSmall[publishedDate.getMonth()] + " " + publishedDate.getFullYear());

    if (!$(where).hasClass("public")) {
        // Now the more tricky part: converting modified date into "opened ..." format.
        // Let us first find what day of the week it was on the modified date and is today.
        var todayDay = today.getDay();
        if (todayDay === 0) todayDay = 7;
        var modifiedDay = modifiedDate.getDay();
        if (modifiedDay === 0) modifiedDay = 7;

        // Now we just make some cases to get the desired format.
        if (today.getDate() === modifiedDate.getDate() && daysPast === 0) this.modified.html("opened today");else if (today.getDate() === modifiedDate.getDate() + 1 && daysPast <= 1) this.modified.html("opened yesterday");else if (modifiedDay < todayDay && daysPast < 7) this.modified.html("opened " + dayNames[modifiedDate.getDay()]);else if (modifiedDate.getMonth() === today.getMonth() && daysPast < 100) this.modified.html("opened " + modifiedDate.getDate() + " " + monthNames[modifiedDate.getMonth()]);else if (modifiedDate.getFullYear() === today.getFullYear()) this.modified.html("opened in " + monthNames[modifiedDate.getMonth()]);else this.modified.html("opened in " + monthNamesSmall[modifiedDate.getMonth()] + " " + modifiedDate.getFullYear());

        var bar = new mySpires_Bar(record, this.box);
        if (this.page === "library") {
            bar.record.busy.then(function () {
                bar.onupdate = function () {
                    if (this.record.status === "unsaved") this.box.remove();
                };
            }.bind(this));
        }
    } else {
        this.dateBullet.hide();
    }

    MathJax.Hub.Queue(["Typeset", MathJax.Hub, "record-" + record.id]);
};

var subtagBox = {
    loadSubtag: function loadSubtag(subtag, type) {
        var subtagParents = subtag.split("/");
        var subtagName = subtagParents[subtagParents.length - 1];
        var subtagName_ = subtagName.replace(/ /g, "_");
        var tagSmallClass = "";

        if (subtagName.length > 20) tagSmallClass = "tag-name-small";

        var typeclass = "";
        if (type) typeclass = "subtag-" + type;

        $(".subtags").append("<div id='subtag-" + subtagName_ + "' " + "class='col-sm-6 col-md-4 col-lg-3 col-xl-2 subtag " + typeclass + "'>" + "<div class='subtag-wrapper'>" + "<div class='tag-icon'><i class='fa fa-tag'></i></div>" + "<div class='tag-name " + tagSmallClass + "'><p>" + subtagName + "</p></div></div></div>");

        var $subtagBox = $("#subtag-" + subtagName_);

        $subtagBox.click(function () {
            window.location = "?tag=" + subtag;
        });

        $subtagBox.find(".tag-name").dotdotdot({ wrap: 'letter', watch: true }); // "watch: true" is too time consuming
    }
};

// Whenever the window is resized, fix the aspect ratio of thumbnails.
$(window).resize(function () {
    $(".paper-box .img-wrapper").height(thumbHeight());
});

function thumbHeight() {
    var x = $("body").width();
    var y = $(".paper-boxes").width();
    var z = void 0;

    if (x < 768) z = y;else if (x < 992) z = y / 3;else z = y / 4;

    return z / 2;
}

function utf8_decode(strData) {
    var tmpArr = [];
    var i = 0;
    var c1 = 0;
    var seqlen = 0;

    strData += '';

    while (i < strData.length) {
        c1 = strData.charCodeAt(i) & 0xFF;
        seqlen = 0;

        // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
        if (c1 <= 0xBF) {
            c1 = c1 & 0x7F;
            seqlen = 1;
        } else if (c1 <= 0xDF) {
            c1 = c1 & 0x1F;
            seqlen = 2;
        } else if (c1 <= 0xEF) {
            c1 = c1 & 0x0F;
            seqlen = 3;
        } else {
            c1 = c1 & 0x07;
            seqlen = 4;
        }

        for (var ai = 1; ai < seqlen; ++ai) {
            c1 = c1 << 0x06 | strData.charCodeAt(ai + i) & 0x3F;
        }

        if (seqlen === 4) {
            c1 -= 0x10000;
            tmpArr.push(String.fromCharCode(0xD800 | c1 >> 10 & 0x3FF));
            tmpArr.push(String.fromCharCode(0xDC00 | c1 & 0x3FF));
        } else {
            tmpArr.push(String.fromCharCode(c1));
        }

        i += seqlen;
    }

    return tmpArr.join('');
}
//# sourceMappingURL=components.js.map