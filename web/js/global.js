// Call mySa on every page load.

$(function () {
    $("body").addClass("mySpires-context");
});

$.post(mySpires.server + "api/mysa.php", function (response) {
    console.log(response);
});

$("#close-foot-message").click(function() {
    $("#foot-message").hide();
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(registration) {
            // console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }, function(err) {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}

let $homeAppNotification = $("#install-home-app-notification"),
    $footMessage = $("#foot-message");

let deferredAddToHomePrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    // Stash the event so it can be triggered later.
    deferredAddToHomePrompt = e;
    // Update UI notify the user they can add to home screen

    $homeAppNotification.show();
    $footMessage.show();
});

$homeAppNotification.click(function() {
    $footMessage.hide();
    $homeAppNotification.hide();

    deferredAddToHomePrompt.prompt();
    deferredAddToHomePrompt.userChoice
        .then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the A2HS prompt');
            } else {
                console.log('User dismissed the A2HS prompt');
            }
            deferredAddToHomePrompt = null;
        });
});

let foot_alert_counter = 0;

function foot_alert(message, type = "primary", timeout = 5000) {
    let $foot_alerts = $("#foot-alerts .container");

    let id = foot_alert_counter;
    foot_alert_counter++;

    let dismissClasses, dismissButton;
    if(timeout >= 0) {
        dismissClasses = "alert-dismissible fade show";
        dismissButton =
            "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>" +
            "  <span>&times;</span>" +
            "</button>";
    }

    $foot_alerts.prepend(
        "<div id='foot-alert-" + id + "' class='alert alert-" + type + " " +
        dismissClasses + "' role='alert'>" +
        dismissButton +
        message +
        "</div>"
    );

    if(timeout > 0) {
        setTimeout(function () {
            $("#foot-alert-" + id).alert("close");
        }, timeout);
    }
}

/*

if (location.hash) {
    let hash = location.hash;
    history.replaceState(null,null, location.href.split("#")[0]);

    $(function() {
        anchorScroll(hash);
        // console.log(hash);
        // history.pushState(null,null, hash);
    });
}

$(function() {
    $("a[href^='#']").click(function(e){
        e.preventDefault();
        let hash = $(this).attr("href");
        anchorScroll(hash);
        history.pushState(null,null, hash);
    });
});

function anchorScroll(hash) {
    console.log(1);
    let $target = $(hash);
    if($target.length) {
        let top = $target.offset().top;
        $(window).scrollTop(top - 60);
    }
}

*/