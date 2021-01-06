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
 * External question API
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/datalib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/local/qmapi/questions/helper/question_helper.php');

/**
 * Question external functions
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.1
 */
class mapi_question_external extends external_api
{

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @author Masoud Zaeem
     * @action
     */
    public static function create_question_parameters()
    {
        $courseconfig = get_config('moodlecourse'); //needed for many default values
        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                            'categoryid' => new external_value(PARAM_INT, 'quiz id', null, 0),
                            // 'description' => new external_value(PARAM_RAW, 'question sh', VALUE_REQUIRED),
                            'courseid' => new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
                            'question_category' => new external_value(PARAM_INT, 'question category id', VALUE_OPTIONAL, VALUE_DEFAULT),

                        )
                    ),
                    'question for quiz created'
                )
            )
        );
    }

    /**
     * give parametes using API for Create quizes
     * @param array $question
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
    public static function create_question($question)
    {
        global $CFG, $DB;


        $quiz_id = $question[0]['quizid'];
        $course_id = $question[0]['courseid'];

        $quiz = $DB->get_record_sql("SELECT * FROM {quiz}
WHERE id = $quiz_id
AND course = $course_id
ORDER BY id", array($quiz_id, $course_id));


        $course_module = $DB->get_record_sql("
SELECT * FROM {course_modules}
WHERE course = $course_id AND module = 17 AND instance = $quiz_id
ORDER BY id ASC;
");

        $context = context_module::instance($course_module->id);
        $params = self::validate_parameters(self::create_question_parameters(), array('question' => $question));

        $questionaire = create_question_proccess(0, $quiz, false, '', $context->id, 'truefalse', null, 0, $question[0]['question_category']);

        return $questionaire;
    }

    /**
     * @return external_single_structure
     * @author Masoud Zaeem
     * @action
     */
    public
    static function create_question_returns()
    {

        return new external_single_structure(
            array(
                'quiz_id' => new external_value(PARAM_INT, 'quiz id'),
                'question_id' => new external_value(PARAM_INT, 'question id'),
                // 'course_id' => new external_value(PARAM_INT, 'course id'),
                'question_category_parent' => new external_value(PARAM_INT, 'question category'),
                'question_category' => new external_value(PARAM_INT, 'question category'),
                'slot_id' => new external_value(PARAM_INT, 'slot id'),
                'message' => new external_value(PARAM_TEXT, 'question created successfully'),
            )
        );
    }

    //TODO delete quiz proccess should be completed...
    //
    // public function delete_question_parameters()
    // {
    // return new external_function_parameters(
    // array(
    // 'question' => new external_multiple_structure(
    // new external_single_structure(
    // array(
    // 'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
    // 'categoryid' => new external_value(PARAM_INT, 'quiz id', null, 0),
    //// 'description' => new external_value(PARAM_RAW, 'question sh', VALUE_REQUIRED),
    // 'courseid' => new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
    // 'question_category' => new external_value(PARAM_INT, 'question category id', VALUE_OPTIONAL, VALUE_DEFAULT),
    //
    // )), 'question for quiz created'
    // )
    // )
    // );
    // }

}
