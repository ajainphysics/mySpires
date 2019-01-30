'use strict';

// Example starter JavaScript for disabling form submissions if there are invalid fields

function validate_fname() {
    let $fname = $("#fname"),
        $fnameWarning = $("#fname-invalid-warning");

    $fname.val($fname.val().trim());

    return new Promise((resolve) => {
        if($fname.val().length === 0) {
            $fname.addClass("is-invalid").removeClass("is-valid");
            $fnameWarning.show();
            resolve(false);
            return;
        }

        $fname.removeClass("is-invalid").addClass("is-valid");
        $fnameWarning.hide();
        resolve(true);
    });
}

function validate_lname() {
    let $lname = $("#lname");

    $lname.val($lname.val().trim());

    return new Promise((resolve) => {
        $lname.removeClass("is-invalid").addClass("is-valid");
        resolve(true);
    });
}

function validate_email() {
    let $email = $("#email"),
        $emailWarning = $("#email-invalid-warning"),
        $emailTaken = $("#email-taken-warning");

    $email.val($email.val().trim().toLowerCase());

    return new Promise((resolve) => {
        $.post("api/registration_validation.php",
            {"email": $email.val()}, function(allowed) {
                let re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

                if(!re.test($email.val())) {
                    $email.addClass("is-invalid").removeClass("is-valid");
                    $emailWarning.show();
                    $emailTaken.hide();
                    resolve(false);
                    return;
                }

                if(!allowed) {
                    $email.addClass("is-invalid").removeClass("is-valid");
                    $emailWarning.hide();
                    $emailTaken.show();
                    resolve(false);
                    return;
                }

                $email.removeClass("is-invalid").addClass("is-valid");
                $emailWarning.hide();
                $emailTaken.hide();
                resolve(true);

            }, "json");
    });
}

function validate_username() {
    let $username = $("#username"),
        $usernameWarning = $("#username-invalid-warning"),
        $usernameTaken = $("#username-taken-warning");

    $username.val($username.val().trim().toLowerCase());

    return new Promise((resolve) => {
        $.post("api/registration_validation.php",
            {"username": $username.val()}, function(allowed) {
                let re = /^[a-z0-9]+$/;

                if(!re.test($username.val()) || $username.val().length < 4) {
                    $username.addClass("is-invalid").removeClass("is-valid");
                    $usernameWarning.show();
                    $usernameTaken.hide();
                    resolve(false);
                    return;
                }

                if(!allowed) {
                    $username.addClass("is-invalid").removeClass("is-valid");
                    $usernameWarning.hide();
                    $usernameTaken.show();
                    resolve(false);
                    return;
                }

                $username.removeClass("is-invalid").addClass("is-valid");
                $usernameWarning.hide();
                $usernameTaken.hide();
                resolve(true);

            }, "json");
    });
}

function validate_password() {
    let $password = $("#password"),
        $passwordWarning = $("#password-invalid-warning");

    return new Promise((resolve) => {
        if($password.val().length < 6) {
            $password.addClass("is-invalid").removeClass("is-valid");
            $passwordWarning.show();
            resolve(false);
            return;
        }

        $password.removeClass("is-invalid").addClass("is-valid");
        $passwordWarning.hide();
        resolve(true);
    });
}

function validate_re_password() {
    let $password = $("#password"),
        $re_password = $("#re-password"),
        $re_password_warning = $("#re-password-invalid-warning");

    return new Promise((resolve) => {
        if($re_password.val() !== $password.val()) {
            $re_password.addClass("is-invalid").removeClass("is-valid");
            $re_password_warning.show();
            resolve(false);
            return;
        }

        $re_password.removeClass("is-invalid").addClass("is-valid");
        $re_password_warning.hide();
        resolve(true);
    });
}

function validate_recaptcha() {
    let $recaptchaWarning = $("#recaptcha-warning");

    if(!grecaptcha.getResponse()) {
        $recaptchaWarning.show();
        return false;
    } else {
        $recaptchaWarning.hide();
        return true;
    }
}

async function validate_form() {
    let validation = await Promise.all([
        validate_fname(),
        validate_lname(),
        validate_email(),
        validate_username(),
        validate_password(),
        validate_re_password(),
        validate_recaptcha()
    ]);

    return validation.every(function(a) {return a});
}

$(function() {
    let $fname = $("#fname"),
        $lname = $("#lname"),
        $email = $("#email"),
        $username = $("#username"),
        $password = $("#password");

    /*
    $fname.val("John");
    $lname.val("Doe");
    $email.val("john.doe@email.com");
    $username.val("johndoe");
    $password.val("123456");
    */

    $fname.blur(validate_fname);
    $lname.blur(validate_lname);
    $email.blur(validate_email);
    $username.blur(validate_username);
    $password.blur(validate_password);

    // Fetch all the forms we want to apply custom Bootstrap validation styles to

    let form = $(".register-form");
    form.submit(function(e) {
        e.preventDefault();
        e.stopPropagation();

        validate_form().then(function(valid) {
            if(valid) {
                form.unbind("submit").submit();
            }
        }).catch(console.log);
    });
});


function validate_forgot_username() {
    let $username = $("#forgot-username"),
        $usernameWarning = $("#forgot-username-invalid-warning");

    $username.val($username.val().trim().toLowerCase());

    return new Promise((resolve) => {
        $.post("api/registration_validation.php",
            {"username": $username.val(), "email": $username.val()}, function(not_exists) {
                if(not_exists) {
                    $username.addClass("is-invalid").removeClass("is-valid");
                    $usernameWarning.show();
                    resolve(false);
                    return;
                }

                $username.removeClass("is-invalid").addClass("is-valid");
                $usernameWarning.hide();
                resolve(true);

            }, "json");
    });
}

async function validate_forgot_password_form() {
    let validation = await Promise.all([
        validate_forgot_username(),
        validate_recaptcha()
    ]);

    return validation.every(function(a) {return a});
}

$(function() {
    let $username = $("#forgot-username");

    $username.blur(validate_forgot_username);

    // Fetch all the forms we want to apply custom Bootstrap validation styles to

    let form = $(".forgot-password-form");
    form.submit(function(e) {
        e.preventDefault();
        e.stopPropagation();

        validate_forgot_password_form().then(function(valid) {
            if(valid) {
                form.unbind("submit").submit();
            }
        }).catch(console.log);
    });
});

async function validate_reset_password_form() {
    let validation = await Promise.all([
        validate_password(),
        validate_re_password()
    ]);

    return validation.every(function(a) {return a});
}

$(function() {
    let form = $(".reset-password-form");
    form.submit(function(e) {
        e.preventDefault();
        e.stopPropagation();

        validate_reset_password_form().then(function(valid) {
            if(valid) {
                form.unbind("submit").submit();
            }
        }).catch(console.log);
    });
});
