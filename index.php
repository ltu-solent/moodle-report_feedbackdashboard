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
require_once('lib.php');
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
echo "<button id='print-btn' onClick='window.print()'>" . get_string('print', 'report_feedbackdashboard') . "</button><br>";

$courses = enrol_get_my_courses('enddate', 'enddate DESC');
$validcourses = null;

if(isset($courses)){
	$studentcourses = array();	
	$tutorcourses = array();
	
	foreach ($courses as $course) {
		$category = core_course_category::get($course->category, IGNORE_MISSING);		
		$context = context_course::instance($course->id);
		
		if(has_capability('mod/assign:submit', $context) && strpos($category->idnumber, 'modules_') !== false){
			$studentcourses[$course->id] = $course;
			$validcourses = 1;
		}

		if(has_capability('mod/assign:grade', $context) && strpos($category->idnumber, 'modules_current') !== false){
			$tutorcourses[$course->id] = $course;
			$validcourses = 1;
		}		
	}

	echo get_student_dashboard($studentcourses, $tutorcourses);	
	echo get_tutor_dashboard($tutorcourses, $studentcourses);
}	

if($validcourses == null){
	echo get_string('nodashboard', 'report_feedbackdashboard');
}

echo $OUTPUT->footer();
