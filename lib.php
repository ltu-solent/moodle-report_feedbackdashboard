<?php

function report_feedbackdashboard_get_assignments($courseids){
	global $DB;	
	
	list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_QM, '', 1);			
	$params = [] + $inparams;
	
	$sql = "SELECT FLOOR( 1 + RAND( ) *5000 ) id, a.id, a.name, cm.id AS cm, a.course, 
				a.duedate, a.gradingduedate, c.fullname, c.shortname, 
				cm.instance , cm.idnumber, cm.visible, cm.deletioninprogress,
			CASE 
				WHEN cm.visible = 1 AND (a.markingworkflow = 1 AND gi.locked != 0) AND (a.blindmarking = 1 AND a.revealidentities = 1) THEN 1
				WHEN cm.visible = 1 AND (a.markingworkflow = 1 AND gi.locked != 0) AND (a.blindmarking = 0) THEN 1
				WHEN cm.visible = 1 AND a.markingworkflow = 0 AND a.blindmarking = 0 THEN 1
				WHEN cm.visible = 1 AND a.markingworkflow = 0 AND a.blindmarking = 1 AND a.revealidentities = 1 THEN 1
			ELSE 0
			END as gradesvisible
			FROM {course} c 
			JOIN {course_categories} cc ON cc.id = c.category
			LEFT JOIN {assign} a ON c.id = a.course								
			JOIN {course_modules} cm ON a.id = cm.instance AND cm.idnumber != ''
			JOIN {grade_items} gi ON cm.instance = gi.iteminstance AND gi.itemmodule = 'assign'
			WHERE c.id {$insql}
			ORDER BY c.enddate, c.shortname desc";
	
	$assignments = $DB->get_records_sql($sql, $params);

	return $assignments;
}

function report_feedbackdashboard_get_turnitin_feedback($assignmentids) {	
	global $DB, $USER;
	
	list($insql, $inparams) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_QM, '', 1);			
	$params = [] + $inparams;
	
	$sql = "SELECT gi.iteminstance AS 'id', p.gm_feedback AS 'feedback', p.externalid, pu.turnitin_uid
			FROM {plagiarism_turnitin_files} p
			JOIN {course_modules} cm ON p.cm = cm.id
			JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
			JOIN {grade_items} gi ON cm.instance = gi.iteminstance
			JOIN {assign} a ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
			JOIN {plagiarism_turnitin_users} pu ON p.userid = pu.userid
			WHERE p.userid = {$USER->id} 
			AND gi.iteminstance {$insql}";
		
	$turnitinfeedback = $DB->get_records_sql($sql, $params);

    return $turnitinfeedback;
}

function report_feedbackdashboard_get_feedback_comments($assignmentids) {
	global $DB, $USER;

	list($insql, $inparams) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_QM, '', 1);	
	$params = [] + $inparams;

	$sql = "SELECT c.assignment AS 'id', c.commenttext
			FROM {assignfeedback_comments} c
			JOIN {assign_grades} ag ON c.grade = ag.id
			WHERE ag.userid = {$USER->id}
			AND c.assignment {$insql}";
									
								
	$comments = $DB->get_records_sql($sql, $params);
	
    return $comments;
}

function report_feedbackdashboard_get_feedback_files($assignmentids) {
	global $DB, $USER;
			
	list($insql, $inparams) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_QM, '', 1);	
	$params = [] + $inparams;
	
	$sql = "SELECT f.assignment AS 'id', f.numfiles
			FROM {assignfeedback_file} f
			JOIN {assign_grades} ag ON f.grade = ag.id
			WHERE ag.userid = {$USER->id}
			AND f.assignment {$insql}";
									
	$files = $DB->get_records_sql($sql, $params);
	
	return $files;
}

