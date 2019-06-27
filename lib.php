<?php

function get_unit_assignments($units) {
  global $DB;
  $unit_ids = '(';
  foreach ($units as $unit) {
    $unit_ids .= $unit->id . ','; //concatenate the unit IDs into a string for the SQL query
  }
  $unit_ids = substr($unit_ids, 0, -1) . ')';
  $assignments = $DB->get_records_sql(
    "SELECT a.id, cm.id AS 'module', a.course, a.duedate, g.idnumber, g.iteminstance
     FROM {assign} a
     INNER JOIN {grade_items} g ON a.id = g.iteminstance
		 INNER JOIN {course_modules} cm ON a.id = cm.instance
     WHERE a.course IN " . $unit_ids . "AND g.itemmodule = 'assign'  AND cm.module = 1"//get assignments from the unit IDs
     );
		 //to-do: check if course is unit page

  return $assignments;
}

function create_table($units, $assignments, $grading_info) {
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

			if ($grades->items[0]->grades[$USER->id]->datesubmitted == null) { //if the student has not submitted anything yet
				$cell2 = new html_table_cell(get_string('nosubmitteddate', 'report_feedbackoverview')); //cell should say 'Not submitted'
			} else {
				$cell2 = new html_table_cell(date('d-m-Y, g:i A', $grades->items[0]->grades[$USER->id]->datesubmitted)); //else, show the submission date
			}

			$cell3 = new html_table_cell(date('d-m-Y, g:i A', ($assignments[$grades->items[0]->iteminstance]->duedate)));

			if ($grades->items[0]->grades[$USER->id]->dategraded == null) { //if the assignment has not been graded
				$cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview')); //cell should be empty
			} else {
				$cell4 = new html_table_cell(date('d-m-Y, g:i A', ($grades->items[0]->grades[$USER->id]->dategraded))); //else, show the grading date
			}

			if ($grades->items[0]->locked == true) { //if the assignment has been locked
				if ($grades->items[0]->grades[$USER->id]->str_feedback == null) { //if there is no feedback
					$cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview')); //this cell should be empty
				} else {
					$cell5 = new html_table_cell($grades->items[0]->grades[$USER->id]->str_feedback); //else, show the feedback
				}
				$cell6 = new html_table_cell($grades->items[0]->grades[$USER->id]->str_grade); //show the grade
				} else {
					$cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview')); //if the assignment is not locked, don't show feedback or grades
					$cell6 = new html_table_cell(get_string('emptycell', 'report_feedbackoverview'));
				}

			$row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

			$table->data[] = $row;

		}
	}


  return $table;
}
