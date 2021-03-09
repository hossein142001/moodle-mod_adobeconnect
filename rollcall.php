<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_adobeconnect
 * @author     Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/connect_class.php');
require_once(dirname(__FILE__).'/connect_class_dom.php');

$id       = required_param('id', PARAM_INT); // course_module ID, or
$sesskey  = required_param('sesskey', PARAM_ALPHANUM);


global $CFG, $USER, $DB, $PAGE;

if (! $cm = get_coursemodule_from_id('adobeconnect', $id)) {
    print_error('Course Module ID was incorrect');
}

$cond = array('id' => $cm->course);
if (! $course = $DB->get_record('course', $cond)) {
    print_error('Course is misconfigured');
}

$cond = array('id' => $cm->instance);
if (! $adobeconnect = $DB->get_record('adobeconnect', $cond)) {
    print_error('Course module is incorrect');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
/// Set page global
$url = new moodle_url('/mod/adobeconnect/rollcall.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(format_string($adobeconnect->name));
$PAGE->set_heading($course->fullname);

$PAGE->set_pagetype('course-index-category');

echo $OUTPUT->header();
echo $OUTPUT->skip_link_target();

if (!has_capability('mod/adobeconnect:view_rollcall', $context) || !confirm_sesskey($sesskey)) {
	print_error('confirmsesskeybad','error');
}
echo html_writer::start_tag('div', ['class'=>'course_category_tree clearfix category-browse category-browse-0']);


// Only show the collapse/expand if there are children to expand.
echo html_writer::start_tag('div', array('class' => 'collapsible-actions'));
echo html_writer::link('#', get_string('expandall'), array('class' => 'collapseexpand aabtn' ));
echo html_writer::end_tag('div');
$PAGE->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
echo html_writer::start_tag('div', array('class' => 'subcategories'));
$aconnect = aconnect_login();

$scoid = $DB->get_field('adobeconnect_meeting_groups', 'meetingscoid', array('instanceid' => $adobeconnect->id));
$params = array('action' => 'report-meeting-sessions', 'sco-id' => $scoid);
$aconnect->create_request($params);
if ($aconnect->call_success()) {
	$dom = new DomDocument();
	$dom->loadXML($aconnect->_xmlresponse);
	$meeting_sessions = $dom->getElementsByTagName('row');
	foreach ($meeting_sessions as $meeting_session) {
		$create = $meeting_session->getElementsByTagName('date-created')->item(0)->nodeValue;
		$end = $meeting_session->getElementsByTagName('date-end')->item(0)->nodeValue;
		$num_participants = $meeting_session->getAttribute('num-participants');
		$asset_id = $meeting_session->getAttribute('asset-id');

		if ($end) {
			$duration = strtotime($end) - strtotime($create);
			
			echo html_writer::start_tag('div', ['class' => 'category with_children loaded collapsed']);
			echo html_writer::start_tag('div', ['class' => 'info']);
			
			//echo html_writer::start_tag('span', ['class' => 'categoryname aabtn']);
			$table = new html_table();
			$table->attributes['class'] = 'generaltable mod_index categoryname aabtn';
			$table->data[] = array(
					userdate(strtotime($create)),
					userdate(strtotime($end)),
					gmdate("H:i:s", $duration ),
					$num_participants
				);
			echo html_writer::table($table);
			//echo html_writer::end_tag('span');
			echo html_writer::end_tag('div');
			
			
			echo html_writer::start_tag('div', ['class' => 'content']);
			//get list of participants
			$params = array('action' => 'report-bulk-consolidated-transactions', 
							'filter-sco-id' => $scoid, 
							'filter-type' => 'meeting', 
							'filter-gte-date-created' => date(DateTime::ISO8601, strtotime($create) - 1), 
							'filter-lte-date-closed' => date(DateTime::ISO8601, strtotime($end) + 1)
							);
			$aconnect->create_request($params);
			if ($aconnect->call_success()) {
				$dom2 = new DomDocument();
				$dom2->loadXML($aconnect->_xmlresponse);
				$contents = $dom2->getElementsByTagName('row');
				$users = array();
				foreach ($contents as $content) {
					$start = $content->getElementsByTagName('date-created')->item(0)->nodeValue;
					$end = $content->getElementsByTagName('date-closed')->item(0)->nodeValue;
					if ($start && $end)
						$users[$content->getElementsByTagName('login')->item(0)->nodeValue][] = array('date_created' => $start,
							'date_closed' => $end);
				}
			}
			
			
			$table = new html_table();
			$table->attributes['class'] = 'generaltable mod_index';
			$table->head = array(
                    '#',
                    get_string('name'),
                    get_string('start', 'adobeconnect'),
                    get_string('end', 'adobeconnect')
                );
			$c = 0;
			foreach ($users as $username => $user) {
				$user_current = $DB->get_record("user", array('username' => $username));
				if ($user_current) {
					$c ++;
					foreach ($user as $u) {
						$table->data[] = array($c,
							fullname($user_current),
							userdate(strtotime($u['date_created'])),
							userdate(strtotime($u['date_closed']))
						);						
					}
				}
			}
			echo html_writer::table($table);
			
			echo html_writer::end_tag('div');
			echo html_writer::end_tag('div');
		}
	}
}
$PAGE->requires->should_create_one_time_item_now('core_course_categoryexpanderjsinit');
$PAGE->requires->yui_module('moodle-course-categoryexpander',
                'Y.Moodle.course.categoryexpander.init');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
	/// Finish the page
echo $OUTPUT->footer();


?>
