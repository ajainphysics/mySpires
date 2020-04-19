$(function () {
    $("#form-bibtex").autoResize();

    $(".bib-edit-button").click(function() {
        let $entry = $(this).closest(".bib-entry");
        let bibkey = $entry.find(".bibkey pre").html();
        let bibtex = $entry.find(".bibtex pre").html();

        $("#form-bibkey").val(bibkey);
        $("#form-bibtex").val(bibtex);

        window.scrollTo(0,document.body.scrollHeight);
    });

    let ids = $(".bib-entries").attr("data-found-records");
    mySpires.api({q: ids, field: "id"}).then(function(response){
        $(".bib-entry.match").each(function () {
            let id = Number($(this).attr("data-record"));
            if(!id) return;
            let box = new mySpires_Box(response[id], {
                where: $(this).find(".found-box"),
                thumbnail: false,
                box_classes: ""
            });

            box.bar.record.busy.then(() => {
                // Set an on-update function
                box.bar.onupdate = function() {
                };
            });

        });
    }).catch(console.log);
});