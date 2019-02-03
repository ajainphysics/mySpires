<?php

include_once "lib/settings.php";

null_populate($_POST, ["support-message"]);

define("pageLabel", "support");

$user = mySpiresUser::info();

$post_message = false;
if($_POST["support-message"]) {
    if($user || webapp::validate_recaptcha($_POST["g-recaptcha-response"])) {
        $subject = "[mySpires Support] " . $_POST["message-subject"];

        $msg = "The following message was received by mySpires support.\r\n\r\n";
        $msg .= "============================================================\r\n\r\n";
        if($user) $msg .= "Sender: " . $user->name . " (" . $user->username . ") <" . $_POST["message-sender"] . ">\r\n\r\n";
        else $msg .= "Sender: " . $_POST["message-sender"] . "\r\n\r\n";
        $msg .= "Subject: " . $_POST["message-subject"] . "\r\n\r\n";
        $msg .= $_POST["message-body"] . "\r\n\r\n";
        $msg .= "============================================================\r\n\r\n";
        $msg .= "mySpires.";

        $msg = wordwrap($msg, 70);

        $headers = "From: mySpires <admin@ajainphysics.com>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $post_message = mail("admin@ajainphysics.com", $subject, $msg, $headers);

        mySa::message($msg,1);
    }

    if($post_message) {
        webapp::alert("Message was sent!", "success", "5000");
    } else {
        webapp::alert("An error occurred!", "danger");
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<?php include_once "navbar.php"; // navbar ?>
    
<div class="main-wrapper">
    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">

            <?php if($user) { ?>
                <p class="introduction">
                    Hey <?php echo $user->first_name ?>! Thanks for using mySpires. You should find all the tips and tricks for using the platform on this page. If you find something missing in the documentation or would like to get in touch about new features or bugs, just drop me a line!
                </p>

                <div class="help-toc list-group">
                    <a href="#help-intro" class="list-group-item list-group-item-action">What is mySpires</a>
                    <a href="#help-plugin" class="list-group-item list-group-item-action">Browser Plugin</a>
                    <a href="#help-add" class="list-group-item list-group-item-action">Adding Records to mySpires</a>
                    <a href="#help-tags-comments" class="list-group-item list-group-item-action">Tags and Comments</a>
                    <a href="#help-delete" class="list-group-item list-group-item-action">Deleting Records and Bin</a>
                    <a href="#help-library" class="list-group-item list-group-item-action">Organising your Library</a>
                    <a href="#help-bibtex" class="list-group-item list-group-item-action">BibTeX and LaTeX Integration</a>
                    <a href="#help-backend" class="list-group-item list-group-item-action">Backend Structure</a>
                    <a href="#help-contact" class="list-group-item list-group-item-action">Contact</a>
                </div>

                <section class="help-section">
                    <div id="help-intro" class="fake-anchor"></div>
                    <h2>What is mySpires</h2>

                    <p>
                        mySpires is a bibliography management system which keeps track of your references while you focus on writing your research papers, dissertations, theses, or are just on a literature hunt for your next groundbreaking idea.
                    </p>

                    <p>
                        There are a bunch of bibliography management systems out there catering to researchers, but quite often you need to go out of your way to an independent software or website to add to or maintain your database. This is where mySpires is different. mySpires integrates your reference library directly to your browser for a seamless experience. You never have to leave your browser or go to a dedicated website to keep track of your literature survey.
                    </p>

                    <p>
                        mySpires does this magic via a browser plugin (see <a href="#help-plugin">here</a>). Whenever you visit <a href="https://inspirehep.net" target="_blank">inspirehep.net</a> or <a href="https://arxiv.org" target="_blank">arxiv.org</a>, the plugin adds a small snippet to the page that allows you to add or modify a record in your library without ever having to interrupt your workflow. If you choose to, you can take it a step further and ask mySpires to keep a history of the references you visit. You can revisit your history later at any time and save the records that you found were helpful.
                    </p>

                    <p>
                        mySpires maintains a BibTeX database for you of all the records that you add to your library. If you are into automation, you can link your <a href="https://www.dropbox.com" target="_blank">Dropbox</a> account and mySpires will keep an uptodate copy of your BibTeX database in your Dropbox folder. You can refer to this file in your TeX projects, or better yet, symlink it to your texmf folder. Possibilities are endless.
                    </p>

                </section>

                <section class="help-section">
                    <div id="help-plugin" class="fake-anchor"></div>
                    <h2>Browser Plugin</h2>

                    <p>
                        mySpires works best in conjunction with its browser plugin. The plugin is currently available for <a href="https://chrome.google.com/webstore/detail/myspires/ejidfomdndeogeipjkjigaeaeohbgcpf" target="_blank">Google Chrome</a> and <a href="bin" target="_blank">Mozilla Firefox</a>. As of now, mySpires plugin is not available for Microsoft Edge or Apple Safari.
                    </p>

                </section>

                <section class="help-section">
                    <div id="help-add" class="fake-anchor"></div>
                    <h2>Adding Records to mySpires</h2>

                    <p>
                        mySpires is all about efficiently organising and keeping track of your bibliography. If you have the mySpires browser plugin installed, you can add records to your database on the go while visiting inspirehep.net or arxiv.org. Instead, if you like it old school, you can always head to the <a href="/">Welcome</a> page and perform an inspire search to add records.
                    </p>

                    <p>
                        Just click on the <button class="btn btn-outline-success btn-sm">Save</button> button below any record to add it to your library.
                    </p>

                </section>

                <section class="help-section">
                    <div id="help-tags-comments" class="fake-anchor"></div>
                    <h2>Tags and Comments</h2>

                    <p>
                        When you first save a record to mySpires, it will appear labelled as <button class="btn btn-outline-secondary btn-sm">Untagged</button>. Clicking on the vertical ellipsis <i class="fas fa-ellipsis-v"></i> to its right will switch to edit mode, where you can add tags and personal comments to your saved record.
                    </p>

                    <p>
                        While you start typing a tag name in the designated input field, mySpires will work in the background to suggest existing tags from your database for easy access. Press comma <code>,</code> when you are happy with what you have entered or have picked one from the suggestions, and are ready to move on to the next tag. Press return <code>&#8629;</code> or click on the <button class="btn btn-outline-success btn-sm">Save</button> button at any time to save your modifications.
                    </p>

                    <p>
                        Use forward slash <code>/</code> in your tag names to implement directory structure in your library. This can be handy to keep your library organised as it grows.
                    </p>

                    <p>
                        While in edit mode, you also have the option to add personal comments to your  saved record. This can include some quick comments to help  you identify the record later or a couple of sentences on what the main point of their discussion is. It's upto you! Type away and press return <code>&#8629;</code> or click on the <button class="btn btn-outline-success btn-sm">Save</button> button when you are done.
                    </p>

                    <p>
                        Tag names can only contain alphanumeric characters <code>a-z</code> <code>A-Z</code> <code>0-9</code>, spaces <code> </code>, and hyphens <code>-</code>, along with forward slash <code>/</code> to implement directory structure. Use of any other characters will result in an error. Comments, on the other hand, do not have any restriction on the usage of characters. You can even use <a href="https://www.mathjax.org/" target="_blank">MathJax</a> syntax to render basic TeX into your comments.
                    </p>
                </section>

                <section class="help-section">
                    <div id="help-delete" class="fake-anchor"></div>
                    <h2>Deleting Records and Bin</h2>

                    <p>
                        If you don't want a record in your library anymore, you can click on the vertical ellipsis <i class="fas fa-ellipsis-v"></i> right next to the tags for that record and press the <button class="btn btn-outline-danger btn-sm">Delete</button> button. This will remove the record from your library and move it to the <a href="bin.php">Bin</a>, in  case you change your mind later.
                    </p>

                    <p>
                        If you do change your mind, you can go to the <a href="bin.php">Bin</a> page, where you will find all the records that were once part of your library. Push <button class="btn btn-outline-success btn-sm">Restore</button> to reinstate them to their former glory. Alternatively, press <button class="btn btn-outline-danger btn-sm">Erase</button> and they will be gone for good.
                    </p>

                    <p>
                        Erasing a deleted record from  the bin will also erase it from  your automatically generated mySpires BibTeX file. If this is something you wouldn't want to happen, it is better to leave the deleted records out of your way in the bin. See more <a href="#help-bibtex">here</a>.
                    </p>
                </section>

                <section class="help-section">
                    <div id="help-library" class="fake-anchor"></div>
                    <h2>Organising your Library</h2>
                    <p>
                        All the records that you save to mySpires live in your <a href="library.php">Library</a>. You can visit the library at any time to modify, organise, or otherwise just view your saved records.
                    </p>

                    <p>
                        The library is organised into a directory structure based on your tags. Clicking on a tag will take you to a page with all of its subtags and the records marked with that tag. You can edit the name of the tag on this page or move it to an entirely different location in your library. You  can also star <i class="far fa-star"></i> a tag and it will show up on the library home page for quick access.
                    </p>
                </section>

                <section class="help-section">
                    <div id="help-bibtex" class="fake-anchor"></div>
                    <h2>BibTeX and LaTeX Integration</h2>

                    <p>
                        mySpires maintains a BibTeX database of all your  saved records, which you can download from the <a href="library.php">Library</a> page. You can also find BibTeX databases corresponding to specific tags in your library.
                    </p>

                    <p>
                        If you link your Dropbox account, mySpires will keep an  updated copy of your BibTeX database in your Dropbox folder at
                    </p>

                    <pre>[path to dropbox]/mySpires/bib/mySpires_<?php echo $user->username; ?>.bib</pre>

                    <p>
                        If you use LaTeX to typeset your documents, you can use your mySpires database to generate bibliographies by including the following in your source code
                    </p>

                    <pre>\bibliography{[path to dropbox]/mySpires/bib/mySpires_<?php echo $user->username; ?>,[other bibliographies]}</pre>

                    A good starting point for BibTeX can be found <a href="https://www.overleaf.com/learn/latex/Bibliography_management_with_bibtex#Bibliography_management_with_Bibtex" target="_blank">here</a>. Note that mySpires keeps the BibTeX database in your Dropbox actively updated, so any external changes to the file will be ignored. To include an entry which is not in your mySpires database, you should use a separate BibTeX file specified under <code>[other bibliographies]</code>.

                    <p>
                        If you want a more robust system, you can symlink the mySpires BibTeX database from Dropbox into your texfm folder. On a typical Mac or Unix installation, it can be achieved by issuing the following command in your terminal
                    </p>

                    <pre>ln -s [path to dropbox]/mySpires/bib/mySpires_<?php echo $user->username; ?>.bib [path to texmf]/bibtex/bib/</pre>

                    <p>
                        Having done that, mySpires BibTeX database can be included in your LaTeX projects by simply issuing
                    </p>

                    <pre>\bibliography{mySpires_<?php echo $user->username; ?>,[other bibliographies]}</pre>

                </section>

            <section class="help-section">
                <div id="help-backend" class="fake-anchor"></div>
                <h2>Backend Structure</h2>

                <p>
                    The mySpires project is coded primarily in PHP on the server side, and JavaScript and Sass on the client side. User data and records are hosted on a MySQL server at <?php echo mySpires::$serverdomain; ?>. The entire codebase of mySpires is published on <a href="https://github.com/ajainphysics/mySpires" target="_blank">GitHub</a>.
                </p>

                <p>
                    The mySpires browser plugin is written in JavaScript and communicates with mySpires over Ajax requests. It determines if you are visiting a mySpires supported website (inspirehep.net or arxiv.org) by inspecting the URL of the current tab. If so, it checks the page being visited against your mySpires records and adds a snippet to the page with options to save, modify, or delete the record in mySpires accordingly.
                </p>

                <p>
                    The plugin only acts as a communication channel between your browser and the mySpires server. Your data is not saved offline on your machine, but is instead present on the mySpires server at <?php echo mySpires::$serverdomain; ?>. It enables you to access your mySpires database from anywhere and over multiple machines, say your personal laptop and your workstation at the office, simultaneously.
                </p>

            </section>

            <?php } else { ?>
                <p class="introduction">
                    Hello there! Thanks for checking out mySpires. If you have any questions or comments about the framework or would like to get involved with the project, drop me a line.
                </p>
            <?php  } ?>

            <section class="help-section">
                <div id="help-contact" class="fake-anchor"></div>

                <div class="row">
                    <div class="col-md-7 support-form-wrapper">

                        <?php if($user) { ?>
                            <h2>Contact</h2>

                            <p> Couldn't find what you were looking for? Or have some suggestions to improve mySpires? Get in touch! </p>
                        <?php } ?>

                        <form method="post" class="support-form" novalidate>
                            <input type="hidden" name="support-message" value="1">
                            <input id="message-username" type="hidden" name="message-username" value="<?php if($user) echo $user->username; ?>">

                            <small class="text-muted"><label for="message-subject">Subject</label></small>
                            <input id="message-subject" class="form-control" name="message-subject" type="text" placeholder="Subject">

                            <small class="text-muted"><label class="label-required" for="message-body">Message</label></small>
                            <textarea rows="4" id="message-body" class="form-control" name="message-body" placeholder="Message" required></textarea>
                            <small id="body-invalid-warning" class="invalid-warning form-text text-danger">Body of the message cannot be empty.</small>

                            <small class="text-muted"><label class="label-required" for="message-sender">Your Email Address</label></small>
                            <input id="message-sender" class="form-control" name="message-sender" type="email" placeholder="john.doe@email.com" value="<?php if($user) echo $user->email; ?>" required>
                            <small id="email-invalid-warning" class="invalid-warning form-text text-danger">Email address appears to be invalid.</small>

                            <?php if(!$user) { ?>
                                <div class="g-recaptcha" data-sitekey="6LetfoQUAAAAAFUizejAuy_PLWduJkEOJ2AHvcpe"></div>
                                <small id="recaptcha-warning" class="invalid-warning form-text text-danger">Please tick the recaptcha checkbox.</small>
                            <?php } ?>

                            <button class="btn btn-primary">Send</button>
                        </form>

                    </div>

                    <div class="col-md-5 contact-wrapper">
                        <div class="contact-box">
                            <img src="img/akash.jpg" alt="Akash Jain" class="img-thumbnail">
                            <p class="contact-name">Akash Jain</p>
                            <p class="contact-subtitle">University of Victoria</p>
                            <p><a href="https://ajainphysics.com" target="_blank">ajainphysics.com</a></p>
                        </div>
                    </div>
                </div>

            </section>


        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>