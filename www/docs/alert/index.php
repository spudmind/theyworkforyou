<?php
/*
 This is the main file allowing users to manage email alerts.
 It is based on the file /user/index.php.
 The alerts depend on the class ALERT which is established in
 /classes/MySociety/TheyWorkForYou/Alert.php

The submitted flag means we've submitted some form of search. Having pid or
keyword present means we've picked one of those results (or come straight from
e.g. MP page), and should try and add, asking for email if needed.

*/

include_once '../../includes/easyparliament/init.php';
include_once INCLUDESPATH . '../../commonlib/phplib/auth.php';
include_once INCLUDESPATH . '../../commonlib/phplib/crosssell.php';

$this_page = "alert";
$extra = null;

$ALERT = new \MySociety\TheyWorkForYou\Alert;
$token = get_http_var('t');
$alert = $ALERT->check_token($token);

$message = '';
if ($action = get_http_var('action')) {
    $success = true;
    if ($action == 'Confirm') {
        $success = $ALERT->confirm($token);
        if ($success) {
            $criteria = $ALERT->criteria_pretty(true);
            $message = "<p>Your alert has been confirmed. You will now
            receive email alerts for the following criteria:</p>
            <ul>$criteria</ul> <p>This is normally the day after, but could
            conceivably be later due to issues at our or parliament.uk's
            end.</p>";
        }
    } elseif ($action == 'Suspend') {
        $success = $ALERT->suspend($token);
        if ($success)
            $message = '<p><strong>That alert has been suspended.</strong> You will no longer receive this alert.</p>';
    } elseif ($action == 'Resume') {
        $success = $ALERT->resume($token);
        if ($success)
            $message = '<p><strong>That alert has been resumed.</strong> You
            will now receive email alerts on any day when there are entries in
            Hansard that match your criteria.</p>';
    } elseif ($action == 'Delete') {
        $success = $ALERT->delete($token);
        if ($success)
            $message = '<p><strong>That alert has been deleted.</strong> You will no longer receive this alert.</p>';
    }
    if (!$success)
        $message = "<p>The link you followed to reach this page appears to be
        incomplete.</p> <p>If you clicked a link in your alert email you may
        need to manually copy and paste the entire link to the 'Location' bar
        of the web browser and try again.</p> <p>If you still get this message,
        please do <a href='mailto:" . str_replace('@', '&#64;', CONTACTEMAIL) . "'>email us</a> and let us
        know, and we'll help out!</p>";
}

$details = array();
if ($THEUSER->loggedin()) {
    $details['email'] = $THEUSER->email();
    $details['email_verified'] = true;
} elseif ($alert) {
    $details['email'] = $alert['email'];
    $details['email_verified'] = true;
} else {
    $details["email"] = trim(get_http_var("email"));
    $details['email_verified'] = false;
}
$details['keyword'] = trim(get_http_var("keyword"));
$details['pid'] = trim(get_http_var("pid"));
$details['alertsearch'] = trim(get_http_var("alertsearch"));
$details['pc'] = get_http_var('pc');
$details['submitted'] = get_http_var('submitted') || $details['pid'] || $details['keyword'];

$errors = \MySociety\TheyWorkForYou\Utility\Alert::checkInput($details);

// Do the search
if ($details['alertsearch']) {
    $details['members'] = \MySociety\TheyWorkForYou\Utility\SearchEngine::searchMemberDbLookup($details['alertsearch'], true);
    list ($details['constituencies'], $details['valid_postcode']) = \MySociety\TheyWorkForYou\Utility\SearchEngine::searchConstituenciesByQuery($details['alertsearch']);
}

if (!sizeof($errors) && ($details['keyword'] || $details['pid'])) {
    $message = \MySociety\TheyWorkForYou\Utility\Alert::addAlert($details);
    $details['keyword'] = '';
    $details['pid'] = '';
    $details['alertsearch'] = '';
    $details['pc'] = '';
}

$PAGE->page_start();
$PAGE->stripe_start();
if ($message) {
    $PAGE->informational($message);
}

$sidebar = null;
if ($details['email_verified']) {
    ob_start();
    if ($THEUSER->postcode()) {
        $current_mp = new \MySociety\TheyWorkForYou\Member(array('postcode' => $THEUSER->postcode()));
        if (!$ALERT->fetch_by_mp($THEUSER->email(), $current_mp->person_id())) {
            $PAGE->block_start(array ('title'=>'Your current MP'));
?>
<form action="/alert/" method="post">
<input type="hidden" name="t" value="<?=_htmlspecialchars(get_http_var('t'))?>">
<input type="hidden" name="pid" value="<?=$current_mp->person_id()?>">
You are not subscribed to an alert for your current MP,
<?=$current_mp->full_name() ?>.
<input type="submit" value="Subscribe">
</form>
<?php
            $PAGE->block_end();
        }
    }
    $PAGE->block_start(array ('title'=>'Your current email alerts'));
    \MySociety\TheyWorkForYou\Utility\Alert::manage($details['email']);
    $PAGE->block_end();
    $sidebar = ob_get_clean();
}

$PAGE->block_start(array ('id'=>'alerts', 'title'=>'Request a TheyWorkForYou email alert'));
\MySociety\TheyWorkForYou\Utility\Alert::displaySearchForm($alert, $details, $errors);
$PAGE->block_end();

$end = array();
if ($sidebar) {
    $end[] = array('type' => 'html', 'content' => $sidebar);
}
$end[] = array('type' => 'include', 'content' => 'minisurvey');
$end[] = array('type' => 'include', 'content' => 'mysociety_news');
$end[] = array('type' => 'include', 'content' => 'search');
$PAGE->stripe_end($end);
$PAGE->page_end($extra);
