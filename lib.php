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
 * Report Feedback Dashboard Lib file.
 *
 * @package   report_feedbackdashboard
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Get assignments for given courseids
 *
 * @param array $courseids IDs of courses
 * @return array Array of Assignment data
 */
function report_feedbackdashboard_get_assignments($courseids) {
    global $DB;

    list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_QM, '', 1);
    $params = [] + $inparams;

    $sql = "SELECT FLOOR( 1 + RAND( ) *5000 ) id, a.id, a.name, cm.id AS cm, a.course,
                a.duedate, a.gradingduedate, c.fullname, c.shortname,
                cm.instance , cm.idnumber, cm.visible, cm.deletioninprogress,
            CASE
                WHEN cm.visible = 1 AND (a.markingworkflow = 1 AND gi.locked != 0)
                    AND (a.blindmarking = 1 AND a.revealidentities = 1) THEN 1
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

/**
 * Get Turnitin Feedback for current user
 *
 * @param array $assignmentids Array of assignment IDs for the user
 * @return array Array of Feedback data for given assignments
 */
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

/**
 * Get feedback from the Comments feedback subplugin for given assignmentids
 *
 * @param array $assignmentids Array of Assignment IDs
 * @return array Array of comment text for assignments
 */
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

/**
 * Get feedback file count from Feedback Files subplugin for given assignment IDs
 *
 * @param array $assignmentids Assignment IDs
 * @return array Count of number of feedback files for each assignment
 */
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

/**
 * Get submission status for given AssignmentIDs for current user
 *
 * @param array $assignmentids Array of AssignmentIDs
 * @return array Array of submission data
 */
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

/**
 * Get assignment data for Tutor's feedback report for each Assignment
 *
 * @param array $assignmentids Array of assignment IDs
 * @return array Assignment, Submission and Feedback data for given assignments
 */
