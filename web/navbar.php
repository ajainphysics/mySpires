<?php $user = mySpiresUser::info(); ?>

<nav id="top-bar" class="navbar navbar-expand-lg fixed-top navbar-dark bg-dark">
    <div class="container"> <!-- Navbar Container -->

        <?php if($user || pageLabel != "index") { ?>
            <a class="navbar-brand" href=".">mySpires.</a>
        <?php } ?>

        <button class="navbar-toggler ml-auto"
                type="button" data-toggle="collapse" data-target="#top-bar-collapse"
                aria-controls="top-bar-collapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="top-bar-collapse">
            <ul class="navbar-nav ml-auto pt-3 pt-lg-0">
                <?php
                $user = mySpiresUser::info();
                foreach(Array("index", "library", "history") as $label) {
                    $page = $SITEOPTIONS["pages"][$label];
                    if($page["auth"] == 0 || ($page["auth"] == 1 && isset($user))
                        || ($page["auth"] == -1 && !isset($user))) {
                        ?>
                        <li id="navitem-<?php echo $label ?>" class="nav-item <?php if(pageLabel == $label) echo "active"; ?>">
                            <a class="nav-link" href="<?php echo $page["path"] ?>" data-homenavigation="true">
                                <?php echo $page["name"] ?></a>
                        </li>
                    <?php }
                } ?>

                <?php  if(!$user)  { ?>
                    <li id="navitem-login" class="nav-item">
                        <a class="nav-link" href="/" data-homenavigation="true">Login</a>
                    </li>
                    <li class="nav-item <?php if(pageLabel == "register") echo "active"; ?>">
                        <a class="nav-link" href="/register.php" data-homenavigation="true">Register</a>
                    </li>
                    <li class="nav-item <?php if(pageLabel == "support") echo "active"; ?>">
                        <a class="nav-link" href="/support.php" data-homenavigation="true">Support</a>
                    </li>
                <?php } ?>

                <?php if($user) { ?>
                    <li class="nav-item dropdown">
                        <a id="toolslink" class="nav-link" href="#" data-toggle="dropdown" aria-expanded="false">
                            Tools
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="toolslink">
                            <a class="dropdown-item" href="cite.php"  data-homenavigation="true">
                                <i class="fas fa-user-tag"></i> Author Citations</a>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a id="bibtexlink" class="nav-link" href="#" data-toggle="dropdown" aria-expanded="false">
                            <b><?php echo $user->name; ?></b>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="bibtexlink">
                            <?php if(mySpiresUser::auth()) { ?>
                                <a class="dropdown-item" href="admin.php" data-homenavigation="true">
                                    <i class="fa fa-user-secret"></i> Admin Panel</a>
                            <?php } ?>
                            <a class="dropdown-item" href="preferences.php"  data-homenavigation="true">
                                <i class="fas fa-cogs"></i> Preferences</a>
                            <a class="dropdown-item" href="bin.php"  data-homenavigation="true">
                                <i class="fas fa-trash-alt"></i> Bin</a>
                            <a class="dropdown-item" href="support.php"  data-homenavigation="true">
                                <i class="far fa-life-ring"></i> Support</a>
                            <a class="dropdown-item" href=".?logout=1"  data-homenavigation="true">
                                <i class="fas fa-sign-out-alt"></i> Sign Out</a>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        </div>

    </div> <!-- /container -->
</nav>