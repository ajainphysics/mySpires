<?php
namespace mySpires\users;

use mySpires\User;
use function mySa\message;
use function mySpires\config;
use function mySpires\mysqli;

// ============================== Authentication Modules ============================== //

function login($username, $password, $remember = false, $dev_login = false) {
    $server = config("server");

    $user = new User($username);
    if(!$dev_login && !$user->check_password($password)) return false;

    if(!$user->info->enabled) return false;

    $login_string = hash('sha512', $user->info->hash);

    $_SESSION['user_id'] = $user->uid;
    $_SESSION['username'] = $username;
    $_SESSION['login_string'] = $login_string;

    if ($remember) {
        $expire_time = time() + 60 * 60 * 24 * 365;
        /* Set cookie to last 1 year */
        setcookie('user_id', $user->uid, $expire_time, $server->path, $server->host);
        setcookie('login_string', $login_string, $expire_time, $server->path, $server->host);

    } else {
        $expire_time = time() - 7000000;
        /* Cookie expires when browser closes */
        setcookie('user_id', "", $expire_time, $server->path, $server->host);
        setcookie('login_string', "", $expire_time, $server->path, $server->host);
    }

    return true;
}

function logout()
{
    $server = config("server");

    session_start();
    session_unset();

    $expire_time = time() - 7000000;

    setcookie(session_name(), '', $expire_time);
    setcookie('user_id', '', $expire_time);
    setcookie('login_string', '', $expire_time);

    setcookie(session_name(), '', $expire_time, $server->path);
    setcookie('user_id', '', $expire_time, $server->path);
    setcookie('login_string', '', $expire_time, $server->path);

    setcookie(session_name(), '', $expire_time, $server->path, $server->host);
    setcookie('user_id', '', $expire_time, $server->path, $server->host);
    setcookie('login_string', '', $expire_time, $server->path, $server->host);

    session_destroy();

    return true;
}

function register($data) {
    // transformations
    $data->fname = trim($data->fname);
    $data->lname = trim($data->lname);
    $data->email = strtolower(trim($data->email));
    $data->username = strtolower(preg_replace("/[^a-zA-Z0-9_\-]+/", "", trim($data->username)));

    // specification checks
    if(strlen($data->fname) == 0) return false;
    if(!filter_var($data->email, FILTER_VALIDATE_EMAIL)) return false;
    if(strlen($data->username) < 4) return false;
    if(strlen($data->password) < 6) return false;

    // already taken checks
    if((new User($data->email, "email"))->info) return false;
    if((new User($data->email, "username"))->info) return false;

    if ($query = mysqli()->prepare("INSERT INTO users (username, email, first_name, last_name, enabled) VALUES (?, ?, ?, ?, 1)")) {
        $query->bind_param('ssss', $data->username, $data->email, $data->fname, $data->lname);
        if($query->execute()) {
            $user = new User($data->username);
            $user->set_password($data->password);
            message("New user registered: " . $data->username,1);
            return true;
        } else {
            echo $query->error;
        }
    }

    return false;
}

function forgot_password($username) {
    $user = new User($username);
    if(!$user->info) return false;

    $server = config("server");

    $subject = "[mySpires Support] Password Reset Request";

    $msg = "Dear " . $user->display_name . ",\r\n\r\n";
    $msg .= "A password reset request was initiated for your mySpires account. If you initiated this request, please follow this link to reset your password:\r\n\r\n";
    $msg .= $server->location . "register.php?reset=1&username=". $user->username . "&code=" . $user->info->password . "\r\n\r\n";
    $msg .= "If you did not initiate this request, you can ignore this email.\r\n\r\n";
    $msg .= "Cheers,\r\n";
    $msg .= "mySpires";

    $headers = "From: mySpires <admin@ajainphysics.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    message("Password reset request received for " . $user->username . ".", 1);

    return mail($user->email, $subject, $msg, $headers);
}

// ============================== Manipulation ============================== //

$user = null;

/**
 * Checks if a user is logged in and returns the username.
 * @return User User object if user is logged in, null otherwise
 */
function user() {
    global $user;
    if($user) return $user;

    if (
        !isset($_SESSION['user_id'], $_SESSION['login_string'])
        && isset($_COOKIE['user_id'], $_COOKIE['login_string'])
    ) {
        $_SESSION["user_id"] = $_COOKIE["user_id"];
        $_SESSION["login_string"] = $_COOKIE["login_string"];
    }

    // Check if all session variables are set
    if (isset($_SESSION['user_id'], $_SESSION['login_string'])) {
        $user_n = new User($_SESSION['user_id'], "uid");

        if ($user_n->info && hash_equals(hash('sha512', $user_n->info->hash), $_SESSION['login_string'])) {
            $_SESSION['username'] = $user_n->username;
            $user = $user_n; // logged in
        }
    }

    return $user;
}

function username() {
    if(user())
        return user()->username;
    else
        return null;
}

/**
 * Verifies a username.
 * @param string $username Username to test
 * @param bool $default If default is set, passing an empty string will check if a user is logged in and, if so, return true and set the input string to the current username.
 * @return bool
 */
function verify(&$username, $default = false) {
    if($username) {
        $username = (new User($username))->username;
        if($username) return true;
    }
    elseif($default) {
        $username = username();
        if($username) return true;
    }
    return false;
}

function admin() {
    $admins = json_decode(file_get_contents(__DIR__ . "/admins.json"));
    return boolval(username() && in_array(username(), $admins)); // if admin, return true.
}

/**
 * Resets saved Dropbox token.
 * @param string $username Username
 */
function dropbox_reset($username) {
    $user = new User($username);
    $user->update_info(["dbxtoken" => NULL]);
}

function user_list() {
    if($query = mysqli()->prepare("SELECT username FROM users")) {
        $query->execute();
        $results = $query->get_result();

        $users = [];
        while($result = $results->fetch_object()) {
            array_push($users, $result->username);
        }

        return $users;
    }

    return null;
}

// ============================== Tools ============================== //

function surname($name) {
    $e = explode(" ", trim($name));
    return $e[sizeof($e) - 1];
}

/**
 * Compares two hash strings.
 * @param string $str1 First hash string.
 * @param string $str2 Second hash string.
 * @return bool Returns true if the two strings match, false otherwise.
 */
function hash_equals($str1, $str2)
{
    if (strlen($str1) != strlen($str2)) {
        return false;
    } else {
        $res = $str1 ^ $str2;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
        return !$ret;
    }
}