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
 * @subpackage feedbackoverview
 * @copyright  2019 onwards Solent University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $PAGE, $USER;

require('../../config.php');
require('lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/report/feedbackoverview/index.php');
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_feedbackoverview'));
$PAGE->set_heading($USER->firstname . ' ' . $USER->lastname . ' - ' . get_string('pluginname', 'report_feedbackoverview'));

echo $OUTPUT->header();
echo "<h5>All grades available in Solent Online Learning are provisional and subject to change.
      To view confirmed, final grades please visit the <a href='https://portal.solent.ac.uk/portal-apps/results/results.aspx'>Results app on the Portal.</a></h5>
      <br>";
$user = $USER->id;
$units = enrol_get_all_users_courses($user);

foreach ($units as $unit) {
  $course_category_ids[] = $unit->category;
}

$course_category_names = get_course_category_names($course_category_ids);

$assignments = get_unit_assignments($units, $user);

foreach ($assignments as $assignment) {
  $assignment_ids[] = $assignment->id;
}

$turnitin_feedback = get_feedback($assignment_ids, $user);
$feedback_comments = get_feedback_comments($assignment_ids, $user);
$feedback_files = get_feedback_files($assignment_ids, $user);

foreach ($units as $unit) { //for each the user's units
  if (strpos(strtolower($course_category_names[$unit->category]->name), 'unit pages') !== false) { //check if the course is a unit page

    $assignment_count = 0; //keep track of the number of assignments;
    $grading_info = [];
    foreach ($assignments as $assignment) { //go through every assignment in the unit
      if ($assignment->course == $unit->id && $assignment->hidden == "0" && $assignment->idnumber != null && $assignment->deletioninprogress == 0) { /*if the assignment
        belongs to the unit, is not hidden, has an idnumber and is not being deleted*/

          $grading_info[] = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->iteminstance, $USER->id); //get the grade information for the user
          $assignment_count++; //add one to the assignment count

      }

    }

    echo html_writer::start_tag('div', ['class'=>'feedbackoverview_unit']);
    echo html_writer::tag('h3', $unit->fullname);

    if ($assignment_count != 0) { //if the unit has assignments...
        $table = create_table($assignments, $grading_info, $turnitin_feedback, $feedback_comments, $feedback_files); //generate a table containing the assignment information and grades
        echo html_writer::table($table); //show the table
    } else {
      echo html_writer::tag('p', get_string('noassignments', 'report_feedbackoverview'));
    }
      echo html_writer::end_tag('div', ['class'=>'feedbackoverview_unit']);
  }
}

echo $OUTPUT->footer();
