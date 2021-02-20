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

use ScssPhp\ScssPhp\Cache;
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
require_once($CFG->dirroot . '/local/qmapi/questions/controller/edit.php');

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
                            'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_DEFAULT, 0),
                            'question_category' => new external_value(PARAM_INT, 'question category id', VALUE_DEFAULT, 0),
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
                            'answer' => new external_value(PARAM_BOOL, 'select RiGHT answer', VALUE_OPTIONAL),
                            'answerformat' => new external_value(PARAM_INT, 'course format option value', VALUE_OPTIONAL, 0),
                            'rfeedback' => new external_value(PARAM_RAW, 'feedback for RIGHT answer', VALUE_OPTIONAL),
                            'wfeedback' => new external_value(PARAM_RAW, 'feedback for WRONG answer', VALUE_OPTIONAL),
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
        global $DB;
        $params = self::validate_parameters(self::create_question_parameters(), array('question' => $question, 'answer' => $answer));
        if ($DB->record_exists('quiz', array('id' => $question[0]['quizid'])) == false && $question[0]['quizid'] != 0) {
            print_error('quiz does not exist', 'quiz', null, null, 'choose correct quiz id');
        } elseif ($DB->record_exists('question_categories', array('id' => $question[0]['question_category'])) == false && $question[0]['question_category']) {
            print_error('categorydoesnotexist', 'question', null, null, 'choose correct category id');
        }

        $questionBuilder = new QuestionBuilder();
        $__question = $questionBuilder->questionMaker($question);
        $answerBuilder = new AnswerBuilder($__question);

        $answerBuilder->answerMaker($answer);

        return $__question;
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
                'id' => new external_value(PARAM_INT, 'question id'),
                'quiz_id' => new external_value(PARAM_INT, 'quiz id'),
                'course_id' => new external_value(PARAM_INT, 'course id'),
                'course_name' => new external_value(PARAM_TEXT, 'course name'),
                'quiz_name' => new external_value(PARAM_TEXT, 'quiz name'),
                'question_name' => new external_value(PARAM_TEXT, 'question name'),
                'question_category' => new external_value(PARAM_TEXT, 'question category name'),
                'type' => new external_value(PARAM_TEXT, 'question type'),
                'defaultmark' => new external_value(PARAM_INT, 'defaultmark'),
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
        global $DB;

        $params = self::validate_parameters(self::get_question_parameters(), array('question' => $question));
        if ($DB->record_exists('question', array('id' => $question[0]['id'])) == false) {
            print_error('question with given id dosen`t exists', 'error', null, null, 'choose correct question id');
        }

        $questionObj = $DB->get_record('question', array('id' => $question[0]['id']));
        $questionId = new TrueFalseQuestion();
        $questionOptions = $questionId->get_question_options($questionObj);

        return [
            'id' => $question[0]['id'],
            'category' => $questionObj->category,
            'question_name' => $questionObj->name,
            'question_text' => $questionObj->questiontext,
            'general_feed_back' => $questionObj->generalfeedbackformat,
            'default_mark' => $questionObj->defaultmark,
            'question_type' => $questionObj->qtype,
            'created_by' => $questionObj->createdby,
            'question_in_usage' => questions_in_use(array('id' => $question[0]['id'])),
            'answers' => $questionOptions['answers'],
            'hints' => $questionOptions['hints'],
        ];
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
                'answers' => new external_value(PARAM_INT, 'show question answers in question_answers table'),
                'hints' => new external_value(PARAM_INT, 'show questions hints number')
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
        global $DB;
        $params = self::validate_parameters(self::get_question_parameters(), array('question' => $question));

        $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.
        $questionObject = $DB->get_record('question', array('id' => $question[0]['id']));

        if (empty($questionObject)) {
            throw new invalid_parameter_exception('question with given id dosent exist');
        }

        if (questions_in_use($question) == true) {

            return ['message' => "question can not be delete becuse it's in use"];
        }
        $categoryContextId = $DB->get_field('question_categories', 'contextid', array('id' => $questionObject->category));

        $questionRemove = new TrueFalseQuestion();

        $questionRemove->delete_question($questionObject->id, $categoryContextId);

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
                'id' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                'question' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'quizid' => new external_value(PARAM_INT, 'quiz id', VALUE_OPTIONAL),
                            'category' => new external_value(PARAM_INT, 'question category id', VALUE_OPTIONAL),
                            'name' => new external_value(PARAM_TEXT, 'question name', VALUE_OPTIONAL),
                            'questiontext' => new external_value(PARAM_TEXT, 'question text', VALUE_OPTIONAL),
                            'generalfeedback' => new external_value(PARAM_TEXT, 'general feedback', VALUE_DEFAULT, 'generalfeedback'),
                            'questiontextformat' => new external_value(PARAM_INT, 'text format', VALUE_DEFAULT, 1),
                            'defaultmark' => new external_value(PARAM_FLOAT, 'default mark', VALUE_OPTIONAL),
                            'penalty' => new external_value(PARAM_FLOAT, 'penalty', VALUE_OPTIONAL, 1),
                        ),
                        '',
                        VALUE_OPTIONAL
                    ),
                    'edit question data',
                    VALUE_OPTIONAL
                ),
                'answer' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'answer' => new external_value(PARAM_BOOL, 'select RiGHT answer', VALUE_OPTIONAL),
                            'answerformat' => new external_value(PARAM_INT, 'course format option value', VALUE_OPTIONAL, 0),
                            // 'fraction' => new external_value(PARAM_FLOAT, 'question fraction for TRUE answer', VALUE_OPTIONAL, 1.0),
                            'rfeedback' => new external_value(PARAM_RAW, 'feedback for RIGHT answer', VALUE_OPTIONAL),
                            'wfeedback' => new external_value(PARAM_RAW, 'feedback for WRONG answer', VALUE_OPTIONAL),
                        ),
                    ),
                    'additional options/answers for question',
                    VALUE_OPTIONAL
                ),
            ),

        );
    }


    public static function edit_question($id, $question, $answer)
    {
        global $DB;
        $params = self::validate_parameters(self::edit_question_parameters(), array(
            'id' => $id, 'question' => $question, 'answer' => $answer
        ));
        $questionObject = $DB->get_record('question', array('id' => $id));
        $questionArray = (array)$questionObject;

        if (empty($questionObject)) {
            print_error('question with given id dosent exist', 'question', null, null, 'choose correct category id');
        } elseif ($DB->record_exists('question_categories', array('id' => $question[0]['category'])) == false && $question[0]['category'] > 0) {
            print_error('category with given id dosen`t exists in question bank', 'category', null, null, 'choose correct category id');
        }
        $questionParams = array();
        $coloums = $DB->get_columns('question');

        //we check dynamicaly if the input data has paramter, pass it to controller , 
        //otherwise set question data wich is set befor as a paramter
        foreach ($coloums as $col) {
            if (isset($question[0][$col->name])) {
                $questionParams[$col->name] = $question[0][$col->name];
            } else {
                $questionParams[$col->name] = $questionArray[$col->name];
            }
        }
        $questionParams['id'] = $id;
        $questionParams['type'] = $questionObject->qtype;
        $modifyQuestion = new QuestionEditor();
        $questionEdited = $modifyQuestion->questionsEditor($questionParams);
        $modifyAnswer   = new AnswerEditor($questionEdited);
        $modifyAnswer->answerEditor($answer);

        return [
            'id' => $questionEdited['id'],
            'question_name' => $questionEdited['question_name'],
            'question_category' => $questionEdited['question_category'],
            'type' => $questionEdited['type'],
            'message' => 'question edited successfully'
        ];
    }


    public static function edit_question_returns()
    {
        return new external_function_parameters(

            array(
                'id' => new external_value(PARAM_INT, 'question id'),
                'question_name' => new external_value(PARAM_TEXT, 'question name'),
                'question_category' => new external_value(PARAM_TEXT, 'question category name'),
                'type' => new external_value(PARAM_TEXT, 'question type'),
                'message' => new external_value(PARAM_TEXT, 'question created successfully'),
            )
        );
    }
}
