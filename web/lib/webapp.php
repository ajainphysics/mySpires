<?php

use function mySpires\config;

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
        $recaptcha = config("recaptcha");

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