<?php
/**
 * Created by PhpStorm.
 * User: akash
 * Date: 2018-12-22
 * Time: 17:47
 */

class mySpiresUser {

    static function user_list() {
        if($query = mySpires::db()->prepare("SELECT username FROM users")) {
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

    /**
     * Returns user information without login authentication.
     * @param $username
     * @return stdClass
     */
    static private function private_info($username) {
        if($query = mySpires::db()->prepare("SELECT * FROM users WHERE username = ?")) {
            $query->bind_param("s", $username);
            $query->execute();

            if($result = $query->get_result()->fetch_object()) {
                $result->name = $result->first_name . " " . $result->last_name;
                return $result;
            }
        }
        return null;
    }

    static private function private_update_info($optarray, $username) {
        $db = mySpires::db();

        $types = "";
        $valquery = "";
        $values = [];
        foreach($optarray as $key => $value) {
            $valquery .= $key . " = ?, ";
            $types .= "s";
            if(is_string($value)) $value = $db->real_escape_string($value);
            array_push($values, $value);
        }
        $valquery .= "last_seen=now()";

        if($query = $db->prepare("UPDATE users SET " . $valquery . " WHERE username = ?")) {
            array_push($values, $username);
            $query->bind_param($types . "s", ...$values);
            if($query->execute()) return true;
        }

        return false;
    }

    /**
     * Checks if a user is logged in and returns the username.
     * @return string Username if user is logged in, null otherwise
     */
    static function current_username()
    {
        $db = mySpires::db();
        if (
            !isset($_SESSION['user_id'], $_SESSION['login_string'])
            && isset($_COOKIE['user_id'], $_COOKIE['login_string'])
        ) {
            $_SESSION["user_id"] = $_COOKIE["user_id"];
            $_SESSION["login_string"] = $_COOKIE["login_string"];
        }

        // Check if all session variables are set
        if (isset($_SESSION['user_id'], $_SESSION['login_string'])) {
            $user_id = $_SESSION['user_id'];
            $login_string = $_SESSION['login_string'];

            if ($stmt = $db->prepare("SELECT username, hash FROM users WHERE id = ? LIMIT 1")) {
                $stmt->bind_param('i', $user_id); // Bind "$user_id" to parameter.
                $stmt->execute();   // Execute the prepared query.
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    // If the user exists get variables from result.
                    $stmt->bind_result($username, $hash);
                    $stmt->fetch();
                    $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);

                    $login_check = hash('sha512', $hash);
                    if (hash_equals($login_check, $login_string)) {
                        $_SESSION['username'] = $username;
                        return $username; // logged in
                    }
                }
            }
        }
        return null; // If reached till this point, then not logged in.
    }

    /**
     * Checks authorisation on behalf of a username.
     * If a <i>username</i> is passed, checks if either an admin or the <i>username</i> is logged in.
     * If no <i>username</i> is passed, checks if an admin is logged in.
     * Admins are always authorised.
     *
     * @param string [$username] <i>username</i> to test.
     * @return bool
     */
    static function auth($username = null) {
        $current_username = self::current_username(); // current username
        if(!$current_username) return false; // if no user logged in, return false.

        $admins = json_decode(file_get_contents(__DIR__ . "/admins.json"));
        if(in_array($current_username, $admins)) return true; // if admin, return true.

        if($current_username == $username) return true; // If current user matches the test user, return true.

        return false;
    }

    static function check_password($username, $password) {
        // Need to make sure that usernames don't have funny characters
        $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);

        if($user = self::private_info($username)) {
            if (crypt($password, $user->password) == $user->password) return true; // password matched
        }