function report_feedbackdashboard_get_submission_status($assignmentids) {
	global $DB, $USER;

	list($insql, $inparams) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_QM, '', 1);	
	$params = [] + $inparams;

	$sql = "SELECT assignment, timemodified, status
			FROM {assign_submission} s
			WHERE userid = {$USER->id}
			AND s.assignment {$insql}";
			
	$submission = $DB->get_records_sql($sql, $params);									
	
	return $submission;
}

function report_feedbackdashboard_get_tutor_data($assignmentids){	
	global $DB;		

	list($inorequalsql, $inparams) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_QM, '', 1);			
	$params = [] + $inparams;

	$sql = "SELECT a.id, cm.id cm, a.name, a.duedate, c.id course, c.shortname,
			(SELECT COUNT(ra.userid) FROM {role_assignments} AS ra
			JOIN {context} AS ctx ON ra.contextid = ctx.id
			WHERE ra.roleid = 5 AND ctx.instanceid = c.id
			) as students,
			(SELECT count(*)
				FROM {assign_submission} sub
				JOIN {assign} a1 ON a1.id = sub.assignment
				JOIN {user} u ON u.id = sub.userid
				JOIN {course} c1 ON c1.id = a1.course 
				JOIN {course_modules} cm ON c1.id = cm.course
				JOIN {modules} m ON m.id = cm.module
				JOIN {context} cxt ON c1.id=cxt.instanceid AND cxt.contextlevel=50
				JOIN {role_assignments} ra ON cxt.id = ra.contextid AND ra.roleid=5 AND ra.userid=u.id 
				WHERE cm.instance = a1.id 
				AND a1.id = a.id
				AND m.name = 'assign' 
			AND sub.status='draft') as drafts,
			(SELECT count(*)
				FROM {assign_submission} sub
				JOIN {assign} a1 ON a1.id = sub.assignment
				JOIN {user} u ON u.id = sub.userid
				JOIN {course} c1 ON c1.id = a1.course 
				JOIN {course_modules} cm ON c1.id = cm.course
				JOIN {modules} m ON m.id = cm.module
				JOIN {context} cxt ON c1.id=cxt.instanceid AND cxt.contextlevel=50
				JOIN {role_assignments} ra ON cxt.id = ra.contextid AND ra.roleid=5 AND ra.userid=u.id 
				WHERE cm.instance = a1.id 
				AND a1.id = a.id
				AND m.name = 'assign' 
			AND sub.status='submitted') as submissions,
			a.gradingduedate,
			UNIX_TIMESTAMP() timenow,
			cm.visible,
			g.locked,
			a.blindmarking, a.revealidentities,
			CASE WHEN cm.visible = 1 AND g.locked != 0 AND a.blindmarking = 1 AND a.revealidentities = 1 THEN 1
				WHEN cm.visible = 1 AND g.locked != 0 AND a.blindmarking = 0 AND a.revealidentities = 0 THEN 1
				ELSE 0
				END as gradesvisible,
			CASE WHEN a.gradingduedate < UNIX_TIMESTAMP() AND g.locked = 0 THEN 1 ELSE 0 END gradingurgent,
			CASE WHEN a.gradingduedate < g.locked THEN 1 
				WHEN a.gradingduedate < UNIX_TIMESTAMP() AND g.locked = 0 THEN 1
				ELSE 0 END gradinglate
			FROM {course} c
			JOIN {course_categories} cc ON cc.id = c.category
			JOIN {assign} a ON a.course = c.id
			JOIN {course_modules} cm ON cm.instance = a.id
			JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
			JOIN {grade_items} g ON g.iteminstance = cm.instance AND g.itemmodule = 'assign'
			LEFT JOIN {assign_grades} gr ON gr.assignment = a.id
			LEFT JOIN {assign_user_mapping} mp ON mp.assignment = a.id AND mp.userid = gr.userid
			LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = mp.userid
			WHERE a.id {$inorequalsql}
			GROUP BY a.id";

	$data = $DB->get_records_sql($sql, $params);	

	return $data;
}

