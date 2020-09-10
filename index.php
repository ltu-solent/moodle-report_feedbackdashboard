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
global $PAGE, $USER,$COURSE;

require('../../config.php');
require('lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/report/feedbackdashboard/index.php');
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_feedbackdashboard'));

// Trigger an grade report viewed event.
$event = \report_feedbackdashboard\event\feedbackdashboard_report_viewed::create(array(
            'context' => context_user::instance($USER->id),
            'relateduserid' => $USER->id,
            'other' => array(
                  'userid' => $USER->id
              )
          ));
$event->trigger();

if (isloggedin() && $USER->id != 1) {
$PAGE->set_heading($USER->firstname . ' ' . $USER->lastname . ' - ' . get_string('pluginname', 'report_feedbackdashboard'));
} else {
  $PAGE->set_heading(get_string('pluginname', 'report_feedbackdashboard'));
}

echo $OUTPUT->header();

if (!isloggedin() || $USER->id == 1) {
  echo get_string('loggedout', 'report_feedbackdashboard');
  echo $OUTPUT->footer();
  die();
}

echo get_string('instructions', 'report_feedbackdashboard');
echo '<br>';
echo get_string('disclaimer', 'report_feedbackdashboard');
echo "<button id= 'print_btn' onClick='window.print()'>" . get_string('print', 'report_feedbackdashboard') . "</button><br>";
$user = $USER->id;
$courses = enrol_get_all_users_courses($user, 1, 'enddate', 'enddate DESC');

foreach ($courses as $course) {
  $context = context_course::instance($course->id);
  if(has_capability('mod/assign:submit', $context)){
    $course_category_ids[] = $course->category;
  }
}

$course_category_names = get_course_category_names($course_category_ids);

$assignments = get_unit_assignments($courses, $user);

foreach ($assignments as $assignment) {
  $assignment_ids[] = $assignment->id;
}

$turnitin_feedback = get_turnitin_feedback($assignment_ids, $user);
$feedback_comments = get_feedback_comments($assignment_ids, $user);
$feedback_files = get_feedback_files($assignment_ids, $user);
$submission = get_submission_status($assignment_ids, $user);

foreach ($courses as $course) { //for each the user's units
  if (strpos(strtolower($course_category_names[$course->category]->name), 'module pages') !== false) { //check if the course is a unit page

    $assignment_count = 0; //keep track of the number of assignments;
    $grading_info = [];
    foreach ($assignments as $assignment) { //go through every assignment in the unit
      if ($assignment->course == $course->id && $assignment->hidden == "0" && $assignment->idnumber != null && $assignment->deletioninprogress == 0) { /*if the assignment
        belongs to the unit, is not hidden, has an idnumber and is not being deleted*/

          $grading_info[] = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->iteminstance, $USER->id); //get the grade information for the user
          $assignment_count++; //add one to the assignment count

      }

    }

    echo html_writer::start_tag('div', ['class'=>'feedbackdashboard_unit']);
    echo html_writer::tag('h3', $course->fullname);
	  echo html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

    if ($assignment_count != 0) { //if the unit has assignments...
        $table = create_table($assignments, $grading_info, $turnitin_feedback, $feedback_comments, $feedback_files, $submission); //generate a table containing the assignment information and grades
        echo html_writer::table($table); //show the table
    } else {
      echo html_writer::tag('p', get_string('noassignments', 'report_feedbackdashboard'));
    }
      echo html_writer::end_tag('div', ['class'=>'feedbackdashboard_unit']);
  }
}

echo $OUTPUT->footer();