        return false; // password not matched
    }

    /**
     * Changes password of a user.
     * @param string $username <i>Username</i> of the user.
     * @param string $current_password <i>Current password</i> of the user.
     * @param string $new_password <i>New password</i>.
     * @return bool Returns <i>true</i> if password successfully changed, <i>false</i> otherwise.
     */
    static function change_password($username, $current_password, $new_password)
    {
        if(!self::auth($username)) return false; // check authorisation

        // if not admin, check if the current_password matches
        if(!self::auth() AND !self::check_password($username, $current_password)) return false;

        return self::set_password($username, $new_password);
    }

    static function check_reset_password_code($username, $code) {
        if($user = self::private_info($username)) {
            if($user->password == $code) return true;
        }

        return false;
    }

    static function reset_password($username, $code, $password) {
        if($user = self::private_info($username)) {
            if($user->password == $code) {
                return self::set_password($username, $password);
            }
        }

        return false;
    }

    static private function set_password($username, $password) {
        $db_password = crypt($password, "$2y$12$" . bin2hex(openssl_random_pseudo_bytes(22)));
        $hash = bin2hex(openssl_random_pseudo_bytes(32));

        return self::private_update_info(["password" => $db_password, "hash" => $hash], $username);
    }

    /**
     * Logs in a user.
     * This method logs in a user with given credentials.
     * It also sets appropriate cookies if opted for remembering the login.
     *
     * @param string $username <i>Username</i> or <i>email</i> of the user.
     * @param string $password <i>Password</i> of the user.
     * @param bool $remember If <b>remember</b> is set to true, user will be remembered between sessions.
     * @return bool
     */
    static function login($username, $password, $remember = false, $dev_login = false) {
        if(!self::check_password($username, $password) && !$dev_login) {
            $username = self::email_to_username($username); // check with email
            if(!self::check_password($username, $password)) return false;
        }

        $user = self::private_info($username);
        if(!$user->enabled) return false;

        $user_id = preg_replace("/[^0-9]+/", "", $user->id);
        $login_string = hash('sha512', $user->hash);

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['login_string'] = $login_string;

        if ($remember) {
            $expire_time = time() + 60 * 60 * 24 * 365;
            /* Set cookie to last 1 year */
            setcookie('user_id', $user_id, $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);
            setcookie('login_string', $login_string, $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);

        } else {
            $expire_time = time() - 7000000;
            /* Cookie expires when browser closes */
            setcookie('user_id', "", $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);
            setcookie('login_string', "", $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);
        }

        return true;
    }

    /**
     * Logs out the user.
     * This method logs out the current user and renders all the cookies invalid.
     * @return bool Returns <i>true</i> if logged out properly, false otherwise
     */
    static function logout()
    {
        session_start();
        session_unset();

        $expire_time = time() - 7000000;

        setcookie(session_name(), '', $expire_time);
        setcookie('user_id', '', $expire_time);
        setcookie('login_string', '', $expire_time);

        setcookie(session_name(), '', $expire_time, mySpires::$serverfolder);
        setcookie('user_id', '', $expire_time, mySpires::$serverfolder);
        setcookie('login_string', '', $expire_time, mySpires::$serverfolder);

        setcookie(session_name(), '', $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);
        setcookie('user_id', '', $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);
        setcookie('login_string', '', $expire_time, mySpires::$serverfolder, mySpires::$serverdomain);

        session_destroy();

        return true;
    }

    /**
     * Returns user information.
     * If no username is specified, returns user information of the current user.
     *
     * @param string $username
     * @return stdClass
     */
    static function info($username = null) {
        if(!$username) $username = self::current_username();
        // if (!self::auth($username)) return null;

        return self::private_info($username);
    }

    /**
     * Sets user options in users table.
     * @param array [$optarray] An array of options.
     * @param string [$username] Username to set options for.
     * @return bool
     */
    static function update_info($optarray, $username = null) {
        if(!$username) $username = self::current_username();
        // if (!self::auth($username)) return false;

        return self::private_update_info($optarray, $username);
    }

    static function email_to_username($email) {
        if ($query = mySpires::db()->prepare("SELECT username FROM users WHERE email = ?")) {
            $query->bind_param('s', $email);
            $query->execute();    // Execute the prepared query.
            $query->bind_result($username);

            if($query->fetch()) return $username;
        }

        return null;
    }

    static function username_exists($username) {
        if(self::private_info($username)) return true;
        else return false;
    }

    static function register($data) {
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
        if(mySpiresUser::email_to_username($data->email)) return false;
        if(mySpiresUser::username_exists($data->username)) return false;

        if ($query = mySpires::db()->prepare("INSERT INTO users (username, email, first_name, last_name, enabled) VALUES (?, ?, ?, ?, 1)")) {
            $query->bind_param('ssss', $data->username, $data->email, $data->fname, $data->lname);
            if($query->execute()) {
                self::set_password($data->username, $data->password);
                mySa::message("New user registered: " . $data->username,1);
                return true;
            } else {
                echo $query->error;
            }
        }

        return false;
    }

    static function forgot_password($username) {
        if($temp = mySpiresUser::email_to_username($username)) $username = $temp;

        $user = mySpiresUser::private_info($username);
        if(!$user) return false;

        $config = include(__DIR__ . "/../../../.myspires_config.php");
        $config = $config->server;

        $subject = "[mySpires Support] Password Reset Request";

        $msg = "Dear " . $user->first_name . ",\r\n\r\n";
        $msg .= "A password reset request was initiated for your mySpires account. If you initiated this request, please follow this link to reset your password:\r\n\r\n";
        $msg .= $config->path . "register.php?reset=1&username=". $user->username . "&code=" . $user->password . "\r\n\r\n";
        $msg .= "If you did not initiate this request, you can ignore this email.\r\n\r\n";
        $msg .= "Cheers,\r\n";
        $msg .= "mySpires";

        $headers = "From: mySpires <admin@ajainphysics.com>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        mySa::message("Password reset request received for " . $user->username . ".", 1);

        return mail($user->email, $subject, $msg, $headers);
    }

    /**
     * Creates a Dropbox instance using saved token or provided authorization code.
     * @param string $username Username
     * @param string [$code] Authorization code.
     * @return Dropbox
     */
    static function dropbox($username, $code = "") {
        // if (!self::auth($username)) return null;

        if($code) {
            $dbx = new Dropbox();
            self::update_info(["dbxtoken" => $dbx->reauth($code)], $username);
        } else {
            $dbx = new Dropbox(self::info($username)->dbxtoken);
        }

        $dbx->reset = "mySpiresUser::dropbox_reset";
        $dbx->reset_argument = $username;

        return $dbx;
    }

    /**
     * Resets saved Dropbox token.
     * @param string $username Username
     */
    static function dropbox_reset($username) {
        // if (!self::auth($username)) return;

        self::update_info(["dbxtoken" => NULL], $username);
        return;
    }
}