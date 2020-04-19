<?php

use mySpires\Records;
use function mySpires\bibtex\add as add_bibtex;
use function mySpires\bibtex\remove as remove_bibtex;
use function mySpires\query;
use function mySpires\records\fetch;
use function mySpires\records\find;
use function mySpires\users\user;
use function mySpires\users\username;

session_start();

include_once "lib/settings.php";
include_once "lib/webapp.php";

define("pageLabel", "bibtex");

if (!user()) {
    header("Location: /");
    exit();
}

if($_POST) {
    $_SESSION["shout"] = $_POST;
    header("HTTP/1.1 303 See Other");
    header("Location: /bibtex.php");
    die();
}
elseif(key_exists("shout", $_SESSION) && $_SESSION["shout"]) {
    $shout = $_SESSION["shout"];
    if(array_key_exists("submit-bibtex", $shout) && $shout['submit-bibtex']) {
        add_bibtex($shout["bibtex"]);
    }
    if(array_key_exists("delete-bibkey", $shout) && $shout['delete-bibkey']) {
        remove_bibtex($shout["delete-bibkey"]);
    }
    if(array_key_exists("save-record", $shout) && $shout['save-record']) {
        $record = find(["id" => $shout['save-record']])->first();
        $record->save();
    }
    $_SESSION["shout"] = null;
}

function found($result, $record) {
    if($result->eprint && $record->arxiv == $result->eprint) return true;
    if($result->doi && $record->doi == $result->doi) return true;
    if($record->bibkey == $result->bibkey) return true;
    if($record->ads == $result->bibkey) return true;
    return false;
}

function matched($result, $record) {
    if($record->bibkey == $result->bibkey) return true;
    if(!$record->bibkey && $record->ads == $result->bibkey) return true;
    return false;
}

$results = query("SELECT * FROM custom_bibtex WHERE username = '" . user()->username . "' ORDER BY title, author");

$result_array = []; // Array of results indexed by bibkeys
$id_array = []; // Array of ids indexed by bibkeys
$matched_keys = []; // List of bibkeys clashing with the database

// Prepare the search arrays
$search_bibkey = [];
$search_arxiv = [];
$search_doi = [];
$find_results = [];
while($result = $results->fetch_object()) {
    if($result->redundant) {
        $search_bibkey[] = $result->bibkey;
        if ($result->eprint) $search_arxiv[] = $result->eprint;
        if ($result->doi) $search_arxiv[] = $result->doi;
        $find_results[] = $result;
    }
    $result_array[$result->bibkey] = $result;
    $id_array[$result->bibkey] = null;
}

// Find in mySpires database
$myS = find([
    "arxiv" => $search_arxiv,
    "bibkey" => $search_bibkey,
    "ads" => $search_bibkey,
    "doi" => $search_doi]);

// Dig through results
$search_bibkey = [];
$search_arxiv = [];
$search_doi = [];
$lookup_results = [];
foreach($find_results as $result) {
    foreach($myS->records() as $record) {
        if(found($result, $record)) {
            $id_array[$result->bibkey] = $record->id;
            if(matched($result, $record)) $matched_keys[] = $result->bibkey;
            break;
        }
    }
    if(!$id_array[$result->bibkey]) {
        $search_bibkey[] = $result->bibkey;
        if ($result->eprint) $search_arxiv[] = $result->eprint;
        if ($result->doi) $search_arxiv[] = $result->doi;
        $lookup_results[] = $result;
    }
}

// Fetch remaining ones online
$myS = fetch([
    "arxiv" => $search_arxiv,
    "bibkey" => $search_bibkey,
    "ads" => $search_bibkey,
    "doi" => $search_doi]);

foreach($lookup_results as $result) {
    foreach($myS->records() as $record) {
        if(found($result, $record)) {
            $id_array[$result->bibkey] = $record->id;
            if(matched($result, $record)) $matched_keys[] = $result->bibkey;
            break;
        }
    }
    if(!$id_array[$result->bibkey])
        query("UPDATE custom_bibtex SET redundant = 0 WHERE bibkey = '{$result->bibkey}'");
}

$sort_duplicate = [];
$sort_matched = [];
$sort_found = [];
$sort_remaining = [];
foreach ($id_array as $key => $id) {
    if($id && array_count_values(array_filter($id_array))[$id] > 1) {
        if(key_exists($id,$sort_duplicate)) $sort_duplicate[$id][] = $key;
        else $sort_duplicate[$id] = [$key];
    }
    elseif($id && in_array($key, $matched_keys)) $sort_matched[$id] = $key;
    elseif($id) $sort_found[$id] = $key;
    else $sort_remaining[] = $key;
}

