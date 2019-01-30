<?php

function utf8ize($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string ($d)) {
        return utf8_encode($d);
    }
    return $d;
}

function safe_json_encode($d) {
    return json_encode(utf8ize($d));
}

function ajSanitizeFilename($string)
{
    $strip = array("*", "\\", "|", ":", "\"", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
        "â€”", "â€“", "<", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', " ", $clean);
    return $clean;
}

function ajUploadToServer($url, $destination)
{
    $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_URL, $url);
    file_put_contents($destination, curl_exec($ch));
}

function preprint($data)
{
    echo "<pre>";
    var_dump($data);
    echo "</pre>";

    return true;
}

function null_populate(&$arr, $keys) {
    foreach($keys as $key) {
        if(!array_key_exists($key, $arr))
            $arr[$key] = null;
    }
}

/**
 * Uploads a file to the server.
 * @param string $url URL to download the file from.
 * @param string $destination Destination of the uploaded file on the server.
 */
function uploadfile($url, $destination)
{
    $agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_URL, $url);
    $contents = curl_exec($ch);
    file_put_contents($destination, $contents);
    return;
}

class webapp
{
    private static $alerts = Array();
    private static $scripts = Array();

    static function alert($message, $type = "info", $timeout = -1) {
        self::$alerts[] = (object)Array(
            "message" => $message,
            "type" => $type,
            "timeout" => $timeout
        );
        return count(self::$alerts);
    }

    static function display_alerts() {
        foreach (self::$alerts as $id => $alert) { ?>
            <div id="alert-<?php echo $id; ?>" class="alert alert-<?php echo $alert->type; ?>
            <?php if($alert->timeout >= 0) echo "alert-dismissible fade show" ?> col-sm-12" role="alert">
                <?php if($alert->timeout >= 0) { ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span>&times;</span>
                    </button>
                <?php } ?>
                <?php echo $alert->message; ?>
            </div>

            <?php if($alert->timeout > 0) {
                webapp::script("<script>
                    $(function() {
                        setTimeout(function() {
                           $('#alert-" . $id . "').alert('close'); 
                        }, $alert->timeout);
                    });
                </script>");
            } ?>

        <?php }
    }

    static function script($script) {
        self::$scripts[] = $script;
    }

    static function fetch_scripts() {
        foreach (self::$scripts as $script) {
            echo $script;
        }
    }

    static function validate_recaptcha($response) {
        $opts = include(__DIR__ . "/../../.myspires_config.php");
        $recaptcha = $opts->recaptcha;

        $url = $recaptcha->url;
        $data = array('secret' => $recaptcha->secret, 'response' => $response);

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if($result) {
            $result = json_decode($result);
            if($result->success) {
                return true;
            }
        }

        return false;
    }
}

