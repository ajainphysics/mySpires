/**
 * @typedef {object} user
 * @property {string} first_name - First name of the user
 * @property {string} last_name - Last name of the user
 */

// The popup title ("mySpires v#.#.#")
// $(".version").html(browser.runtime.getManifest().version);

// Add "Home" and "Support" links in the footer.
let $footLinks = $(".footlinks");
$footLinks
    .append("<a href='" + mySpires.server + "' target='_blank'><i class='fas fa-home'></i></a>")
    .append("<a href='" + mySpires.server + "support.php' target='_blank'><i class='fas fa-life-ring'></i></a>");

let searchActivated = false;
mySpires_Search.activate = function () {
    $("body").animate({width: "500"});
    $(".search-button").show();
    // $(".search-help").show();
    $("#record-wrapper").slideUp();
    searchActivated = true;
};

let $loggedInContent = $("#logged_in_content");

mySpires_Search.draw($loggedInContent);
$loggedInContent.show();

// Activate the busy loader.
let $loader = $(".busy-loader");

browser.runtime.sendMessage({type: "auth", lenient: true}).then(response => {
    let messages = response.messages,
        $alerts = $("#popup_alerts");

    if(!messages) messages = [];

    for(let message of messages) {
        if(!message.message) continue;
        if(!message.type) message.type = "primary";
        $alerts.append("<div class='alert alert-" + message.type + " alert-dismissible fade show' role='alert'>" +
            message.message + "</div>");
    }
    $alerts.children(".alert a").addClass("alert-link");

    /**user*/
    let user = response.user;

    // $loader.hide();

    /* ============================== Header, Main Content and Footer ============================== */

    if (!user) {
        $("#logged_in_content").hide();

        $("#login_msg").html("You are not logged in.").removeClass("text-danger");

        $("#forgot_password").attr("href", mySpires.server + "register.php?forgot=1");
        $("#register").attr("href", mySpires.server + "register.php");

        $("#login").submit(function (e) {
            e.preventDefault();
            $loader.show();
            $("#login_btn").html("<i class='fa fa-spinner fa-spin' aria-hidden='true'></i>");

            let args = {login: 1};
            $(this).serializeArray().forEach(function(a) {
                args[a.name] = a.value;
            });

            mySpires.api(args).then(function(response) {
                if (response) {
                    window.close();
                } else {
                    $loader.hide();
                    $("#login_btn").html("Sign In");
                    $("#login_msg").html("Login failed! Please try again.").addClass("text-danger");
                }
            }).catch(console.log);
        });

        $("#login_msg_box").show();
        $("#username").focus();

    } else {
        $(".current-user").html(user.first_name + " " + user.last_name).show();
        $(".search-field").focus();

        $footLinks
            .append("<a href='" + mySpires.server + "library.php' target='_blank'><i class='fas fa-hdd'></i></a>")
            .append("<a href='" + mySpires.server + "history.php' target='_blank'><i class='fas fa-history'></i></a>")
            .append("<a href='" + mySpires.server + "preferences.php' target='_blank'><i class='fas fa-cogs'></i></a>")
            .append("<a id='link-logout' href='#' target='_blank'><i class='fas fa-sign-out-alt'></i></a>");

        $("#link-logout").click(function (e) {
            e.preventDefault();
            mySpires.api({logout: 1}).then(window.close).catch(console.log);
        });

        load_current_record();
    }

});

function load_current_record() {
    let $record_wrapper = $("#record-wrapper");
    // $(".search-results").html("").slideUp();
    // $("body").animate({width: "220"});
    // $(".search-help").slideUp();

    if($record_wrapper.html()) {
        $record_wrapper.slideDown();
        return;
    }

    active_tab().then((tab) => {
        $loader.show();
        mySpires_Plugin.analyze_url(tab.url).then(function(query) {
            if(query && query.q) {
                mySpires_Plugin.api(query).then(response => {
                    console.log(response);
                    console.log(query.q);

                    let box = new mySpires_Box(response[query.q], "#record-wrapper");

                    box.bar.record.busy.then(() => {
                        $loader.hide();

                        // Set an on-update function
                        box.bar.onupdate = function() {
                            let raw_record = JSON.parse(JSON.stringify(box.bar.record));
                            ask_page("refresh", raw_record).catch(console.log)
                        };

                        if(!searchActivated) $record_wrapper.slideDown();
                    });

                }).catch(function(e) {
                    $loader.hide();
                    console.log(e);
                });
            } else {
                $loader.hide();
            }
        }).catch(function(e) {
            $loader.hide();
            console.log(e);
        });
    }).catch(console.log);
}