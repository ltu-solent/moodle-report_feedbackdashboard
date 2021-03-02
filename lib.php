<?php

function get_assignments($courses){
	global $DB;
	
	$courseids = null;
	foreach ($courses as $course) {
	  $context = context_course::instance($course->id);
	  if(has_capability('mod/assign:submit', $context)){
		$courseids .= $course->id . ',';
	  }
	}

	$courseids = rtrim($courseids, ",");	

	$assignments = $DB->get_records_sql("SELECT FLOOR( 1 + RAND( ) *5000 ) id, a.id, cm.id AS cm, a.course, 
											a.duedate, c.fullname, c.shortname, cm.instance , cm.idnumber, cm.visible, cm.deletioninprogress
										FROM {course} c 
										JOIN {course_categories} cc ON cc.id = c.category
										LEFT JOIN {assign} a ON c.id = a.course								
										JOIN {course_modules} cm ON a.id = cm.instance AND cm.idnumber != ''
										JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
										WHERE c.id IN (" . $courseids . ")  
										AND cc.idnumber LIKE 'modules_%'
										AND cm.visible = 1");

	return $assignments;
}

function get_turnitin_feedback($assignmentids) {	
	global $DB, $USER;

	$turnitinfeedback = $DB->get_records_sql("SELECT g.iteminstance AS 'id', p.gm_feedback AS 'feedback', p.externalid, pu.turnitin_uid
											FROM {plagiarism_turnitin_files} p
											INNER JOIN {course_modules} cm ON p.cm = cm.id
											JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
											INNER JOIN {grade_items} g ON cm.instance = g.iteminstance
											INNER JOIN {assign} a ON g.iteminstance = a.id
											INNER JOIN {plagiarism_turnitin_users} pu ON p.userid = pu.userid
											WHERE p.userid =" . $USER->id .  " 
											AND g.iteminstance IN (" . $assignmentids . ")");
    return $turnitinfeedback;
}

function get_feedback_comments($assignmentids) {
	global $DB, $USER;

	$comment = $DB->get_records_sql("SELECT c.assignment AS 'id', c.commenttext
									FROM {assignfeedback_comments} c
									INNER JOIN {assign_grades} g ON c.grade = g.id
									WHERE g.userid = " . $USER->id . " 
									AND c.assignment IN (" . $assignmentids .")");
    return $comment;
}

function get_feedback_files($assignmentids) {
	global $DB, $USER;

	$files = $DB->get_records_sql("SELECT f.assignment AS 'id', f.numfiles
									FROM {assignfeedback_file} f
									INNER JOIN {assign_grades} g ON f.grade = g.id
									WHERE g.userid = " . $USER->id . " 
									AND f.assignment IN (" . $assignmentids.")");
	return $files;
}

function get_submission_status($assignmentids) {
	global $DB, $USER;

	$submission = $DB->get_records_sql("SELECT assignment, timemodified, status
										FROM {assign_submission} s
										WHERE userid = " . $USER->id . " AND assignment IN (" . $assignmentids.")");
	return $submission;
}

function create_table($course, $assignments, $turnitinfeedback, $feedbackcomments, $feedbackfiles, $submission) {
	global $CFG, $USER;

	$strassignment = get_string('assignmentname', 'report_feedbackdashboard');
	$strfeedback = get_string('feedback', 'report_feedbackdashboard');
	$strgrade = get_string('grade', 'report_feedbackdashboard');
	$strgradeddate = get_string('gradeddate', 'report_feedbackdashboard');
	$strdatesubmitted = get_string('datesubmitted', 'report_feedbackdashboard');
	$strduedate = get_string('duedate', 'report_feedbackdashboard');

	$table = new html_table();
	$table->attributes['class'] = 'generaltable boxaligncenter';
	$table->id = 'feedbackdashboard';
	$table->cellpadding = 5;
	$table->head = array($strassignment, $strdatesubmitted, $strduedate, $strgradeddate, $strfeedback, $strgrade);
	
	$gradinginfo = null;
	
	foreach ($assignments as $assignment) {

		if($assignment->course == $course->id){
		  $gradinginfo = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->instance, $USER->id); //get the grade information for the user
		  
		  	foreach ($gradinginfo as $grades) {

				foreach($grades as $g=>$v ){
					$row = new html_table_row();

					$cell1 = new html_table_cell(html_writer::tag('a', $v->name, ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm ]));

					if ($submission[$v->iteminstance]->status == "submitted") { //if the student has made a submission
						$cell2 = new html_table_cell(date('d-m-Y, g:i:s A', ($submission[$v->iteminstance]->timemodified))); //show the submission date
					} else {
						$cell2 = new html_table_cell(get_string('nosubmitteddate', 'report_feedbackdashboard')); //else, cell should say 'Not submitted'
					}
					if ($assignments[$v->iteminstance]->duedate !== "0") {
						$cell3 = new html_table_cell(date('d-m-Y, g:i A', ($assignments[$v->iteminstance]->duedate)));
					} else {
						$cell3 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					}

					if ($v->locked == true) {

						if ($v->grades[$USER->id]->dategraded == null) { //if the assignment has not been graded
							$cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard')); //cell should be empty
						} else {
							$cell4 = new html_table_cell(date('d-m-Y, g:i A', ($v->grades[$USER->id]->dategraded))); //else, show the grading date
						}

						$cell5 = new html_table_cell();
						$cell5->text .= '<ul>';

						if(isset($turnitinfeedback) && ($turnitinfeedback[$v->iteminstance]->feedback == "1")) {
							$cell5->text .= '<li>';
							$cell5->text .= html_writer::tag('a', get_string('feedbackturnitin', 'report_feedbackdashboard'), ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm . '#submissionstatus']);
							$cell5->text .= '</li>';
						}
						   
						if(isset($feedbackfiles[$v->iteminstance]) && ($feedbackfiles[$v->iteminstance]->numfiles !== null && $feedbackfiles[$v->iteminstance]->numfiles !== "0")) {
							$cell5->text .= '<li>';
							$cell5->text .= html_writer::tag('a', get_string('feedbackfile', 'report_feedbackdashboard'), ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm . '#feedback']);
							$cell5->text .= '</li>';
						}
					   
						if (isset($feedbackcomments[$v->iteminstance]) && ($feedbackcomments[$v->iteminstance]->commenttext !== '' && $feedbackcomments[$v->iteminstance]->commenttext !== null )) {
						  $cell5->text .= '<li>';
						  $cell5->text .= html_writer::tag('a', get_string('feedbackcomment', 'report_feedbackdashboard'), ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm . '#feedback']);//else, show the feedback
						  $cell5->text .= '</li>';
						}
						
						else{
							$cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
						}
						$cell5->text .= '</ul>';
						
						$cell6 = new html_table_cell($v->grades[$USER->id]->str_grade); //show the grade
						
					} else {
					  $cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					  $cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard')); //if the assignment is not locked, don't show feedback or grades
					  $cell6 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					}	
			
					$row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

					$table->data[] = $row;
				}
			}	
		}else{
			//echo "No assignments";
		}
	}

    return $table;
}