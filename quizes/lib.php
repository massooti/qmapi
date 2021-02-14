<?php
require_once($CFG->dirroot . '/course/lib.php');

function add_module_course($courseid, $instanceid, $modname, $sectionnum = 1)
{
    global $DB;
    course_create_sections_if_missing($courseid, $sectionnum);

    $moduleid = $DB->get_field('modules', 'id', array('name' => $modname), MUST_EXIST);
    $sectionid = $DB->get_field('course_sections', 'id', array('course' => $courseid, 'section' => $sectionnum), MUST_EXIST);

    // Add the module to the course.
    $newcm = new stdClass();
    $newcm->course = $courseid;
    $newcm->module = $moduleid;
    $newcm->section = $sectionid;
    $newcm->added = time();
    $newcm->instance = $instanceid;
    $newcm->visible = 1;
    $newcm->groupmode = 0;
    $newcm->groupingid = 0;
    $newcm->groupmembersonly = 0;
    $newcm->showdescription = 0;
    $cmid = add_course_module($newcm);
    // And add it to the section.
    course_add_cm_to_section($courseid, $cmid, $sectionnum);
}


function add_quiz_section(int $quizid)
{
    global $DB;

    $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.

    $quizSection = new stdClass();
    $quizSection->quizid = $quizid;
    $quizSection->firstslot = 1;
    $quizSection->heading = null;
    $quizSection->shufflequestion = 0;
    $DB->insert_record('quiz_sections', $quizSection);

    $transaction->allow_commit();
}