function report_feedbackdashboard_create_student_table($course, $assignments, $turnitinfeedback, $feedbackcomments, $feedbackfiles, $submission) {
	global $CFG, $USER;
	
	$txt = get_strings(array('assignmentname', 'duedate', 'datesubmitted', 'gradeddate', 'feedback', 'grade'), 'report_feedbackdashboard');

	$table = new html_table();
	$table->attributes['class'] = 'generaltable boxaligncenter';
	$table->id = 'feedbackdashboard';
	$table->cellpadding = 5;
	$table->head = array($txt->assignmentname, $txt->duedate, $txt->datesubmitted, $txt->gradeddate, $txt->feedback, $txt->grade);
	
	$gradinginfo = null;
	$assigncount = 0;
	
	foreach ($assignments as $assignment ) {
		if($assignment->course == $course->id && $assignment->visible == 1){
			$assigncount ++;
			$gradinginfo = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->instance, $USER->id); 
		  
			foreach ($gradinginfo as $grades) {

				foreach($grades as $g=>$v ){
					$row = new html_table_row();
					
					$cell1 = new html_table_cell(html_writer::tag('a', $v->name, ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm ]));
					
					if ($assignments[$v->iteminstance]->duedate !== "0") {
						$cell2 = new html_table_cell(date('d-m-Y, g:i A', ($assignments[$v->iteminstance]->duedate)));
					} else {
						$cell2 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					}
					
					if (isset($submission[$v->iteminstance]) && $submission[$v->iteminstance]->status == "submitted") { // show the submission date
						$cell3 = new html_table_cell(date('d-m-Y, g:i:s A', ($submission[$v->iteminstance]->timemodified)));
					} else {
						$cell3 = new html_table_cell(get_string('nosubmitteddate', 'report_feedbackdashboard')); 
					}	

					if (isset($assignment->gradesvisible) && $assignment->gradesvisible == 1) {

						if ($v->grades[$USER->id]->dategraded == null) { //empty cell if the assignment has not been graded
							$cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
						} else { // else show the grading date
							$cell4 = new html_table_cell(date('d-m-Y, g:i A', ($v->grades[$USER->id]->dategraded))); 
						}

						$cell5 = new html_table_cell();
						$cell5->text .= '<ul>';

						if(isset($turnitinfeedback[$v->iteminstance]) && $turnitinfeedback[$v->iteminstance]->feedback == "1") {
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
						  $cell5->text .= html_writer::tag('a', get_string('feedbackcomment', 'report_feedbackdashboard'), ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignments[$v->iteminstance]->cm . '#feedback']);
						  $cell5->text .= '</li>';
						}
						
						else{
							$cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
						}
						$cell5->text .= '</ul>';		
						
						$cell6 = new html_table_cell($v->grades[$USER->id]->str_grade); //show the grade						
						
					} else { //if the assignment is not locked, don't show feedback or grades
					  $cell4 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					  $cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard')); 
					  $cell6 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
					}	
			
					$row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

					$table->data[] = $row;
				}
			}	
		}
	}
	
	if($assigncount == 0){
		$fillercell = new html_table_cell();
        $fillercell->text = html_writer::tag('p', get_string('noassignments', 'report_feedbackdashboard'));
        $fillercell->colspan = 6;
        $row = new html_table_row(array($fillercell));
        $table->data[] = $row;		
	}

    return $table;
}

