<?php

class Dropbox {
    private $token;
    private $opts;

    public $reset;
    public $reset_argument;

    function __construct($token = "") {
        if($token) {
            $this->token = $token;
        }

        $opts = include(__DIR__ . "/../../../.myspires_config.php");
        $this->opts = $opts->dropbox;
    }

    function reauth($code) {
        $url = "https://api.dropboxapi.com/oauth2/token";
        $data = Array(
            "code" => $code,
            "grant_type" => "authorization_code",
            "client_id" => $this->opts->key,
            "client_secret" => $this->opts->secret,
            "redirect_uri"  => mySpires::$server . "api/dbxauth.php"
        );

        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch));

        $this->token = $response->access_token;

        return $this->token;
    }

    function unlink() {
        if(!$this->token) return;

        $ch = curl_init("https://api.dropboxapi.com/2/auth/token/revoke");
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, "null");
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/json"
        ));
        curl_exec($ch);

        if(isset($this->reset_argument))
            call_user_func($this->reset, $this->reset_argument);
        else
            call_user_func($this->reset);

        return;
    }

    function user() {
        if(!$this->token) return NULL;

        $ch = curl_init("https://api.dropboxapi.com/2/users/get_current_account");
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, "null");
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/json"
        ));
        $userData = json_decode(curl_exec($ch));

        if(isset($userData->error)) {
            $this->unlink();
            return NULL;
        }
        else return $userData;
    }

    function upload($filecontents, $path) {
        // $filecontents = str_replace('&amp;', '&', $filecontents);

        if(!$this->token) return NULL;

        $ch = curl_init("https://content.dropboxapi.com/2/files/upload");
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $filecontents);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/octet-stream",
            "Dropbox-API-Arg: {\"path\": \"".$path."\", \"mode\": {\".tag\": \"overwrite\"}, \"mute\": true}",
            "Content-Length: ".strlen($filecontents)
        ));
        $file = json_decode(curl_exec($ch));

        if(isset($userData->error)) {
            $this->unlink();
            return NULL;
        }
        else return $file;
    }
}