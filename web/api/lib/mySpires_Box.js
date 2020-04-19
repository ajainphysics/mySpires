const mySpires_Boxes = {};

class mySpires_Box
{
    /**
     * @param {mySpires_Record} record - The record to be loaded.
     * @param {string|object} [xopts] - Location where the box should be loaded or detailed options.
     */
    constructor(record, xopts) {
        this.record = record;

        let opts = {
            where: ".paper-boxes",
            thumbnail: true,
            box_classes: "col-md-4 col-lg-3"
        }

        if(typeof xopts === "string" || xopts instanceof jQuery) opts.where = xopts;
        else if(typeof xopts === "object")
            for(let opt of Object.keys(opts))
                if(xopts[opt] !== undefined) opts[opt] = xopts[opt];

        let $where = $(opts.where);
        $where.addClass("mySpires-context");

        let page_id = $("body").attr("id");
        if(page_id)
            this.page = page_id.split("page-")[1];
        else this.page = "";

        let boxID;
        if(record.id) boxID = "record-" + record.id;
        else if(record.inspire) boxID = "temp-inspire-" + record.inspire;
        else if(record.arxiv) boxID = "temp-arxiv-" + record.arxiv;
        else return;

        if($where.hasClass("mySpires-boxes-rows"))
            opts.box_classes = "";

        // Let us first place the physical box, where results will be loaded.
        $where.append("<div id='" + boxID + "' class='mySpires-box mySpires-context paper-box'></div>");

        this.box = $("#" + boxID).addClass(opts.box_classes).addClass(record.status);
        this.box.append("<div class='paper-wrapper'></div>");
        this.papers = this.box.find(".paper-wrapper");
        mySpires_Boxes[record.id] = this;

        // Create DOM elements for the record.
        if($where.hasClass("mySpires-boxes-minimal"))
            this.papers.append(
                "<div class='title-wrapper'>" +
                "  <p class='paper-title' data-toggle='tooltip' data-placement='top' title='Title'></p>" +
                "</div>" +
                "<div class='author-wrapper'>" +
                "  <span class='paper-authors'></span>" +
                "  <span class='date-bullet'>&bull;</span>" +
                "  <span class='published-date' data-toggle='tooltip' data-placement='top' title='Publication On'></span>" +
                "  <span class='date-bullet'>&bull;</span>" +
                "  <span class='links-wrapper'></span>" +
                "</div>" +
                "<div class='mySpires-bar homeserver smallbar'></div>"
            );
        else
            this.box.find(".paper-wrapper").append(
                "<div class='img-wrapper'></div>"
                + "<div class='title-wrapper'>"
                + "<p class='paper-title' data-toggle='tooltip' data-placement='top' title='Title'></p>"
                + "</div>"
                + "<div class='author-wrapper'>"
                + "<p class='paper-authors'></p>"
                + "</div>"
                + "<div class='date-wrapper'>"
                + "<span class='published-date' data-toggle='tooltip' data-placement='top' title='Publication On'>"
                + "</span>"
                + "<span class='date-bullet'>&bull;</span>"
                + "<span class='modified-date' data-toggle='tooltip' data-placement='top' title='Viewed On'></span> </div>"
                + "<div class='links-wrapper'></div>"
                + "<div class='doi-wrapper'><a href='' target='_blank'></a></div>"
                + "<div class='mySpires-bar homeserver smallbar'></div>"
            );

        // Save all the DOMs defined above as properties of the object, to be used later.

        this.thumbnail =   this.box.find(".img-wrapper");
        this.title =       this.box.find('.paper-title');
        this.published =   this.box.find('.published-date');
        this.modified =    this.box.find('.modified-date');
        this.dateBullet =  this.box.find('.date-bullet');
        this.authors =     this.box.find(".paper-authors");
        this.doi =     this.box.find(".doi-wrapper");
        this.links = this.box.find(".links-wrapper");

        /* ============================== Links ============================== */

        let link_arxiv_pdf, link_arxiv_abs, link_inspire, link_doi, link_ads;

        if(record.arxiv) {
            link_arxiv_pdf = "https://arxiv.org/pdf/" + record.arxiv + ".pdf";
            link_arxiv_abs = "https://arxiv.org/abs/" + record.arxiv;

            this.links.append("<a href='" + link_arxiv_pdf + "' target='_blank'>PDF</a>");
            this.links.append("<a href='" + link_arxiv_abs + "' target='_blank'>arXiv</a>");
        }
        if(record.inspire) {
            link_inspire = "http://inspirehep.net/record/" + record.inspire;
            this.links.append("<a href='" + link_inspire + "' target='_blank'>INSPIRE</a>");
        }
        if(!record.inspire && record.ads) {
            link_ads = "https://ui.adsabs.harvard.edu/abs/" + record.ads + "/abstract";
            this.links.append("<a href='" + link_ads + "' target='_blank'>ADS</a>");
        }
        if(record.doi) {
            link_doi = "https://doi.org/" + record.doi;
            this.links.append("<a href='" + link_doi + "' target='_blank'>DOI</a>");
        }
        if(record.bibkey) {
            this.links.append("<input type='text' style='display:none;' class='bibkey-value' value='" + record.bibkey + "'>");
            this.links.append("<a class='link-bibkey' href='#'>BibKey</a>");
        }
        if(!record.bibkey && record.ads) {
            this.links.append("<input type='text' style='display:none;' class='bibkey-value' value='" + record.ads + "'>");
            this.links.append("<a class='link-bibkey' href='#'>BibKey</a>");
        }

        this.box.find(".link-bibkey").click((e) => {
            e.preventDefault();
            let bibkeyValue = this.box.find(".bibkey-value");
            bibkeyValue.show().select();
            document.execCommand("copy");
            bibkeyValue.hide();
            foot_alert("BibKey <span class='alert-link'>" + record.bibkey + "</span> was copied to clipboard.", "primary", 5000);
        });

        /* ============================== Thumbnail ============================== */

        if(opts.thumbnail) {
            this.thumbnail.height(thumbHeight());

            // If thumbnail exists, display it.
            if (record.id) {
                let url = mySpires.content_server + "thumbnails/" + record.id + ".jpg";
                image_exists(url).then(function () {
                    if (record.arxiv) {
                        this.thumbnail.append(
                            "<a href='" + link_arxiv_pdf + "' target='_blank'>"
                            + "<img src='" + url + "' class='img-thumbnail img-fluid' alt=''></a>"
                        );
                    } else {
                        this.thumbnail.append(
                            "<img src='" + url + "' class='img-thumbnail img-fluid' alt=''>"
                        );
                    }
                }.bind(this)).catch(console.log);
            }

            // Set up hover animations for the thumbnail.
            this.thumbnail.hover(function () {
                $(this).find(".edit-buttons").toggle();
                $(this).find("img").toggleClass("lifted", 500);
            });
        }
        else this.thumbnail.remove();

        /* ============================== Title ============================== */

        // Isolate the last word of the title.
        let titleLastWord = record.title.split(" ");
        titleLastWord = titleLastWord[titleLastWord.length - 1];
        // var remainingTitle = entry.title.slice(0, 0 - titleLastWord.length);

        // Figure out the title link.
        let titleLink = "";
        if (record.inspire) titleLink = "href = '" + link_inspire + "'";
        else if (record.ads) titleLink = "href = '" + link_ads + "'";

        // Display the title
        this.title.attr("title", utf8_decode(record.title))
            .html("<a " + titleLink + " target='_blank'>" + utf8_decode(record.title) + "</a>"
                + "")
            .tooltip({classes: {"ui-tooltip": "myspires-tooltip"}});



        /* ============================== Authors ============================== */

        // Convert the string of author names to a string of surnames and display it.
        let authorArray = record.author.split(", "),
            surnameArray = $.map(authorArray, function (a) {
                let arr = a.split(" ");
                return arr[arr.length - 1];
            }),
            authorList;

        if(record.author_id) {
            let authorIDList = record.author_id.split(", ");
            authorList = $.map(authorArray, function (val, key) {
                let arr = val.split(" ");
                return "<span class='paper-author' data-toggle='tooltip' data-placement='top' title='" + val + "'>" +
                    "<a href='https://inspirehep.net/literature?q=ea%20" + authorIDList[key] + "' target='_blank'>" +
                    arr[arr.length - 1] +
                    "</a>" +
                    "</span>";
            });
        } else {
            authorList = $.map(authorArray, function (val, key) {
                let arr = val.split(" ");
                return "<span class='paper-author' data-toggle='tooltip' data-placement='top' title='" + val + "'>" +
                    arr[arr.length - 1] + "</span>";
            });
        }

        if(authorList.length > 6)
            this.authors.html(utf8_decode(authorList[0]) + " et al.");
        else
            this.authors.html(utf8_decode(authorList.join(", ")));

        this.authors.children(".paper-author").tooltip({classes: {"ui-tooltip": "myspires-tooltip"}});

        // Add author surnames as classes to the box for use by filters.
        this.box.addClass(utf8_decode($.map(surnameArray, function (s) {
            return "author-" + s
        }).join(" ")));

        /* ============================== Date ============================== */

        // Define month and week names to be used in a bit.
        let monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September",
                "October", "November", "December"],
            monthNamesSmall = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        // Transform the modified and published dates from the record to a more useful format.
        let publishedDate = new Date(record.published);