function report_feedbackdashboard_create_tutor_table($course, $assignments) {
	global $CFG, $USER;
	require_once($CFG->dirroot.'/mod/assign/locallib.php');	
	
	$txt = get_strings(array('assignmentname', 'duedate', 'gradingdue', 'submissiontypes', 'submissions', 'gradingstatus'), 'report_feedbackdashboard');
	
	$assigncount = 0;
	foreach ($assignments as $assignment) {
		if($assignment->course == $course->id){
			$assigncount ++;
		}
	}
	
	$table = new html_table();
	$table->attributes['class'] = 'generaltable boxaligncenter';
	$table->id = 'feedbackdashboard';
	$table->cellpadding = 5;
	$table->head = array($txt->assignmentname, $txt->duedate, $txt->gradingdue, $txt->submissiontypes, $txt->submissions, $txt->gradingstatus);
	
	if($assigncount > 0){		
		
		$gradinginfo = null;
	
		foreach ($assignments as $assignment) {
			if($assignment->course == $course->id){
				$context = context_module::instance($assignment->cm);
				$cm = get_coursemodule_from_instance('assign', $assignment->cm, 0);
				$assignclass = new assign($context, $cm, $course);

				$row = new html_table_row();
				
				if($assignment->visible == 0 ){
					$cell1 = new html_table_cell(html_writer::tag('a', $assignment->name . get_string('hidden', 'report_feedbackdashboard'), ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignment->cm ]));
				}else{
					$cell1 = new html_table_cell(html_writer::tag('a', $assignment->name, ['href'=>$CFG->wwwroot . '/mod/assign/view.php?id=' . $assignment->cm ]));
				}
				
				$cell2 = new html_table_cell(date('d-m-Y, g:i A', ($assignment->duedate)));					
				$cell3 = new html_table_cell(date('d-m-Y, g:i A', ($assignment->gradingduedate)));
				
				$typeno = 0;
				$types = "<ul>";
				foreach ($assignclass->get_submission_plugins() as $plugin) {					
					if ($plugin->is_enabled() && $plugin->is_visible()) {
						if($plugin->get_type() != 'comments'){
							$typeno++;
							$types .= "<li>" . get_string($plugin->get_type(), 'assignsubmission_' . $plugin->get_type()) . "</li>";
						}
					}
				}
				$types .= "</ul>";
				$cell4 = new html_table_cell($types);
				
				if($typeno > 0 && $assignment->students != 0){
					$submissions = get_string('celldrafts', 'report_feedbackdashboard') . $assignment->drafts . get_string('cellsubmissions', 'report_feedbackdashboard') . $assignment->submissions;
				}else{
					$submissions = "";
				}
				$cell5 = new html_table_cell(get_string('students', 'report_feedbackdashboard') . $assignment->students . $submissions);
				
                $message = '';
				if($assignment->gradingurgent == 1 && $assignment->students != 0){
					$message = get_string('gradingrelease', 'report_feedbackdashboard');
					$row->attributes['class'] = 'grading-action';
				}
                
                if($assignment->duedate < $assignment->timenow && $assignment->gradingduedate > $assignment->timenow && $assignment->students != 0){
					//Work out number of days due				
					$datediff = $assignment->timenow - $assignment->gradingduedate;
					$days = ltrim(round($datediff / (60 * 60 * 24)), "-");

					$message = get_string('gradingduein', 'report_feedbackdashboard', ['days'=>$days]);
                    $row->attributes['class'] = 'grading-warning';
				}
                
                if ($assignment->locked != 0 && $assignment->visible == 0 && $assignment->students != 0){
					$message = get_string('gradingreleasedhidden', 'report_feedbackdashboard');
					$row->attributes['class'] = 'grading-action';
				}
                
                if ($assignment->locked != 0 && $assignment->blindmarking == 1 && $assignment->revealidentities == 0 && $assignment->students != 0){
					$message = get_string('gradingreleasedidentities', 'report_feedbackdashboard');
					$row->attributes['class'] = 'grading-action';
				}
                
                if($assignment->gradesvisible == 1 && $assignment->students != 0){
					$message = get_string('gradingreleased', 'report_feedbackdashboard');
					$row->attributes['class'] = 'grading-complete';
				}
				
				if($assignment->gradinglate == 1 && $assignment->students != 0){
					//Work out number of days late			
					if($locked = $assignment->locked == 0){						
						$datediff = $assignment->gradingduedate - $assignment->timenow;											
					}else{
						$datediff = $assignment->gradingduedate - $assignment->locked;
					}
					
					$days = ltrim(round($datediff / (60 * 60 * 24)), "-");
					
					$cell6 = new html_table_cell($message . get_string('dayslate', 'report_feedbackdashboard', ['dayslate'=>$days]));
				}else{
					$cell6 = new html_table_cell($message);
				}
		
				$row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

				$table->data[] = $row;				
			}
		}
	}else{
		$fillercell = new html_table_cell();
        $fillercell->text = html_writer::tag('p', get_string('noassignments', 'report_feedbackdashboard'));
        $fillercell->colspan = 6;
        $row = new html_table_row(array($fillercell));
        $table->data[] = $row;		
	}
	
    return $table;
}

