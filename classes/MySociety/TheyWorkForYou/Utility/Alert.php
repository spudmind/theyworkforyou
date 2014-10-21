<?php

namespace MySociety\TheyWorkForYou\Utility;

/**
 * Alert Utilities
 *
 * Utility functions related to alerts
 */

class Alert
{

    public static function confirmationAdvert($details) {
        global $THEUSER;

        $adverts = array(
            #array('hfymp0', '<h2 style="border-top: dotted 1px #999999; padding-top:0.5em; margin-bottom:0">Get email from your MP in the future</h2> <p style="font-size:120%;margin-top:0;">and have a chance to discuss what they say in a public forum [button]Sign up to HearFromYourMP[/button]'),
            array('hfymp1', '<h2 style="border-top: dotted 1px #999999; padding-top:0.5em; margin-bottom:0">Get email from your MP in the future</h2> <p style="font-size:120%;margin-top:0;">and have a chance to discuss what they say in a public forum [form]Sign up to HearFromYourMP[/form]'),
            #array('fms0', '<p>Got a local problem like potholes or flytipping in your street?<br><a href="http://www.fixmystreet.com/">Report it at FixMyStreet</a></p>'),
            #array('gny0', '<h2>Are you a member of a local group&hellip;</h2> <p>&hellip;which uses the internet to coordinate itself, such as a neighbourhood watch? If so, please help the charity that runs TheyWorkForYou by <a href="http://www.groupsnearyou.com/add/about/">adding some information about it</a> to our new site, GroupsNearYou.</p>'),
            #array('twfy_alerts0', ''),
        );

        if ($THEUSER->isloggedin()) {
            $advert_shown = crosssell_display_advert('twfy', $details['email'], $THEUSER->firstname() . ' ' . $THEUSER->lastname(), $THEUSER->postcode(), $adverts);
        } else {
            $advert_shown = crosssell_display_advert('twfy', $details['email'], '', '', $adverts);
        }
        if ($advert_shown == 'other-twfy-alert-type') {
            if ($details['pid']) {
                $advert_shown = 'twfy-alert-word';
    ?>
<p>Did you know that TheyWorkForYou can also email you when a certain word or phrases is mentioned in parliament? For example, it could mail you when your town is mentioned, or an issue you care about. Don't rely on the newspapers to keep you informed about your interests - find out what's happening straight from the horse's mouth.
<a href="/alert/"><strong>Sign up for an email alert</strong></a></p>
    <?php       } else {
                $advert_shown = 'twfy-alert-person';
    ?>
<p>Did you know that TheyWorkForYou can also email you when a certain MP or Lord contributes in parliament? Don't rely on the newspapers to keep you informed about someone you're interested in - find out what's happening straight from the horse's mouth.
<a href="/alert/"><strong>Sign up for an email alert</strong></a></p>
    <?php       }
        }
        return $advert_shown;
    }


    public static function detailsToCriteria($details) {
        $criteria = array();

        if (!empty($details['keyword'])) {
            $criteria[] = $details['keyword'];
        }

        if (!empty($details['pid'])) {
            $criteria[] = 'speaker:'.$details['pid'];
        }

        $criteria = join(' ', $criteria);
        return $criteria;
    }