        // Display published date in the format Jan 2017.
        this.published
            .html(monthNamesSmall[publishedDate.getMonth()] + " " + publishedDate.getFullYear())
            .tooltip({classes: {"ui-tooltip": "myspires-tooltip"}});

        if(!$(opts.where).hasClass("public")) {
            if(record.updated) {
                let t = record.updated.split(/[- :]/),
                    modifiedDate = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);

                // Find out how much time has passed since the record was last modified.
                let today = new Date(),
                    daysPast = Math.floor((today.getTime() - modifiedDate.getTime()) / (1000 * 60 * 60 * 24));

                // Now the more tricky part: converting modified date into "opened ..." format.
                // Let us first find what day of the week it was on the modified date and is today.
                let todayDay = today.getDay();
                if (todayDay === 0) todayDay = 7;
                let modifiedDay = modifiedDate.getDay();
                if (modifiedDay === 0) modifiedDay = 7;

                // Now we just make some cases to get the desired format.
                if (today.getDate() === modifiedDate.getDate() && daysPast === 0)
                    this.modified.html("opened today");
                else if (today.getDate() === modifiedDate.getDate() + 1 && daysPast <= 1)
                    this.modified.html("opened yesterday");
                else if (modifiedDay < todayDay && daysPast < 7)
                    this.modified.html("opened " + dayNames[modifiedDate.getDay()]);
                else if (modifiedDate.getMonth() === today.getMonth() && daysPast < 100)
                    this.modified.html("opened " + modifiedDate.getDate() + " "
                        + monthNames[modifiedDate.getMonth()]);
                else if (modifiedDate.getFullYear() === today.getFullYear())
                    this.modified.html("opened in " + monthNames[modifiedDate.getMonth()]);
                else
                    this.modified.html("opened in " + monthNamesSmall[modifiedDate.getMonth()] + " "
                        + modifiedDate.getFullYear());
            }
            else this.dateBullet.hide();

            this.modified.tooltip({classes: {"ui-tooltip": "myspires-tooltip"}});

            this.bar = new mySpires_Bar(record, this.box);

            this.bar.onupdate = () => {
                this.bar.record.busy.then(() => {
                    if (this.page === "library") {
                        if (this.record.status === "unsaved") this.box.remove();
                    }
                    mySpires.prepare(true).then(() => {
                        window.dispatchEvent(new Event("mySpires_Record_Update"));
                    });
                });
            };

            window.addEventListener("mySpires_Record_Update", () => {
                this.bar.refreshValues();
            });


        } else {
            this.dateBullet.hide();
        }

        // MathJax.Hub.Queue(["Typeset", MathJax.Hub, "record-" + record.id]);
    }
}

function thumbHeight() {
    let x = $("body").width();
    let y = $(".paper-boxes").width();
    let z;

    if(x < 768) z = y;
    else if(x < 992) z = y/3;
    else z = y/4;

    return z / 2
}

function image_exists(url) {
    return new Promise((resolve, reject) => {
        let img = new Image();
        img.onload = resolve;
        img.onerror = reject;
        img.src = url;
    });
}