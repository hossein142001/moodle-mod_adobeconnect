<?php
/**
 * @package mod
 * @subpackage adobeconnect
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
    //defined('MOODLE_INTERNAL') || die;

    require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
    require_once(dirname(__FILE__) . '/locallib.php');
    require_once(dirname(dirname(dirname(__FILE__))) . '/lib/accesslib.php');

    require_login(SITEID, false);

    global $USER, $CFG, $DB, $OUTPUT;

    $url = new moodle_url('/mod/adobeconnect/conntest.php');
    $PAGE->set_url($url);

    $admins = explode(',', $CFG->siteadmins);

    if (false === array_search($USER->id, $admins)) {
        print_error('error1', 'adobeconnect', $CFG->wwwroot);
    }

    $ac = new stdClass();

    $param = array('name' => 'adobeconnect_admin_login');
    $ac->login      = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'adobeconnect_host');
    $ac->host       = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'adobeconnect_port');
    $ac->port       = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'adobeconnect_admin_password');
    $ac->pass       = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'adobeconnect_admin_httpauth');
    $ac->httpauth   = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'adobeconnect_email_login');
    $ac->emaillogin = $DB->get_field('config', 'value', $param);

    foreach ($ac as $propertyname => $propertyvalue) {

        if (0 != strcmp($propertyname, 'emaillogin') and
            empty($propertyvalue)) {
//no-reply@remote-learner.net
            //$url = $CFG->wwwroot . '/admin/settings.php?section=modsettingadobeconnect';
            print_error('error2', 'adobeconnect', '', $propertyname);
            die();
        }
    }

    $strtitle = get_string('connectiontesttitle', 'adobeconnect');

    $systemcontext = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_context($systemcontext);
    $PAGE->set_title($strtitle);
    //$PAGE->set_heading($strtitle);

    echo $OUTPUT->header();
    echo $OUTPUT->box_start('center');

    $param = new stdClass();
    $param->url = 'http://docs.moodle.org/en/Remote_learner_adobe_connect_pro';
    print_string('conntestintro', 'adobeconnect', $param);

    adobe_connection_test($ac->host, $ac->port, $ac->login, $ac->pass, $ac->httpauth, $ac->emaillogin);

    echo '<center>'. "\n";
    echo '<input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" />';
    echo '</center>';

    echo $OUTPUT->box_end();

    //echo $OUTPUT->footer();
