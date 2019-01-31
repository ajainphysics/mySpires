<?php if(!isset($SITEOPTIONS)) $SITEOPTIONS = Array(); // basically to satisfy PhpStorm ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.2.1.min.js"
        integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>

<!-- jQuery UI JS -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"
        integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js" integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1" crossorigin="anonymous"></script>

<!-- Select2 JS: for searchable select fields -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.full.min.js"></script>

<!-- jQuery.dotdotdot: Ellipsis for text TODO: This is too resource consuming -->
<script src="//resources.ajainphysics.com/jQuery.dotdotdot/jquery.dotdotdot.min.js" type="text/javascript"></script>

<script src="//resources.ajainphysics.com/awesomplete/awesomplete.min.js" async></script>

<script src="js/global.js"></script>

<script src="api/lib/mySpires.js"></script>
<script src="api/lib/mySpires_Bar.js"></script>
<script src="js/components.js"></script>

<script src="//cdn.ajainphysics.com/refspires/refSpires.js"></script>

<?php if(file_exists(__DIR__."/js/".pageLabel.".js")) { ?>
    <script src="js/<?php echo pageLabel; ?>.js"></script>
<?php } ?>

<script>
    if (window.navigator.standalone === true) {
        $(function() {
            $("a[data-homenavigation='true']").each(function() {
                if ($(this).attr("target") !== "_blank") {
                    $(this).on("click", function() {
                        window.location = $(this).attr("href");
                        return false;
                    });
                }
            });
        });
    }
</script>

<script type="text/x-mathjax-config">
    MathJax.Hub.Config({tex2jax: {inlineMath: [['$','$'], ['\\(','\\)']]}});
</script>
<script type="text/javascript" async
        src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.1/MathJax.js?config=TeX-MML-AM_CHTML">
</script>

<?php webapp::fetch_scripts(); ?>

<script src='https://www.google.com/recaptcha/api.js'></script>