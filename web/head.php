<?php
if(!isset($SITEOPTIONS)) $SITEOPTIONS = Array(); // basically to satisfy PhpStorm
?>

<head>
    <script>
        if (window.navigator.standalone === true) {
            if (location.hash === "#retainstate") {
                $lastLocation = localStorage.getItem("lastLocation");
                if ($lastLocation) window.location = $lastLocation;
            } else {
                localStorage.setItem("lastLocation", window.location.href);
            }
        }
    </script>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="description" content="">
    <meta name="author" content="Akash Jain">

    <!-- icon in the highest resolution we need it for -->
    <link rel="icon" sizes="192x192" href="img/mySpiresBorder192.png">

    <!-- Chrome, Firefox OS and Opera -->
    <meta name="theme-color" content="#333333">

    <link rel="manifest" href="manifest.json">

    <?php
    $siteTitle = "mySpires";
    $user = mySpires::user();
    if($user)
        $siteTitle = $SITEOPTIONS["pages"][pageLabel]["name"]." | mySpires - ".$user->name;
    ?>
    <title><?php echo $siteTitle; ?></title>

    <!-- jQuery UI CSS -->
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.min.css" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">

    <!-- Font Family Lato -->
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700' rel='stylesheet' type='text/css'>

    <!-- Select2 CSS: for searchable select fields -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />

    <!-- Font Awesome: for font icons -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">

    <!-- Awesomeplete CSS - For autocomplete, if you do not want to use qQuery UI for this. -->
    <link rel="stylesheet" href="//resources.ajainphysics.com/awesomplete/awesomplete.css" />

    <!-- The main website CSS -->
    <link href="api/lib/mySpires_Bar.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <!-- For iPad -->
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="img/mySpiresBorder192.png">


    <?php include_once("lib/analyticstracking.php") ?>
</head>

<?php
if($user = mySpires::user()) {
    if (!$user->info->dbxtoken && $user->info->dbx_reminder) {
        $alert_id = webapp::alert(
            "You can now connect a Dropbox account to mySpires. mySpires will keep an updated <i>mySpires_"
            . $user->username . ".bib</i> file containing your entire database in Dropbox. Include this file in your texmf folder and keep your references on your fingertips.
            <a href='api/dbxauth.php?redirect=" . urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") . "' class='alert-link'>Link Now</a>. 
            <a href='#' id='dbx_no_reminder' class='alert-link'>Don't Remind Me</a>."
            , "info", 0);

        webapp::script("<script>
        $(function() {
            $('#dbx_no_reminder').click(function(e) {
                e.preventDefault();
                $.get('" . mySpires::$server . "api/dbxauth.php', {'no_reminder': 1});
                $('#alert-" . ($alert_id - 1) . "').alert('close');
            });
        });
    </script>");
    }
}
?>