<?php

function get_course_category_names($course_category_ids) {
  global $DB;
  $category_ids = '(';
  foreach ($course_category_ids as $category_id) {
    $category_ids .= $category_id . ','; //concatenate the unit IDs into a string for the SQL query
  }
  $category_ids = substr($category_ids, 0, -1) . ')';

  $course_category_names = $DB->get_records_sql(
    "SELECT id, name
     FROM {course_categories}
     WHERE id IN " . $category_ids
     );

  return $course_category_names;
}

function get_unit_assignments($units, $user) {
  global $DB;
  $unit_ids = '(';
  foreach ($units as $unit) {
    $unit_ids .= $unit->id . ','; //concatenate the unit IDs into a string for the SQL query
  }
  $unit_ids = substr($unit_ids, 0, -1) . ')';
  $assignments = $DB->get_records_sql(
    "SELECT a.id, cm.id AS 'module', a.course, a.duedate, g.idnumber, g.iteminstance, g.hidden, cm.deletioninprogress
     FROM {assign} a
     INNER JOIN {grade_items} g ON a.id = g.iteminstance
		 INNER JOIN {course_modules} cm ON a.id = cm.instance
     WHERE a.course IN " . $unit_ids . "AND g.itemmodule = 'assign'  AND cm.module = 29"//get assignments from the unit IDs
     );
		 //to-do: check if course is unit page

  return $assignments;
}

function get_feedback($assignments, $user) {
  global $DB;

  $assignment_ids = '(';
  foreach ($assignments as $assignment) {
    $assignment_ids .= $assignment . ','; //concatenate the assignment IDs into a string for the SQL query
  }
  $assignment_ids = substr($assignment_ids, 0, -1) . ')';

  $turnitin_feedback = $DB->get_records_sql(
    "SELECT g.iteminstance AS 'id', p.gm_feedback AS 'feedback'
     FROM {plagiarism_turnitin_files} p
     INNER JOIN {course_modules} cm ON p.cm = cm.id
     INNER JOIN {grade_items} g ON cm.instance = g.iteminstance
     INNER JOIN {assign} a ON g.iteminstance = a.id
     WHERE p.userid =" . $user .  " AND cm.module = 29 AND g.iteminstance IN " . $assignment_ids . " AND g.itemmodule = 'assign'");

     return $turnitin_feedback;
}

function get_feedback_comments($assignments, $user) {
  global $DB;

  $assignment_ids = '(';
  foreach ($assignments as $assignment) {
    $assignment_ids .= $assignment . ','; //concatenate the assignment IDs into a string for the SQL query
  }
  $assignment_ids = substr($assignment_ids, 0, -1) . ')';

  $comment = $DB->get_records_sql(
    "SELECT c.assignment AS 'id', c.commenttext
     FROM {assignfeedback_comments} c
     INNER JOIN {assign_grades} g ON c.grade = g.id
     WHERE g.userid = " . $user . " AND c.assignment IN " . $assignment_ids);

     return $comment;
}

function get_feedback_files($assignments, $user) {
  global $DB;

  $assignment_ids = '(';
  foreach ($assignments as $assignment) {
    $assignment_ids .= $assignment . ','; //concatenate the assignment IDs into a string for the SQL query
  }
  $assignment_ids = substr($assignment_ids, 0, -1) . ')';

  $files = $DB->get_records_sql(
    "SELECT f.assignment AS 'id', f.numfiles
     FROM {assignfeedback_file} f
     INNER JOIN {assign_grades} g ON f.grade = g.id
     WHERE g.userid = " . $user . " AND f.assignment IN " . $assignment_ids);

     return $files;
}

function get_submission_status($assignments, $user) {
  global $DB;

  $assignment_ids = '(';
  foreach ($assignments as $assignment) {
    $assignment_ids .= $assignment . ','; //concatenate the assignment IDs into a string for the SQL query
  }
  $assignment_ids = substr($assignment_ids, 0, -1) . ')';

  $submission = $DB->get_records_sql(
    "SELECT assignment, timemodified, status
     FROM {assign_submission} s
     WHERE userid = " . $user . " AND assignment IN " . $assignment_ids);

     return $submission;
}

function create_table($assignments, $grading_info, $turnitin_feedback, $feedback_comments, $feedback_files, $submission) {
	global $USER;

	$strassignment = get_string('assignmentname', 'report_feedbackoverview');
	$strfeedback = get_string('feedback', 'report_feedbackoverview');
	$strgrade = get_string('grade', 'report_feedbackoverview');
	$strgradeddate = get_string('gradeddate', 'report_feedbackoverview');
	$strdatesubmitted = get_string('datesubmitted', 'report_feedbackoverview');
	$strduedate = get_string('duedate', 'report_feedbackoverview');

	$table = new html_table();
	$table->attributes['class'] = 'generaltable boxaligncenter';
	$table->id = 'feedbackoverview';
	$table->cellpadding = 5;
	$table->head = array($strassignment, $strdatesubmitted, $strduedate, $strgradeddate, $strfeedback, $strgrade);

	foreach ($grading_info as $grades) {
		if ($grades->items[0]->hidden == false) { //if the assignment is not hidden
			$row = new html_table_row();

			$cell1 = new html_table_cell(html_writer::tag('a', $grades->items[0]->name, ['href'=>'/mod/assign/view.php?id=' . $assignments[$grades->items[0]->iteminstance]->module]));

			if ($submission[$grades->items[0]->iteminstance]->status == "submitted") { //if the student has not submitted anything yet
				$cell2 = new html_table_cell(date('d-m-Y, g:i:s A', ($submission[$grades->items[0]->iteminstance]->timemodified))); //else, show the submission date
			} else {
        $cell2 = new html_table_cell(get_string('nosubmitteddate', 'report_feedbackoverview')); //cell should say 'Not submitted'
			}
      if ($assignments[$grades->items[0]->iteminstance]->duedate !== "0") {
			$cell3 = new html_table_cell(date('d-m-Y, g:i A', ($assignments[$grades->items[0]->iteminstance]->duedate)));
      } else {
      $cell3 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview'));
      }

      if ($grades->items[0]->locked == true) {

        if ($grades->items[0]->grades[$USER->id]->dategraded == null) { //if the assignment has not been graded
          $cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview')); //cell should be empty
        } else {
          $cell4 = new html_table_cell(date('d-m-Y, g:i A', ($grades->items[0]->grades[$USER->id]->dategraded))); //else, show the grading date
        }

          $cell5 = new html_table_cell("");

          if ($turnitin_feedback[$grades->items[0]->iteminstance]->feedback == "1") {
              $cell5->text .= 'Feedback available in Turnitin<br>';
          }
         if ($feedback_files[$grades->items[0]->iteminstance]->numfiles !== null && $feedback_files[$grades->items[0]->iteminstance]->numfiles !== "0") {
              $cell5->text .= 'Feedback file(s) available<br>';
          }
         if ($feedback_comments[$grades->items[0]->iteminstance]->commenttext !== '') {
    				$cell5->text .= 'Feedback available on the assignment page<br>'; //else, show the feedback
          }

          if($cell5->text == "" || $cell5->text == null) {
                $cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview'));
           }

				$cell6 = new html_table_cell($grades->items[0]->grades[$USER->id]->str_grade); //show the grade
      } else {
          $cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview'));
          $cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview')); //if the assignment is not locked, don't show feedback or grades
          $cell6 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview'));
        }
        $row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

        $table->data[] = $row;
      }




		}
    return $table;
	}
