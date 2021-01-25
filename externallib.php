<?php

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
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");

class mapi_quiz_external extends external_api
{

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @author Masoud Zaeem
     * @action
     */
    //TODO create exam section paramter
    public static function create_quiz_section_parameters()
    {
        $courseconfig = get_config('moodlecourse'); //needed for many default values
        return new external_function_parameters(
            array(
                'quiz' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
                            'generalname' => new external_value(PARAM_TEXT, 'general exam name', VALUE_REQUIRED),
                            'description' => new external_value(PARAM_RAW, 'course short name', VALUE_REQUIRED),

                        )
                    ),
                    'quiz for course created'
                )
            )
        );
    }

    //TODO create quiz

    /**
     * give parametes using API for Create  quizes
     * @param array $quiz
     * @return array
     * @return array courses (id and shortname only)
     * @return external_function_parameters
     * @throws invalid_parameter_exception
     *
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @author Masoud Zaeem
     * @action
     */
    public static function create_quiz_section($quiz)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/mod/quiz/lib.php");
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::create_quiz_section_parameters(), array('quiz' => $quiz));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.

        foreach ($params['quiz'] as $exam) {
            $exam = (object)$exam;

            if (trim($exam->courseid) == '') {
                throw new invalid_parameter_exception('Invalid course id');
            }
            if ($DB->get_record('quiz', array('course' => $exam->courseid, 'name' => $exam->generalname))) {
                throw new invalid_parameter_exception('exam with the same name already exists in the course');
            }
        }


        $quiz = new StdClass();
        $quiz->id = 0;
        $quiz->course = $exam->courseid;
        $quiz->name = $exam->generalname;
        $quiz->intro = $exam->description;
        $quiz->introformat = 1;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->overduehandling = 'autosubmit';
        $quiz->reviewattempt = 65792;
        $quiz->reviewcorrectness = 4352;
        $quiz->reviewmarks = 4352;
        $quiz->questionperpage = 1;
        $quiz->shuffleanswers = 1;
        $quiz->grade = 10.00000;
        //        $quiz->timecreated = date
        //        $quiz->password = 123;
        function addmoduletocourse($courseid, $instanceid, $modname, $sectionnum = 1)
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

        // for quiz record create
        $quizObj = $DB->insert_record('quiz', $quiz);
        // for add course_modules record
        addmoduletocourse($exam->courseid, $quizObj, 'quiz');
        $transaction->allow_commit();

        // returning response status
        if (isset($quizObj)) {
            $result = array();
            $result['quiz_id'] = $quizObj;
            $result['quiz_name'] = $DB->get_field('quiz', 'name', array('id' => $quizObj), MUST_EXIST);
            $result['course_id'] = $DB->get_field('course', 'id', array('id' => $exam->courseid), MUST_EXIST);
            $result['course_name'] = $DB->get_field('course', 'fullname', array('id' => $exam->courseid), MUST_EXIST);
            $result['status'] = 201;
            $result['message'] = "Quiz created succesfully";
        } else {
            $result = array();
            $result['quiz'] = 0;
            $result['status'] = 400;
            $result['message'] = "Some error occured please try again";
        }

        return $result;
    }


    //TODO retrun quiz response

    /**
     * @return external_single_structure
     * @author Masoud Zaeem
     * @action
     */
    public
    static function create_quiz_section_returns()
    {
        return new external_single_structure(
            array(
                'quiz_id' => new external_value(PARAM_INT, 'quiz id'),
                'quiz_name' => new external_value(PARAM_TEXT, 'quiz name'),
                'course_id' => new external_value(PARAM_INT, 'course id'),
                'course_name' => new external_value(PARAM_TEXT, 'course name'),
                'status' => new external_value(PARAM_INT, 'quiz created successfully'),
                'message' => new external_value(PARAM_TEXT, 'quiz created successfully'),
            )
        );
    }


    public static function edit_quiz_section_parameters()
    {
        $quizconfig = get_config('quiz');
        // print_r($quizconfig);
        // die();
        return new external_function_parameters(
            array(
                'quiz' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                            'quizformatoptions' =>

                            new external_single_structure(
                                array(
                                    // 'course' => new external_value(PARAM_INT, 'course id', VALUE_OPTIONAL),
                                    'name' => new external_value(PARAM_RAW, 'quiz general name', VALUE_OPTIONAL),
                                    'intro' => new external_value(PARAM_RAW, 'quiz description value', VALUE_OPTIONAL),
                                    'introformat' => new external_value(PARAM_INT, 'quiz description value', VALUE_DEFAULT, 1),
                                    'timeopen' => new external_value(PARAM_INT,  'time start', VALUE_DEFAULT, 0),
                                    'timeclose' => new external_value(PARAM_INT, 'time close', VALUE_DEFAULT, 0),
                                    'timelimit' => new external_value(PARAM_INT, 'time limit', VALUE_DEFAULT, 0),
                                    'preferredbehaviour' => new external_value(PARAM_TEXT, 'deferredfeedback, adaptivemode, immediatefeedback', VALUE_DEFAULT, $quizconfig->preferredbehaviour),
                                    'overduehandling' => new external_value(PARAM_TEXT,  'quiz over duehandling', VALUE_DEFAULT, $quizconfig->overduehandling),
                                    'reviewattempt' => new external_value(PARAM_INT,  'reviewattempt', VALUE_DEFAULT, $quizconfig->reviewattempt),
                                    'reviewcorrectness' => new external_value(PARAM_INT, 'reviewcorrectness', VALUE_DEFAULT, $quizconfig->reviewcorrectness),
                                    'reviewmarks' => new external_value(PARAM_INT, 'reviewmarks', VALUE_DEFAULT, $quizconfig->reviewmarks),
                                    'grade' => new external_value(PARAM_INT, 'grade', VALUE_OPTIONAL),
                                    'shuffleanswers' => new external_value(PARAM_INT, 'shuffleanswers', VALUE_DEFAULT, $quizconfig->shuffleanswers),
                                ),
                                'additional options for particular quiz format',
                                VALUE_OPTIONAL
                            ),
                        ),
                    )
                ),

            ),
            'update course given quiz'
        );
    }

    public function edit_quiz_section($quiz)
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/mod/quiz/lib.php");
        require_once($CFG->libdir  . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::edit_quiz_section_parameters(), array('quiz' => $quiz));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.

        if ($DB->record_exists('quiz', array('id' => $quiz[0]['id'])) == false) {
            throw new invalid_parameter_exception('exam with given id dosen`t exists in the course');
        }
        $quizParams = array();
        $coloums = $DB->get_columns('quiz');

        foreach ($coloums as $col) {
            if (isset($quiz[0]['quizformatoptions'][$col->name])) {
                $quizParams[$col->name] = $quiz[0]['quizformatoptions'][$col->name];
            }
        }
        $quizParams['id'] = $quiz[0]['id'];
        $quizObj =  $DB->update_record_raw('quiz', $quizParams);

        $transaction->allow_commit();

        $result = array();
        $result['quizid'] = $quiz[0]['id'];
        $result['status'] = 201;
        $result['message'] = "Quiz edited succesfully";

        return $result;
    }


    public
    static function edit_quiz_section_returns()
    {
        return new external_single_structure(
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz id'),
                'status' => new external_value(PARAM_INT, 'quiz edited successfully'),
                'message' => new external_value(PARAM_TEXT, 'quiz edited successfully'),
            )
        );
    }

    public static function delete_quiz_section_parameters()
    {
        return new external_function_parameters(
            array(
                'quiz' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                            'courseid' => new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
                        )
                    ),
                    'remove course given quiz'
                )
            )
        );
    }

    public static function delete_quiz_section($quiz)
    {

        global $CFG, $DB;
        require_once($CFG->dirroot . "/mod/quiz/lib.php");
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::delete_quiz_section_parameters(), array('quiz' => $quiz));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.

        if ($DB->record_exists('quiz', array('id' => $quiz[0]['quizid'])) == false) {
            throw new invalid_parameter_exception('exam with given id dosen`t exists in the course');
        }

        $quizSlots =  $DB->delete_records('quiz_slots', array('quizid' => $quiz[0]['quizid']));
        $cm = get_coursemodule_from_instance('quiz', $quiz[0]['quizid'], $quiz[0]['courseid']);
        course_delete_module($cm->id);
        $quiz = $DB->delete_records('quiz', ['id' => $quiz[0]['quizid']]);

        $transaction->allow_commit();

        $result = array();
        $result['status'] = 201;
        $result['message'] = "Quiz deleted succesfully";

        return $result;
    }


    public
    static function delete_quiz_section_returns()
    {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'quiz created successfully'),
                'message' => new external_value(PARAM_TEXT, 'quiz deleted successfully'),
            )
        );
    }
}
