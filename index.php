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
$PAGE->set_title('Feedback Overview');

echo $OUTPUT->header();

$units = enrol_get_all_users_courses($USER->id);

$assignments = get_unit_assignments($units);

foreach ($units as $unit) { //for each the user's units
  $assignment_count = 0; //keep track of the number of assignments;
  $grading_info = [];
  foreach ($assignments as $assignment) { //go through every assignment in the unit

    if ($assignment->course == $unit->id && $assignment->idnumber != null) { //if the assignment has an idnumber
        $grading_info[] = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->iteminstance, $USER->id); //get the grade information for the user
        $assignment_count++; //add one to the assignment count

    }

  }

  echo html_writer::start_tag('div', ['class'=>'feedbackoverview_unit']);
  echo html_writer::tag('h3', $unit->fullname);

  if ($assignment_count != 0) { //if the unit has assignments...
      $table = create_table($unit, $assignments, $grading_info); //generate a table containing the assignment information and grades
      echo html_writer::table($table); //show the table
  } else {
    echo html_writer::tag('p', get_string('noassignments', 'report_feedbackoverview'));
  }
    echo html_writer::end_tag('div', ['class'=>'feedbackoverview_unit']);

}

echo $OUTPUT->footer();