function report_feedbackdashboard_get_tutor_data($assignmentids) {
    global $DB;

    list($inorequalsql, $inparams) = $DB->get_in_or_equal($assignmentids);
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

/**
 * Create Student Feedback Table
 *
 * @param object $course Course object
 * @param array $assignments List of assignment objects for course
 * @param array $turnitinfeedback List of Turnitin feedback objects for course
 * @param array $feedbackcomments List of Comment feedback objects for course
 * @param array $feedbackfiles List of File feedback objects for course
 * @param array $submission List of Submission data for course
 * @return html_table HTML output of Course Feedback table
 */
function report_feedbackdashboard_create_student_table($course, $assignments, $turnitinfeedback, $feedbackcomments,
    $feedbackfiles, $submission) {
    global $CFG, $USER;

    $txt = get_strings(
            ['assignmentname', 'duedate', 'datesubmitted', 'gradeddate', 'feedback', 'grade',
            'emptycell', 'nosubmitteddate', 'feedbackturnitin', 'feedbackfile', 'feedbackcomment'],
            'report_feedbackdashboard');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable boxaligncenter';
    $table->id = 'feedbackdashboard';
    $table->head = array($txt->assignmentname, $txt->duedate, $txt->datesubmitted, $txt->gradeddate, $txt->feedback, $txt->grade);

    $gradinginfo = null;
    $assigncount = 0;
    foreach ($assignments as $assignment) {
        if ($assignment->course != $course->id) {
            continue;
        }
        if ($assignment->visible != 1) {
            continue;
        }

        $assigncount++;
        $gradinginfo = grade_get_grades($assignment->course, 'mod', 'assign', $assignment->instance, $USER->id);

        foreach ($gradinginfo as $grades) {
            foreach ($grades as $g => $v) {
                $cmid = $assignments[$v->iteminstance]->cm;
                $row = new html_table_row();
                // Assignment name and link.
                $cell1 = new html_table_cell(
                    html_writer::link(new moodle_url('/mod/assign/view.php', ['id' => $cmid]), $v->name)
                );

                // Show due date.
                if ($assignments[$v->iteminstance]->duedate !== "0") {
                    $cell2 = new html_table_cell(date('d-m-Y, g:i A', $assignments[$v->iteminstance]->duedate));
                } else {
                    $cell2 = new html_table_cell($txt->emptycell);
                }

                // Show the submission date.
                if (isset($submission[$v->iteminstance]) && $submission[$v->iteminstance]->status == "submitted") {
                    $cell3 = new html_table_cell(date('d-m-Y, g:i:s A', ($submission[$v->iteminstance]->timemodified)));
                } else {
                    $cell3 = new html_table_cell($txt->nosubmitteddate);
                }

                if (isset($assignment->gradesvisible) && $assignment->gradesvisible == 1) {
                     // Empty cell if the assignment has not been graded.
                    if ($v->grades[$USER->id]->dategraded == null) {
                        $cell4 = new html_table_cell($txt->emptycell);
                    } else {
                        // Else show the grading date.
                        $cell4 = new html_table_cell(date('d-m-Y, g:i A', ($v->grades[$USER->id]->dategraded)));
                    }

                    $feedbackitems = [];
                    // Turnitin feedback.
                    if (isset($turnitinfeedback[$v->iteminstance]) && $turnitinfeedback[$v->iteminstance]->feedback == "1") {
                        $feedbackitems[] = html_writer::link(
                            new moodle_url('/mod/assign/view.php',
                                ['id' => $assignments[$v->iteminstance]->cm],
                                'submissionstatus'),
                            get_string('feedbackturnitin', 'report_feedbackdashboard')
                        );
                    }

                    // Feedback files.
                    if (isset($feedbackfiles[$v->iteminstance])
                        && ($feedbackfiles[$v->iteminstance]->numfiles !== null
                        && $feedbackfiles[$v->iteminstance]->numfiles !== "0")) {

                        $feedbackitems[] = html_writer::link(
                            new moodle_url('/mod/assign/view.php',
                                ['id' => $assignments[$v->iteminstance]->cm],
                                'feedback'),
                            get_string('feedbackfile', 'report_feedbackdashboard')
                        );
                    }

                    // Comment feedback.
                    if (isset($feedbackcomments[$v->iteminstance])
                        && ($feedbackcomments[$v->iteminstance]->commenttext !== ''
                        && $feedbackcomments[$v->iteminstance]->commenttext !== null)) {

                        $feedbackitems[] = html_writer::link(
                            new moodle_url('/mod/assign/view.php',
                                ['id' => $assignments[$v->iteminstance]->cm],
                                'feedback'),
                            get_string('feedbackcomment', 'report_feedbackdashboard')
                        );
                    }
                    if (count($feedbackitems) > 0) {
                        $cell5 = new html_table_cell(html_writer::alist($feedbackitems));
                    } else {
                        $cell5 = new html_table_cell(get_string('emptycell', 'report_feedbackdashboard'));
                    }

                    // Show the grade.
                    $cell6 = new html_table_cell($v->grades[$USER->id]->str_grade);
                } else {
                    // If the assignment is not locked, don't show feedback or grades.
                    $cell4 = new html_table_cell($txt->emptycell);
                    $cell5 = new html_table_cell($txt->emptycell);
                    $cell6 = new html_table_cell($txt->emptycell);
                }

                $row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);

                $table->data[] = $row;
            }
        }
    }

    if ($assigncount == 0) {
        $fillercell = new html_table_cell();
        $fillercell->text = html_writer::tag('p', get_string('noassignments', 'report_feedbackdashboard'));
        $fillercell->colspan = 6;
        $row = new html_table_row(array($fillercell));
        $table->data[] = $row;
    }

    return $table;
}

/**
 * Create Tutor feedback dashboard table
 *
 * @param objects $course Course object
 * @param array $assignments Array of Assignment objects
 * @return html_table HTML Table of assignment data for given course.
 */
