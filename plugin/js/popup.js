/**
 * @typedef {object} user
 * @property {string} first_name - First name of the user
 * @property {string} last_name - Last name of the user
 */

// The popup title ("mySpires v#.#.#")
$(".version").html(browser.runtime.getManifest().version);

// Add "Home" and "Support" links in the footer.
let $footLinks = $(".footlinks");
$footLinks
    .append("<a href='" + mySpires.server + "' target='_blank'>Home</a>")
    .append("<a href='" + mySpires.server + "support.php' target='_blank'>Support</a>");

// Activate the busy loader.
let $loader = $(".busy-loader");
$loader.show();

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

    $loader.hide();

    /* ============================== Header, Main Content and Footer ============================== */

    if (!user) {
        $("#login_msg").html("You are not logged in.").removeClass("text-danger");

        $("#forgot_password").attr("href", mySpires.server + "register.php?forgot=1");
        $("#register").attr("href", mySpires.server + "register.php");

        $("#login").submit(function (e) {
            e.preventDefault();
            $("#login_btn").html("<i class='fa fa-spinner fa-spin' aria-hidden='true'></i>");

            let args = {login: 1};
            $(this).serializeArray().forEach(function(a) {
                args[a.name] = a.value;
            });

            mySpires.api(args).then(function(response) {
                if (response) {
                    window.close();
                } else {
                    $("#login_btn").html("Login");
                    $("#login_msg").html("Login failed! Please try again.").addClass("text-danger");
                }
            }).catch(console.log);
        });

        $("#login_msg_box").show();

    } else {
        $("#welcome").html("Hello " + user.first_name + "!");
        $("#db_btn").attr("href", mySpires.server + "library.php");
        $("#recent_btn").attr("href", mySpires.server + "history.php");

        $footLinks.append("<a href='" + mySpires.server + "preferences.php' target='_blank'>Preferences</a>");
        $footLinks.append("<a id='link-logout' href='#' target='_blank'>Logout</a>");

        $("#link-logout").click(function (e) {
            e.preventDefault();
            mySpires.api({logout: 1}).then(window.close).catch(console.log);
        });

        $("#logged_in_content").show();
    }

    $(".main-content").slideDown();

    /* ============================== Record Box ============================== */

    if(user) {
        active_tab().then((tab) => {
            let query = mySpires_Plugin.analyze_url(tab.url);
            if(query && query.q) {
                $loader.show();
                mySpires_Plugin.api(query).then(response => {
                    let bar = new mySpires_Bar(response[query.q], $("#record-wrapper"));
                    bar.record.busy.then(() => {
                        $loader.hide();

                        // Set an on-update function
                        bar.onupdate = function() {
                            let raw_record = JSON.parse(JSON.stringify(bar.record));
                            ask_page("refresh", raw_record).catch(console.log)
                        };

                        // Prepare the content box
                        $("#record-title").prepend(bar.record.title);
                        $("#record-author").html(bar.record.author);
                        $("#record-subwrapper").show();
                        $("#record-wrapper").slideDown();
                    });

                }).catch(console.log);
            }
        }).catch(console.log);
    }

});

function active_tab() {
    return new Promise((resolve, reject) => {
        browser.tabs.query({
            currentWindow: true,
            active: true
        }).then(function (tabs) {
            resolve(tabs[0]);
        }).catch(reject)
    });
}

function ask_page(type, message) {
    return new Promise((resolve, reject) => {
        active_tab().then((tab) => {
            browser.tabs.sendMessage(tab.id, {
                type: type,
                message: message
            }).then(response => {
                resolve(response);
            }).catch(function () {
                reject("No answer received!")
            });
        }).catch(reject)
    });
}