$found_ids = implode(",", array_unique(array_filter($id_array)));

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-bibtex">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">

        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fa fa-database"></i>
                    <div id="title-wrapper"><h1>Custom BibTeX</h1></div>
                </div>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#title-nav-collapse" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="title-nav-collapse">
                    <ul class="navbar-nav ml-auto pt-2 pt-lg-0">
                        <li class="nav-item untagged-show search-hide">
                            <a id="bibtexlink" class="nav-link" href="api/bib.php">
                                <i class="fas fa-database"></i> Download Database
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <p>
                You can add custom BibTeX entries here to be included in the mySpires database <code>mySpires_<?php echo username(); ?>.bib</code>. Make sure that there are no syntax errors, otherwise they will be propagated to the final BibTeX file. Adding a repeated key will overwrite the previously added entry. Learn more about BibTeX formatting <a href="https://en.wikibooks.org/wiki/LaTeX/Bibliography_Management">here</a>.
            </p>
            <p>
                If deriving the BibTeX entries from <a href="https://inspirehep.net">inspirehep.net</a> or <a href="https://ui.adsabs.harvard.edu/">ui.adsabs.harvard.edu</a>, it is preferable to import them directly into mySpires using the <a href="support.php#help-plugin">mySpires plugin</a>.
            </p>

            <?php if(sizeof($sort_duplicate) || sizeof($sort_found)) { ?>
                <div class="alert alert-primary" role="alert">
                    Some BibTeX entries were found in the mySpires database. It is recommended to replace them with the matching records.
                </div>
            <? } ?>

            <?php if(sizeof($sort_matched)) { ?>
                <div class="alert alert-warning" role="alert">
                    Keys of some BibTeX entries clash with the existing records in the mySpires database. Should you choose to keep them, they will overwrite the BibTeX entries automatically generated by mySpires.
                </div>
            <? } ?>

            <div class="bib-entries row" data-found-records="<?php echo $found_ids; ?>">
                <?php

                foreach ($sort_duplicate as $id => $duplicates) { ?>
                    <div class='bib-entry record-duplicate match col-lg-<?php echo 3*sizeof($duplicates); ?>'
                         data-record='<?php echo $id; ?>'>
                        <div class="bibtex-wrapper alert-primary">
                            <div class="duplicated-bibtex">
                                <?php foreach ($duplicates as $key) { ?>
                                    <div class='bibtex'>
                                        <?php echo "<pre class='alert-primary'>" . $result_array[$key]->bibtex . "</pre>"; ?>
                                        <?php bibtex_edit($key); ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="found-box"></div>
                            <?php bibtex_replace(implode(",",$duplicates), $id, "primary"); ?>
                        </div>
                    </div>
                <?php }

                foreach ($sort_found as $id => $key) { ?>
                    <div class='bib-entry record-found match col-lg-3' data-record=' <?php echo $id; ?>'>
                        <div class="bibtex-wrapper alert-primary">
                            <div class='bibtex'>
                                <?php echo "<pre class='alert-primary'>" . $result_array[$key]->bibtex . "</pre>"; ?>
                                <?php bibtex_edit($key); ?>
                            </div>
                            <div class="found-box"></div>
                            <?php bibtex_replace($key, $id, "primary"); ?>
                        </div>
                    </div>
                <?php }

                foreach ($sort_matched as $id => $key) { ?>
                    <div class='bib-entry record-matched match col-lg-3' data-record=' <?php echo $id; ?>'>
                        <div class="bibtex-wrapper alert-warning">
                            <div class='bibtex'>
                                <?php echo "<pre class='alert-warning'>" . $result_array[$key]->bibtex . "</pre>"; ?>
                                <?php bibtex_edit($key); ?>
                            </div>
                            <div class="found-box"></div>
                            <?php bibtex_replace($key, $id, "warning"); ?>
                        </div>
                    </div>
                <?php }

                foreach ($sort_remaining as $key) { ?>
                    <div class='bib-entry col-lg-3'>
                        <div class='bibtex'>
                            <?php echo "<pre>" . $result_array[$key]->bibtex . "</pre>"; ?>
                            <?php bibtex_edit($key); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <form id="bib-form" method="post" action="bibtex.php">
                <div class="form-group">
                    <label for="form-bibtex">Add new entries:</label>
                    <textarea class="form-control" id="form-bibtex" name="bibtex" placeholder="BibTeX Code" rows="10" tabindex="2"></textarea>
                    <input type="hidden" name="submit-bibtex" value="1">
                    <button type="submit" class="btn btn-sm btn-primary" tabindex="3">Save</button>
                </div>
            </form>

        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

<?php

function bibtex_edit($key) { ?>
    <form class="form-edit-bibtex" method="post" action="bibtex.php">
        <button type="button" class="btn btn-sm btn-outline-primary bib-edit-button">Edit</button>
        <input type="hidden" name="delete-bibkey" value="<?php echo $key; ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
    </form>
<?php }

function bibtex_replace($keys, $id, $context) { ?>
    <form class="form-replace-bibtex" method="post" action="bibtex.php">
        <input type="hidden" name="delete-bibkey" value="<?php echo $keys; ?>">
        <input type="hidden" name="save-record" value="<?php echo $id; ?>">
        <button type="submit" class="btn btn-sm btn-<?php echo $context; ?> replace-button">
            Replace with mySpires Record
        </button>
    </form>
<?php } ?>