function report_feedbackdashboard_create_tutor_table($course, $assignments) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $txt = get_strings(
        array('assignmentname', 'duedate', 'gradingdue', 'submissiontypes', 'submissions', 'gradingstatus', 'hidden',
        'celldrafts', 'cellsubmissions', 'students', 'gradingrelease', 'gradingreleasedhidden',
        'gradingreleasedidentities', 'gradingreleased', 'noassignments'),
        'report_feedbackdashboard');

    $assigncount = 0;
    foreach ($assignments as $assignment) {
        if ($assignment->course == $course->id) {
            $assigncount ++;
        }
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable boxaligncenter';
    $table->id = 'feedbackdashboard';
    $table->head = [
        $txt->assignmentname,
        $txt->duedate,
        $txt->gradingdue,
        $txt->submissiontypes,
        $txt->submissions,
        $txt->gradingstatus
    ];

    if ($assigncount == 0) {
        $fillercell = new html_table_cell();
        $fillercell->text = html_writer::tag('p', $txt->noassignments);
        $fillercell->colspan = 6;
        $row = new html_table_row(array($fillercell));
        $table->data[] = $row;
        return $table;
    }

    $gradinginfo = null;

    foreach ($assignments as $assignment) {
        if ($assignment->course != $course->id) {
            continue;
        }
        $context = context_module::instance($assignment->cm);
        $cm = get_coursemodule_from_instance('assign', $assignment->cm, 0);
        $assignclass = new assign($context, $cm, $course);
        $cmid = $assignment->cm;
        $row = new html_table_row();

        if ($assignment->visible == 0 ) {
            $cell1 = new html_table_cell(
                html_writer::link(
                    new moodle_url('/mod/assign/view.php', ['id' => $cmid]), $assignment->name . $txt->hidden
                )
            );
        } else {
            $cell1 = new html_table_cell(
                html_writer::link(
                    new moodle_url('/mod/assign/view.php', ['id' => $cmid]), $assignment->name
                )
            );
        }

        $cell2 = new html_table_cell(date('d-m-Y, g:i A', ($assignment->duedate)));
        $cell3 = new html_table_cell(date('d-m-Y, g:i A', ($assignment->gradingduedate)));

        $typeno = 0;
        $types = [];
        foreach ($assignclass->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                if ($plugin->get_type() != 'comments') {
                    $typeno++;
                    $types[] = get_string($plugin->get_type(), 'assignsubmission_' . $plugin->get_type());
                }
            }
        }
        $cell4 = new html_table_cell(html_writer::alist($types));

        $submissions = '';
        if ($typeno > 0 && $assignment->students != 0) {
            $submissions = $txt->celldrafts . $assignment->drafts . $txt->cellsubmissions . $assignment->submissions;
        }
        $cell5 = new html_table_cell($txt->students . $assignment->students . $submissions);

        $message = '';
        // Grading due date has passed and grading hasn't been done yet.
        if ($assignment->gradingurgent == 1 && $assignment->students != 0) {
            $message = $txt->gradingrelease;
            $row->attributes['class'] = 'grading-action';
        }

        // Grading due in n days (if assignment duedate has passed).
        if ($assignment->duedate < $assignment->timenow &&
            $assignment->gradingduedate > $assignment->timenow &&
            $assignment->students != 0) {
            // Work out number of days due.
            $datediff = $assignment->timenow - $assignment->gradingduedate;
            $days = ltrim(round($datediff / (60 * 60 * 24)), "-");

            $message = get_string('gradingduein', 'report_feedbackdashboard', ['days' => $days]);
            $row->attributes['class'] = 'grading-warning';
        }

        // Grades have been released, but the assignment is invisible (students can't see grades).
        if ($assignment->locked != 0 && $assignment->visible == 0 && $assignment->students != 0) {
            $message = $txt->gradingreleasedhidden;
            $row->attributes['class'] = 'grading-action';
        }

        // Grades have been released, but their identities have not (student can't see grades).
        if ($assignment->locked != 0 &&
            $assignment->blindmarking == 1 &&
            $assignment->revealidentities == 0 &&
            $assignment->students != 0) {
            $message = $txt->gradingreleasedidentities;
            $row->attributes['class'] = 'grading-action';
        }

        // Grades have been released - all good.
        if ($assignment->gradesvisible == 1 && $assignment->students != 0) {
            $message = $txt->gradingreleased;
            $row->attributes['class'] = 'grading-complete';
        }

        // Marking has passed the grading due date.
        if ($assignment->gradinglate == 1 && $assignment->students != 0) {
            // Work out number of days late.
            if ($assignment->locked == 0) {
                $datediff = $assignment->gradingduedate - $assignment->timenow;
            } else {
                $datediff = $assignment->gradingduedate - $assignment->locked;
            }

            $days = ltrim(round($datediff / (60 * 60 * 24)), "-");

            $cell6 = new html_table_cell(
                $message . get_string('dayslate', 'report_feedbackdashboard', ['dayslate' => $days])
            );
        } else {
            $cell6 = new html_table_cell($message);
        }

        $row->cells = array($cell1, $cell2, $cell3, $cell4, $cell5, $cell6);
        $table->data[] = $row;
    }

    return $table;
}

