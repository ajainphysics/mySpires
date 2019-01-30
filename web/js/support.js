'use strict';

// Example starter JavaScript for disabling form submissions if there are invalid fields

function validate_subject() {
    let $subject = $("#message-subject");
    $subject.val($subject.val().trim());
    $subject.removeClass("is-invalid").addClass("is-valid");
    return true;
}

function validate_body() {
    let $body = $("#message-body"),
        $bodyWarning = $("#body-invalid-warning");

    $body.val($body.val().trim());

    if($body.val().length === 0) {
        $body.addClass("is-invalid").removeClass("is-valid");
        $bodyWarning.show();
        return false;
    }

    $body.removeClass("is-invalid").addClass("is-valid");
    $bodyWarning.hide();
    return true;
}

function validate_email() {
    let $email = $("#message-sender"),
        $emailWarning = $("#email-invalid-warning");

    $email.val($email.val().trim().toLowerCase());

    let re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    if(!re.test($email.val())) {
        $email.addClass("is-invalid").removeClass("is-valid");
        $emailWarning.show();
        return false;
    }

    $email.removeClass("is-invalid").addClass("is-valid");
    $emailWarning.hide();
    return true;
}

function validate_recaptcha() {
    if($("#message-username").val()) return true;

    let $recaptchaWarning = $("#recaptcha-warning");

    if(!grecaptcha.getResponse()) {
        $recaptchaWarning.show();
        return false;
    } else {
        $recaptchaWarning.hide();
        return true;
    }
}

function validate_form() {
    let validation = [
        validate_subject(),
        validate_body(),
        validate_email(),
        validate_recaptcha()
    ];

    return validation.every(function(a) {return a});
}

$(function() {
    let $subject = $("#message-subject"),
        $body = $("#message-body"),
        $email = $("#message-sender");

    $subject.blur(validate_subject);
    $body.blur(validate_body);
    $email.blur(validate_email);

    // Fetch all the forms we want to apply custom Bootstrap validation styles to

    let form = $(".support-form");
    form.submit(function(e) {
        if(!validate_form()) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});