function report_feedbackdashboard_get_student_dashboard($courses, $tutorcourses){
	$html = null;
	if(count($tutorcourses) > 0){			
		$html .= html_writer::tag('h1', get_string('studentdashboard', 'report_feedbackdashboard'));			
	}
	$html .= html_writer::start_tag('div', ['class'=>'feedbackdashboard-instructions']);
	$html .= get_string('instructionsstudent', 'report_feedbackdashboard');
	$html .= get_string('disclaimer', 'report_feedbackdashboard');
	$html .= html_writer::end_tag('div');
	
	if(!empty($courses)){
		$assignments = report_feedbackdashboard_get_assignments(array_keys($courses));
		$assignmentids = array_keys($assignments);
		
		if(count($assignments) > 0){		
			$turnitinfeedback = report_feedbackdashboard_get_turnitin_feedback($assignmentids);
			$feedbackcomments = report_feedbackdashboard_get_feedback_comments($assignmentids);
			$feedbackfiles = report_feedbackdashboard_get_feedback_files($assignmentids);
			$submission = report_feedbackdashboard_get_submission_status($assignmentids);	

			foreach ($courses as $course) { //for each the user's units
				$html .= html_writer::start_tag('div', ['class'=>'feedbackdashboard-course']);
				$html .= html_writer::tag('h3', $course->fullname);
				$html .= html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

				$table = report_feedbackdashboard_create_student_table($course, $assignments, $turnitinfeedback, $feedbackcomments, $feedbackfiles, $submission); //generate a table containing the assignment information and grades
				
				$html .= html_writer::table($table); //show the table
				$html .= html_writer::end_tag('div');
			}
			
			return $html;
		}
	}else{
		return null;
	}
}

function report_feedbackdashboard_get_tutor_dashboard($courses, $studentcourses){	
	global $CFG;
	require_once($CFG->dirroot.'/mod/assign/externallib.php');	

	$html = null;
	if(!empty($courses)){
		if(count($studentcourses) > 0){			
			$html .= html_writer::tag('h1', get_string('tutordashboard', 'report_feedbackdashboard'));			
		}
		
		$html .= html_writer::start_tag('div', ['class'=>'feedbackdashboard-instructions']);
		$html .= get_string('instructionstutor', 'report_feedbackdashboard');
		$html .= html_writer::end_tag('div');
		
		$assignments = report_feedbackdashboard_get_assignments(array_keys($courses));

		if(count($assignments) > 0){		
			$data = report_feedbackdashboard_get_tutor_data(array_keys($assignments));		

			foreach ($courses as $course) { //for each the user's units
				$html .= html_writer::start_tag('div', ['class'=>'feedbackdashboard-course']);
				$html .= html_writer::tag('h3', $course->fullname);
				$html .= html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

				$table = report_feedbackdashboard_create_tutor_table($course, $data); //generate a table containing the assignment information and grades
				$html .= html_writer::table($table); //show the table
				$html .= html_writer::end_tag('div');
			}
			
			return $html;
		}
	}else{
		return null;
	}		
}