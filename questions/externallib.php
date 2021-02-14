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

use tool_dataprivacy\form\context_instance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir  . '/externallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/datalib.php');
require_once($CFG->dirroot . '/question/type/truefalse/question.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->libdir  . '/questionlib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/local/qmapi/questions/controller/add.php');

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

        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                            // 'categoryid' => new external_value(PARAM_INT, 'quiz id', null, 0),
                            'question_category' => new external_value(PARAM_INT, 'question category id', VALUE_OPTIONAL),
                            'name' => new external_value(PARAM_TEXT, 'question name', VALUE_REQUIRED),
                            'text' => new external_value(PARAM_TEXT, 'question text', VALUE_REQUIRED),
                            'generalfeedback' => new external_value(PARAM_TEXT, 'general feedback', VALUE_DEFAULT, 'generalfeedback'),
                            'textformat' => new external_value(PARAM_INT, 'text format', VALUE_DEFAULT, 1),
                            // 'rightanswer' => new external_value(PARAM_BOOL, 'question right answer', VALUE_DEFAULT, true),
                            'type' => new external_value(PARAM_TEXT, 'qtype', VALUE_REQUIRED),
                            'defaultmark' => new external_value(PARAM_FLOAT, 'default mark', VALUE_REQUIRED),
                            'penalty' => new external_value(PARAM_FLOAT, 'penalty', VALUE_DEFAULT, 1),
                        ),
                    ),
                    'question for quiz created'
                ),
                'answer' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'answer' => new external_value(PARAM_TEXT, 'select true answer', VALUE_REQUIRED),
                            'answerformat' => new external_value(PARAM_INT, 'course format option value', VALUE_OPTIONAL, 0),
                            'fraction' => new external_value(PARAM_FLOAT, 'question fraction for TRUE answer', VALUE_DEFAULT, 1.0),
                            'tfeedback' => new external_value(PARAM_RAW, 'feedback for TRUE answer', VALUE_OPTIONAL),
                            'ffeedback' => new external_value(PARAM_RAW, 'feedback for FALSE answer', VALUE_OPTIONAL),
                        ),
                    ),
                    'additional options/answers for question',
                    VALUE_REQUIRED
                ),
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
    public static function create_question($question, $answer)
    {
        global $CFG, $DB;

        $course_id = $DB->get_field('quiz', 'course', array('id' => $question[0]['quizid']));

        $params = self::validate_parameters(self::create_question_parameters(), array('question' => $question, 'answer' => $answer));
        $quiz = $DB->get_record('quiz', array('id' => $question[0]['quizid']));
        $questionBuilder = new QuestionBuilder();
        $__question = $questionBuilder->questionMaker($question, $course_id);
        $answerBuilder = new AnswerBuilder($__question);

        $__answer =  $answerBuilder->answerMaker($answer);

        $result = array();
        $result['course_id']    = $course_id;
        $result['quiz_id']      = $question[0]['quizid'];
        $result['quiz_name']    = $quiz->name;
        $result['question_name']     = $__question['question_name'];
        $result['question_id']  = $__question['id'];
        $result['qtype']        = $__question['type'];
        $result['defaultmark']  = $__question['defaultmark'];
        $result['right_answer_id'] = $__answer->trueanswer;
        $result['false_answer_id'] = $__answer->falseanswer;

        return $result;
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
                'course_id' => new external_value(PARAM_INT, 'course id'),
                'quiz_id' => new external_value(PARAM_INT, 'quiz id'),
                'quiz_name' => new external_value(PARAM_TEXT, 'quiz name'),
                'question_id' => new external_value(PARAM_INT, 'question id'),
                'question_name' => new external_value(PARAM_TEXT, 'question name'),
                'qtype' => new external_value(PARAM_TEXT, 'question type'),
                'defaultmark' => new external_value(PARAM_INT, 'defaultmark'),
                'right_answer_id' => new external_value(PARAM_INT, 'right answer id'),
                'false_answer_id' => new external_value(PARAM_INT, 'false answer id'),
                // 'message' => new external_value(PARAM_TEXT, 'question created successfully'),
            )
        );
    }

    public static function get_question_parameters()
    {
        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'questiion id', VALUE_REQUIRED),
                        )
                    ),
                    'show question'
                )
            )
        );
    }

    public static function get_question($question)
    {
        global $CFG, $DB;


        $params = self::validate_parameters(self::get_question_parameters(), array('question' => $question));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.
        if ($DB->record_exists('question', array('id' => $question[0]['id'])) == false) {
            throw new invalid_parameter_exception('question with given id dosent exist');
        }
        $questionObj = $DB->get_record('question', array('id' => $question[0]['id']));

        $fields = $DB->get_columns('question');
        $transaction->allow_commit();
        $result = [];
        $result['id'] = $question[0]['id'];
        $result['category'] = $questionObj->category;
        $result['question_name'] = $questionObj->name;
        $result['question_text'] = $questionObj->questiontext;
        $result['general_feed_back'] = $questionObj->generalfeedbackformat;
        $result['default_mark'] = $questionObj->defaultmark;
        $result['question_type'] = $questionObj->qtype;
        $result['created_by'] = $questionObj->createdby;
        $result['question_in_usage'] = questions_in_use(array('id' => $question[0]['id']));

        return $result;
    }

    public static function get_question_returns()
    {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'question id'),
                'category' => new external_value(PARAM_INT, 'question category'),
                'question_name' => new external_value(PARAM_TEXT, 'question name'),
                'question_text' => new external_value(PARAM_RAW, 'question text'),
                'question_type' => new external_value(PARAM_TEXT, 'question type'),
                'general_feed_back' => new external_value(PARAM_INT, 'question genereal feed back format'),
                'default_mark'      => new external_value(PARAM_FLOAT, 'default mark'),
                'created_by'    => new external_value(PARAM_INT, 'user created this question'),
                'question_in_usage'    => new external_value(PARAM_BOOL, 'question in usage'),
                // 'message' => new external_value(PARAM_TEXT, 'question created successfully'),
            )
        );
    }


    public static function delete_question_parameters()
    {

        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'questiion id', VALUE_REQUIRED),
                        )
                    ),
                    'delete question from question bank'
                )
            )
        );
    }

    public static function delete_question($question)
    {

        global $CFG, $DB;

        $params = self::validate_parameters(self::get_question_parameters(), array('question' => $question));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.
        if ($DB->record_exists('question', array('id' => $question[0]['id'])) == false) {
            throw new invalid_parameter_exception('question with given id dosent exist');
        }

        if (questions_in_use($question) == false) {

            return ['message' => "question can not be delete becuse it's in use"];
        }

        question_delete_question($question[0]['id']);

        $transaction->allow_commit();

        return ['message' => 'question deleted successfully'];
    }

    public static function delete_question_returns()
    {
        return new external_single_structure(
            array(
                'message' => new external_value(
                    PARAM_TEXT,
                    'question delete response message'
                )
            )
        );
    }


    public static function edit_question_parameters()
    {

        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'questiion id', VALUE_REQUIRED),
                            'qtype' => new external_value(PARAM_TEXT, 'question type', VALUE_OPTIONAL),
                            'name' => new external_value(PARAM_TEXT, 'question name', VALUE_OPTIONAL),
                            'text' => new external_value(PARAM_RAW, 'question text', VALUE_OPTIONAL),

                        )
                    ),
                    'edit question from question bank'
                )
            )
        );
    }


    public static function edit_question($question)
    {
        global $CFG, $DB;

        $params = self::validate_parameters(self::edit_question_parameters(), array('question' => $question));
        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.
        if ($DB->record_exists('question', array('id' => $question[0]['id'])) == false) {
            throw new invalid_parameter_exception('question with given id dosent exist');
        }
        question_bank::load_question_definition_classes('truefalse');
        $tf = new qtype_truefalse_question();
        test_question_maker::initialise_a_question($tf);
        $tf->name = 'True/false question';
        $tf->questiontext = 'The answer is true.';
        $tf->generalfeedback = 'You should have selected true.';
        $tf->penalty = 1;
        $tf->qtype = question_bank::get_qtype('truefalse');

        $tf->rightanswer = true;
        $tf->truefeedback = 'This is the right answer.';
        $tf->falsefeedback = 'This is the wrong answer.';
        $tf->truefeedbackformat = FORMAT_HTML;
        $tf->falsefeedbackformat = FORMAT_HTML;
        $tf->trueanswerid = 13;
        $tf->falseanswerid = 14;

        return $tf;
    }


    public static function edit_question_returns()
    {
        return new external_function_parameters(
            array(
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'question id')
                        )
                    )
                )
            )
        );
    }
}