    public static function manage($email) {
        $db = new \MySociety\TheyWorkForYou\ParlDb;
        $q = $db->query('SELECT * FROM alerts WHERE email = :email
            AND deleted != 1 ORDER BY created', array(
                ':email' => $email
            ));
        $out = '';
        for ($i=0; $i<$q->rows(); ++$i) {
            $row = $q->row($i);
            $criteria = explode(' ',$row['criteria']);
            $ccc = array();
            $current = true;
            foreach ($criteria as $c) {
                if (preg_match('#^speaker:(\d+)#',$c,$m)) {
                    $MEMBER = new \MySociety\TheyWorkForYou\Member(array('person_id'=>$m[1]));
                    $ccc[] = 'spoken by ' . $MEMBER->full_name();
                    if (!$MEMBER->current_member_anywhere()) {
                        $current = false;
                    }
                } else {
                    $ccc[] = $c;
                }
            }
            $criteria = join(' ',$ccc);
            $token = $row['alert_id'] . '-' . $row['registrationtoken'];
            $action = '<form action="/alert/" method="post"><input type="hidden" name="t" value="'.$token.'">';
            if (!$row['confirmed']) {
                $action .= '<input type="submit" name="action" value="Confirm">';
            } elseif ($row['deleted']==2) {
                $action .= '<input type="submit" name="action" value="Resume">';
            } else {
                $action .= '<input type="submit" name="action" value="Suspend"> <input type="submit" name="action" value="Delete">';
            }
            $action .= '</form>';
            $out .= '<tr><td>' . $criteria . '</td><td align="center">' . $action . '</td></tr>';
            if (!$current) {
                $out .= '<tr><td colspan="2"><small>&nbsp;&mdash; <em>not a current member of any body covered by TheyWorkForYou</em></small></td></tr>';
            }
        }
        if ($out) {
            print '<table cellpadding="3" cellspacing="0"><tr><th>Criteria</th><th>Action</th></tr>' . $out . '</table>';
        } else {
            print '<p>You currently have no email alerts set up.</p>';
        }
    }

    /**
     * Validates the edited or added alert data and creates error messages.
     */

    function checkInput($details) {
        global $SEARCHENGINE;

        $errors = array();

        // Check each of the things the user has input.
        // If there is a problem with any of them, set an entry in the $errors array.
        // This will then be used to (a) indicate there were errors and (b) display
        // error messages when we show the form again.

        // Check email address is valid and unique.
        if (!$details['email']) {
            $errors["email"] = "Please enter your email address";
        } elseif (!validate_email($details["email"])) {
            // validate_email() is in includes/utilities.php
            $errors["email"] = "Please enter a valid email address";
        }

        if ($details['pid'] && !ctype_digit($details['pid'])) {
            $errors['pid'] = 'Invalid person ID passed';
        }

        $text = $details['alertsearch'];
        if (!$text) $text = $details['keyword'];

        if ($details['submitted'] && !$details['pid'] && !$text) {
            $errors['alertsearch'] = 'Please enter what you want to be alerted about';
        }

        if (strpos($text, '..')) {
            $errors['alertsearch'] = 'You probably don&rsquo;t want a date range as part of your criteria, as you won&rsquo;t be alerted to anything new!';
        }

        $se = new \MySociety\TheyWorkForYou\SearchEngine($text);
        if (!$se->valid) {
            $errors['alertsearch'] = 'That search appears to be invalid - ' . $se->error . ' - please check and try again.';
        }

        if (strlen($text) > 255) {
            $errors['alertsearch'] = 'That search is too long for our database; please split it up into multiple smaller alerts.';
        }

        return $errors;
    }

    /**
     * Adds alert to database depending on success.
     */

    function addAlert($details) {
        global $THEUSER, $ALERT, $extra;

        $external_auth = auth_verify_with_shared_secret($details['email'], OPTION_AUTH_SHARED_SECRET, get_http_var('sign'));
        if ($external_auth) {
            $site = get_http_var('site');
            $extra = 'from_' . $site . '=1';
            $confirm = false;
        } elseif ($details['email_verified']) {
            $confirm = false;
        } else {
            $confirm = true;
        }

        // If this goes well, the alert will be added to the database and a confirmation email
        // will be sent to them.
        $success = $ALERT->add ( $details, $confirm );

        $advert = false;
        if ($success>0 && !$confirm) {
            if ($details['pid']) {
                $MEMBER = new \MySociety\TheyWorkForYou\Member(array('person_id'=>$details['pid']));
                $criteria = $MEMBER->full_name();
                if ($details['keyword']) {
                    $criteria .= ' mentions \'' . $details['keyword'] . '\'';
                } else {
                    $criteria .= ' contributes';
                }
            } elseif ($details['keyword']) {
                $criteria = '\'' . $details['keyword'] . '\' is mentioned';
            }
            $message = array(
                'title' => 'Your alert has been added',
                'text' => 'You will now receive email alerts on any day when ' . $criteria . ' in parliament.'
            );
            $advert = true;
        } elseif ($success>0) {
            $message = array(
                'title' => "We're nearly done...",
                'text' => "You should receive an email shortly which will contain a link. You will need to follow that link to confirm your email address to receive the alert. Thanks."
            );
        } elseif ($success == -2) {
            // we need to make sure we know that the person attempting to sign up
            // for the alert has that email address to stop people trying to work
            // out what alerts they are signed up to
            if ( $details['email_verified'] || ( $THEUSER->loggedin && $THEUSER->email() == $details['email'] ) ) {
                $message = array('title' => 'You already have this alert',
                'text' => 'You already appear to be subscribed to this email alert, so we have not signed you up to it again.'
                );
            } else {
                // don't throw an error message as that implies that they have already signed
                // up for the alert but instead pretend all is normal but send an email saying
                // that someone tried to sign them up for an existing alert
                $ALERT->send_already_signedup_email($details);
                $message = array('title' => "We're nearly done...",
                    'text' => "You should receive an email shortly which will contain a link. You will need to follow that link to confirm your email address to receive the alert. Thanks."
                );
            }
            $advert = true;
        } else {
            $message = array ('title' => "This alert has not been accepted",
            'text' => "Sorry, we were unable to create this alert. Please <a href=\"mailto:" . str_replace('@', '&#64;', CONTACTEMAIL) . "\">let us know</a>. Thanks."
            );
        }

        return $message['text'];
    }

    /**
     * Shows the new form to enter alert data.
     *
     * This function creates the form for displaying an alert, prompts the user
     * for input and creates the alert when submitted.
     */

    function displaySearchForm ( $alert, $details = array(), $errors = array() ) {
        global $this_page, $PAGE;

        $ACTIONURL = new \MySociety\TheyWorkForYou\Url($this_page);
        $ACTIONURL->reset();
        $form_start = '<form action="' . $ACTIONURL->generate() . '" method="post">
<input type="hidden" name="t" value="' . _htmlspecialchars(get_http_var('t')) . '">
<input type="hidden" name="email" value="' . _htmlspecialchars(get_http_var('email')) . '">';

        if (isset($details['members']) && $details['members']->rows() > 0) {
            echo '<ul class="hilites">';
            $q = $details['members'];
            $last_pid = null;
            for ($n=0; $n<$q->rows(); $n++) {
                if ($q->field($n, 'person_id') != $last_pid) {
                    $last_pid = $q->field($n, 'person_id');
                    echo '<li>';
                    echo $form_start . '<input type="hidden" name="pid" value="' . $last_pid . '">';
                    echo 'Things by ';
                    $name = member_full_name($q->field($n, 'house'), $q->field($n, 'title'), $q->field($n, 'first_name'), $q->field($n, 'last_name'), $q->field($n, 'constituency') );
                    if ($q->field($n, 'house') != 2) {
                        echo $name . ' (' . $q->field($n, 'constituency') . ') ';
                    } else {
                        echo $name;
                    }
                    echo ' <input type="submit" value="Subscribe"></form>';
                    echo "</li>\n";
                }
            }
            echo '</ul>';
        }

        if (isset($details['constituencies'])) {
            echo '<ul class="hilites">';
            foreach ($details['constituencies'] as $constituency) {
                $MEMBER = new \MySociety\TheyWorkForYou\Member(array('constituency'=>$constituency, 'house' => 1));
                echo "<li>";
                echo $form_start . '<input type="hidden" name="pid" value="' . $MEMBER->person_id() . '">';
                if ($details['valid_postcode'])
                    echo '<input type="hidden" name="pc" value="' . _htmlspecialchars($details['alertsearch']) . '">';
                echo $MEMBER->full_name();
                echo ' (' . _htmlspecialchars($constituency) . ')';
                echo ' <input type="submit" value="Subscribe"></form>';
                echo "</li>";
            }
            echo '</ul>';
        }

        if ($details['alertsearch']) {
            echo '<ul class="hilites"><li>';
            echo $form_start . '<input type="hidden" name="keyword" value="' . _htmlspecialchars($details['alertsearch']) . '">';
            echo 'Mentions of [';
            $alertsearch = $details['alertsearch'];
            if (preg_match('#speaker:(\d+)#', $alertsearch, $m)) {
                $MEMBER = new \MySociety\TheyWorkForYou\Member(array('person_id'=>$m[1]));
                $alertsearch = str_replace("speaker:$m[1]", "speaker:" . $MEMBER->full_name(), $alertsearch);
            }
            echo _htmlspecialchars($alertsearch) . '] ';
            echo ' <input type="submit" value="Subscribe"></form>';
            if (strstr($alertsearch, ',') > -1) {
                echo '<em class="error">You have used a comma in your search term &ndash; are you sure this is what you want?
You cannot sign up to multiple search terms using a comma &ndash; either use OR, or fill in this form multiple times.</em>';
            }
            echo "</li></ul>";
        }

        if ($details['pid']) {
            $MEMBER = new \MySociety\TheyWorkForYou\Member(array('person_id'=>$details['pid']));
            echo '<ul class="hilites"><li>';
            echo "Signing up for things by " . $MEMBER->full_name();
            echo ' (' . _htmlspecialchars($MEMBER->constituency()) . ')';
            echo "</li></ul>";
        }

        if ($details['keyword']) {
            echo '<ul class="hilites"><li>';
            echo 'Signing up for results from a search for [';
            $alertsearch = $details['keyword'];
            if (preg_match('#speaker:(\d+)#', $alertsearch, $m)) {
                $MEMBER = new \MySociety\TheyWorkForYou\Member(array('person_id'=>$m[1]));
                $alertsearch = str_replace("speaker:$m[1]", "speaker:" . $MEMBER->full_name(), $alertsearch);
            }
            echo _htmlspecialchars($alertsearch) . ']';
            echo "</li></ul>";
        }

        if (!$details['pid'] && !$details['keyword']) {
    ?>

<p><label for="alertsearch">To sign up to an email alert, enter either your
<strong>postcode</strong>, the <strong>name</strong> of who you're interested
in, or the <strong>search term</strong> you wish to receive alerts
for.</label> To be alerted on an exact <strong>phrase</strong>, be sure to put it in quotes.
Also use quotes around a word to avoid stemming (where &lsquo;horse&rsquo; would
also match &lsquo;horses&rsquo;).

    <?php
        }

        echo '<form action="' . $ACTIONURL->generate() . '" method="post">
    <input type="hidden" name="t" value="' . _htmlspecialchars(get_http_var('t')) . '">
    <input type="hidden" name="submitted" value="1">';

        if ((!$details['pid'] && !$details['keyword']) || isset($errors['alertsearch'])) {
            if (isset($errors["alertsearch"])) {
                $PAGE->error_message($errors["alertsearch"]);
            }
            $text = $details['alertsearch'];
            if (!$text) $text = $details['keyword'];
    ?>

<div class="row">
<input type="text" name="alertsearch" id="alertsearch" value="<?php if ($text) { echo _htmlentities($text); } ?>" maxlength="255" size="30" style="font-size:150%">
</div>

    <?php
        }

        if ($details['pid'])
            echo '<input type="hidden" name="pid" value="' . _htmlspecialchars($details['pid']) . '">';
        if ($details['keyword'])
            echo '<input type="hidden" name="keyword" value="' . _htmlspecialchars($details['keyword']) . '">';

        if (!$details['email_verified']) {
            if (isset($errors["email"]) && $details['submitted']) {
                $PAGE->error_message($errors["email"]);
            }
    ?>
            <div class="row">
                <label for="email">Your email address:</label>
                <input type="text" name="email" id="email" value="<?php if (isset($details["email"])) { echo _htmlentities($details["email"]); } ?>" maxlength="255" size="30" class="form">
            </div>
    <?php
        }
    ?>

        <div class="row">
            <input type="submit" class="submit" value="<?=
                ($details['pid'] || $details['keyword']) ? 'Subscribe' : 'Search'
            ?>">
        </div>

        <div class="row">
    <?php
        if (!$details['email_verified']) {
    ?>
            <p>If you <a href="/user/?pg=join">join</a> or <a href="/user/login/?ret=%2Falert%2F">sign in</a>, you won't need to confirm your email
            address for every alert you set.<br><br>
    <?php
        }
        if (!$details['pid'] && !$details['keyword']) {
    ?>
            <p>Please note that you should only enter <strong>one term per alert</strong> &ndash; if
            you wish to receive alerts on more than one thing, or for more than
            one person, simply fill in this form as many times as you need, or use boolean OR.<br><br></p>
            <p>For example, if you wish to receive alerts whenever the words
            <i>horse</i> or <i>pony</i> are mentioned in Parliament, please fill in
            this form once with the word <i>horse</i> and then again with the word
            <i>pony</i> (or you can put <i>horse OR pony</i> with the OR in capitals
            as explained on the right). Do not put <i>horse, pony</i> as that will only
            sign you up for alerts where <strong>both</strong> horse and pony are mentioned.</p>
    <?php
        }
    ?>
        </div>
    <?php
        if (get_http_var('sign'))
            echo '<input type="hidden" name="sign" value="' . _htmlspecialchars(get_http_var('sign')) . '">';
        if (get_http_var('site'))
            echo '<input type="hidden" name="site" value="' . _htmlspecialchars(get_http_var('site')) . '">';
        echo '</form>';
    }

}
