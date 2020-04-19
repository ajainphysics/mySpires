<?php

session_start();

include_once "lib/settings.php";

define("pageLabel", "library");

\library\tools\null_populate($_GET, ["tag"]);

$tag = $_GET["tag"];

if (!\mySpires\users\user()) {
    header("Location: /");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>" class="library-tag-Untagged">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">

        <div class="container">

            <?php webapp::display_alerts(); ?>

            <input type="hidden" id="filter-tags" value="<?php echo $tag; ?>">

            <nav class="title-nav navbar navbar-expand-lg navbar-light">

                <div class="main-title">
                    <i class="main-icon fas fa-hdd"></i>
                    <div>
                        <h1 class="main-heading">Library</h1>
                        <span class="main-breadcrumb"></span>
                    </div>
                </div>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#title-nav-collapse" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="title-nav-collapse">
                    <ul class="navbar-nav ml-auto pt-2 pt-lg-0">
                        <li class="nav-item dropdown untagged-hide search-hide">
                            <a id="bibtexlink" class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"
                               aria-expanded="false">
                                <i class="fas fa-database"></i> BibTeX
                            </a>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="bibtexlink">
                                <a class="dropdown-item" href="api/bib.php">
                                    <i class="fas fa-database"></i> Entire Database
                                </a>
                                <a class="dropdown-item" href="api/bib.php?tag=<?php echo $tag; ?>">
                                    <i class="fas fa-tag"></i> Current Tag
                                </a>
                            </div>
                        </li>

                        <li class="nav-item untagged-hide search-hide">
                            <a id="edit-tag-btn" class="nav-link" href="#">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </li>

                        <li class="nav-item untagged-show search-hide">
                            <a id="bibtexlink" class="nav-link" href="api/bib.php">
                                <i class="fas fa-database"></i> BibTeX
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="library-area">
                <p id="tag-description"></p>

                <div class="edit-tag">
                    <form id="edit-tag-form" novalidate>
                        <small class="text-muted"><label for="edit-tag-parent">Parent Tag</label></small>
                        <input id="edit-tag-parent" type="text" class="form-control" name="tag-parent">
                        <p id="edit-tag-parent-invalid-warning" class="invalid-warning text-danger">Tag names can only  contain alphanumeric characters, spaces, and hyphens, along with forward slash to implement directory structure.</p>
                        <small class="text-muted">
                            <label for="edit-tag-name" class="label-required">Tag Name</label>
                        </small>
                        <input id="edit-tag-name" type="text" class="form-control" name="tag-name">
                        <p id="edit-tag-name-invalid-warning" class="invalid-warning text-danger">Tag names can only  contain alphanumeric characters, spaces, and hyphens.</p>
                        <p id="edit-tag-exists-warning" class="invalid-warning text-danger">A tag with this name already exists.</p>

                        <small class="text-muted"><label for="edit-tag-description">Description</label></small>
                        <textarea id="edit-tag-description" class="form-control" rows="3" name="tag-description"></textarea>

                        <button id="edit-tag-save-btn" class="btn btn-sm btn-outline-primary">Save</button>
                        <button id="edit-tag-reset-btn" class="btn btn-sm btn-outline-warning">Cancel</button>
                        <button id="edit-tag-delete-btn" class=" btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div>

            <form class="main-search-bar" target="pseudo-search-target" action="about:blank" autocomplete="on">
                <div class="form-group">
                    <input class="search-field form-control form-control-lg" name="q" type="text"
                           placeholder="Search">
                    <div>
                        <span class="fa fa-times search-reset"></span>
                        <button type="submit" class="search-button btn btn-primary btn-lg pull-right">Go</button>
                    </div>
                </div>
                <small class="form-text text-muted">
                    Search for titles, authors, tags, or user comments.
                </small>
            </form>

            <div class="search-area">
                <div class="row search-results"></div>
            </div>

            <div class="library-area">
                <div id="empty-message">
                    <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">
                    <p class="introduction">
                        Hey <?php echo $user->display_name; ?>! You have not added any records to your mySpires library yet. If you have the <a href="support.php#help-plugin">mySpires browser plugin</a> installed, you can add records on the go when you visit inspirehep.net or arxiv.org. Otherwise, head over to the <a href="/">Welcome</a> page to add some records manually. Find more information on the <a href="support.php#help-add">Support</a> page.
                    </p>
                </div>

                <div class="row subtags-wrapper">
                    <div class="col-md-12 starred-tags"></div>
                    <div class="col-md-12 recent-tags"></div>
                    <div class="col-md-12 subtags"></div>
                </div>

                <div class="row paper-boxes">
                    <div class="col-md-12">
                        <div class="records-header">
                            <div class="filters">
                                <select class="custom-select" id="filter-authors"></select>

                                <button id="filter-sort-button" type="button" data-order="desc"
                                        class="btn btn-secondary pull-right"><i class='fas fa-sort-numeric-down'></i>
                                </button>
                                <div id="filter-method-box" class="pull-right">
                                    <i class='fas fa-sort'></i>
                                    <select class="custom-select pull-right">
                                        <option value="modified">Viewed/Modified</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                            <h3>Records</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>