/**
 * Returns the Student Feedback dashboard HTML
 *
 * @param array $courses List of course objects the user is enrolled on
 * @param array $tutorcourses List of Tutor courses the user is enrolled on.
 * @return string HTML of the Student Dashboard page
 */
function report_feedbackdashboard_get_student_dashboard($courses, $tutorcourses) {
    $html = '';
    if (count($courses) == 0) {
        return $html;
    }
    if (count($tutorcourses) > 0) {
        $html .= html_writer::tag('h1', get_string('studentdashboard', 'report_feedbackdashboard'));
    }
    $html .= html_writer::start_tag('div', ['class' => 'feedbackdashboard-instructions']);
    $html .= get_string('instructionsstudent', 'report_feedbackdashboard');
    $html .= get_string('disclaimer', 'report_feedbackdashboard');
    $html .= html_writer::end_tag('div');

    $assignments = report_feedbackdashboard_get_assignments(array_keys($courses));

    if (count($assignments) == 0) {
        return '';
    }

    $assignmentids = array_keys($assignments);
    $turnitinfeedback = report_feedbackdashboard_get_turnitin_feedback($assignmentids);
    $feedbackcomments = report_feedbackdashboard_get_feedback_comments($assignmentids);
    $feedbackfiles = report_feedbackdashboard_get_feedback_files($assignmentids);
    $submission = report_feedbackdashboard_get_submission_status($assignmentids);

    foreach ($courses as $course) {
        $html .= html_writer::start_tag('div', ['class' => 'feedbackdashboard-course']);
        $html .= html_writer::tag('h3', $course->fullname);
        $html .= html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

        $table = report_feedbackdashboard_create_student_table(
            $course,
            $assignments,
            $turnitinfeedback,
            $feedbackcomments,
            $feedbackfiles,
            $submission
        );
        $html .= html_writer::table($table);
        $html .= html_writer::end_tag('div');
    }
    return $html;
}

/**
 * Returns the Tutor Feedback dashboard HTML
 *
 * @param array $courses List of course objects the user is enrolled on as a tutor
 * @param array $studentcourses List of course objects the user is enrolled on as a student
 * @return string HTML output of the Tutor dashboard
 */
function report_feedbackdashboard_get_tutor_dashboard($courses, $studentcourses) {
    global $CFG;
    require_once($CFG->dirroot.'/mod/assign/externallib.php');

    $html = '';
    if (count($courses) == 0) {
        return $html;
    }
    if (count($studentcourses) > 0) {
        $html .= html_writer::tag('h1', get_string('tutordashboard', 'report_feedbackdashboard'));
    }

    $html .= html_writer::start_tag('div', ['class' => 'feedbackdashboard-instructions']);
    $html .= get_string('instructionstutor', 'report_feedbackdashboard');
    $html .= html_writer::end_tag('div');

    $assignments = report_feedbackdashboard_get_assignments(array_keys($courses));

    if (count($assignments) == 0) {
        return '';
    }

    $data = report_feedbackdashboard_get_tutor_data(array_keys($assignments));

    foreach ($courses as $course) {
        $html .= html_writer::start_tag('div', ['class' => 'feedbackdashboard-course']);
        $html .= html_writer::tag('h3', $course->fullname);
        $html .= html_writer::tag('p', date('d/m/Y', $course->startdate) . ' - ' . date( "d/m/Y", $course->enddate));

        $table = report_feedbackdashboard_create_tutor_table($course, $data);
        $html .= html_writer::table($table);
        $html .= html_writer::end_tag('div');
    }
    return $html;
}
