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
 * Display a user grade report for all courses
 *
 * @package    report
 * @subpackage feedbackdashboard
 * @copyright  2019 onwards Solent University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $PAGE, $USER, $COURSE;

require('../../config.php');
require('lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

require_login(true);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/report/feedbackdashboard/index.php');
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_feedbackdashboard'));
$PAGE->set_heading($USER->firstname . ' ' . $USER->lastname . ' - ' . get_string('pluginname', 'report_feedbackdashboard'));

// Trigger an grade report viewed event.
$event = \report_feedbackdashboard\event\feedbackdashboard_report_viewed::create(array(
            'context' => context_user::instance($USER->id),
            'relateduserid' => $USER->id,
            'other' => array(
                  'userid' => $USER->id
              )
          ));
$event->trigger();

echo $OUTPUT->header();
echo get_string('instructions', 'report_feedbackdashboard'). '<br>';
echo get_string('disclaimer', 'report_feedbackdashboard');
echo "<button id= 'print_btn' onClick='window.print()'>" . get_string('print', 'report_feedbackdashboard') . "</button><br>";
$courses = enrol_get_all_users_courses($USER->id, 1, 'enddate', 'enddate DESC');

if(isset($courses)){
	$assignments = get_assignments($courses);
	$assignmentids = null;
	foreach ($assignments as $assignment) {
		if(isset($assignment->id)){
			$assignmentids .= $assignment->id . ',';
		}
	}
	$assignmentids = rtrim($assignmentids, ",");
	
	$turnitinfeedback = null; //get_turnitin_feedback($assignmentids);
	$feedbackcomments = get_feedback_comments($assignmentids);
	$feedbackfiles = get_feedback_files($assignmentids);
	$submission = get_submission_status($assignmentids);

	foreach ($courses as $course) { //for each the user's units
		echo html_writer::start_tag('div', ['class'=>'feedbackdashboard_unit']);
		echo html_writer::tag('h3', $course->fullname);
		echo html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

		$table = create_table($course, $assignments, $turnitinfeedback, $feedbackcomments, $feedbackfiles, $submission); //generate a table containing the assignment information and grades
		
		echo html_writer::table($table); //show the table
		echo html_writer::end_tag('div', ['class'=>'feedbackdashboard_unit']);
	}
}
echo $OUTPUT->footer();
