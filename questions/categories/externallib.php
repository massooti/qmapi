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

use core\session\exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/datalib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

/**
 * Question external functions
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.1
 */
class mapi_question_category_external extends external_api
{

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @author Masoud Zaeem
     * @action
     */
    public static function create_question_category_parameters()
    {
        return new external_function_parameters(
            array(
                'category' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'quiz id', VALUE_REQUIRED),
                            'name' => new external_value(PARAM_TEXT, 'question category name', VALUE_REQUIRED),
                            'info' => new external_value(PARAM_RAW, 'question category info', VALUE_OPTIONAL),
                            'parent_category' => new external_value(PARAM_INT, 'question category parent id', VALUE_OPTIONAL, VALUE_DEFAULT),
                            'idnumber' => new external_value(PARAM_INT, 'question category id number', VALUE_OPTIONAL, VALUE_DEFAULT, NULL),

                        )
                    ),
                    'create question category for given course'
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
    public static function create_question_category($category)
    {
        global $CFG, $DB;

        $params = self::validate_parameters(self::create_question_category_parameters(), array('category' => $category));
        $transaction = $DB->start_delegated_transaction();

        $contextid = context_course::instance($category[0]['courseid']);

        if (empty($category[0]['name'])) {
            throw new invalid_parameter_exception('question category name cannot be null');
        } elseif ($DB->record_exists('question_categories', array('id' => $category[0]['parent_category'])) == false) {
            throw new invalid_parameter_exception('parent category not found');
        }

        $question_category = new stdClass();
        $question_category->name = $category[0]['name'];
        $question_category->contextid = $contextid->id;
        $question_category->info = $category[0]['info'];
        $question_category->infoformat = FORMAT_HTML;
        $question_category->stamp = make_unique_id_code();
        $question_category->parent = $category[0]['parent_category'];
        $question_category->sortorder = 999;
        $question_category->idnumber = null;
        $categoryObj = $DB->insert_record("question_categories", $question_category);

        $transaction->allow_commit();

        $result = array();
        $result['context_name']    = $contextid->get_context_name();
        $result['category_id']     = $categoryObj;
        $result['category_parent'] = $DB->get_field('question_categories', 'name', ['id' => $category[0]['parent_category']]);
        $result['category_name']   = $category[0]['name'];
        $result['message']         = 'question category created successfully';

        return $result;
    }

    /**
     * @return external_single_structure
     * @author Masoud Zaeem
     * @action
     */
    public
    static function create_question_category_returns()
    {

        return new external_single_structure(
            array(
                'context_name' => new external_value(PARAM_TEXT, 'context name'),
                'category_id' => new external_value(PARAM_INT, 'question category id'),
                'category_parent' => new external_value(PARAM_TEXT, 'question category parent'),
                'category_name' => new external_value(PARAM_TEXT, 'question category name'),
                'message' => new external_value(PARAM_TEXT, 'question category created successfully'),
            )
        );
    }


    public static function edit_question_category_parameters()
    {
        return new external_function_parameters(
            array(
                'category' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'question category id', VALUE_REQUIRED),
                            'name' => new external_value(PARAM_TEXT, 'question category name', VALUE_OPTIONAL),
                            'info' => new external_value(PARAM_RAW, 'question category info', VALUE_OPTIONAL),
                            'idnumber' => new external_value(PARAM_INT, 'question category id number', VALUE_OPTIONAL, VALUE_DEFAULT, NULL),

                        )
                    ),
                    'edit question category for given id'
                )
            )
        );
    }

    public static function edit_question_category($category)
    {
        global $DB;

        $params = self::validate_parameters(self::edit_question_category_parameters(), array('category' => $category));
        $transaction = $DB->start_delegated_transaction();

        if ($DB->record_exists('question_categories', array('id' => $category[0]['id'])) == false) {
            throw new invalid_parameter_exception('question category with given id doesnt exist.');
        }
        $coloums = $DB->get_columns('question_categories');
        $parameters = [];
        foreach ($coloums as $col) {

            if (isset($category[0][$col->name])) {
                $parameters[$col->name] = $category[0][$col->name];
            }
        }

        $DB->update_record_raw('question_categories', $parameters);

        $transaction->allow_commit();

        $result['question_category_id'] = $category[0]['id'];
        $result['message'] = 'question category updated successfully.';

        return $result;
    }


    public static function edit_question_category_returns()
    {
        return new external_single_structure(
            array(
                'question_category_id' => new external_value(PARAM_INT, 'question category id'),
                'message' => new external_value(PARAM_TEXT, 'question category response'),
            )
        );
    }



    public static function get_question_category_parameters()
    {
        return new external_function_parameters(
            array(
                'category' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'question category id', VALUE_REQUIRED),
                        )
                    ),
                    "get question category and it's subcategories for given id"
                )
            )
        );
    }



    public static function get_question_category($category)
    {
        global $DB;

        $params = self::validate_parameters(self::edit_question_category_parameters(), array('category' => $category));
        $transaction = $DB->start_delegated_transaction();

        if ($DB->record_exists('question_categories', array('id' => $category[0]['id'])) == false) {
            throw new invalid_parameter_exception('question category with given id doesnt exist.');
        }

        $questionCategory = $DB->get_record('question_categories', array('id' => $category[0]['id']));

        $questions = $DB->get_records('question', array('category' => $category[0]['id']));


        //TODO should implement for showing questions in  moodle response 
        $question = [];
        foreach ($questions as $item) {

            $question = $item;
        }
        // print(json_decode($question));
        // die();
        $result = [];
        $result['id'] = $category[0]['id'];
        $result['name'] = $questionCategory->name;
        $result['info'] = $questionCategory->info;
        $result['questions'] = count($questions);
        // $result['questions'] = $question;

        $transaction->allow_commit();

        return $result;
    }

    public static function get_question_category_returns()
    {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'question category id'),
                'name' => new external_value(PARAM_TEXT, 'question category name'),
                'info' => new external_value(PARAM_TEXT, 'question category info'),
                'questions' => new external_value(PARAM_INT, 'list of questions related to the question category')
            )
        );
    }

    public static function delete_question_category_parameters()
    {
        return new external_function_parameters(
            array(
                'category' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'question category id', VALUE_REQUIRED),
                            'primary' => new external_value(PARAM_INT, 'question category name', VALUE_OPTIONAL)
                        )
                    ),
                    'delete question category for given id'
                )
            )
        );
    }



    public static function delete_question_category($category)
    {
        global $CFG, $DB;

        $params = self::validate_parameters(self::delete_question_category_parameters(), array('category' => $category));
        $transaction = $DB->start_delegated_transaction();
        $questions = $DB->get_records('question', array('category' => $category[0]['id']));
        $question_category_primary_name = $DB->get_field('question_categories', 'name', array('id' => $category[0]['primary']));

        if (count($questions) > 0) {

            if ($category[0]['primary'] == '' || $DB->record_exists('question_categories', array('id' => $category[0]['primary'])) == false) {
                throw new invalid_parameter_exception('please set a valid primary question category');
            }
            foreach ($questions as $key => $question) {

                $question->category = $category[0]['primary'];

                $DB->update_record_raw('question', array('id' => $question->id,  'category' => $question->category));
            }

            $result['message'] = count($questions) . ' questions moved to ' . $question_category_primary_name . ' successfully.';
        } elseif ($DB->record_exists('question_categories', array('id' => $category[0]['id'])) == false) {

            throw new invalid_parameter_exception('question category with given id does not exist');
        } elseif (count($questions) == 0) {
            $DB->delete_records('question_categories', array('id' => $category[0]['id']));
            $result['message'] = 'question category deleted successfully.';
        }


        $transaction->allow_commit();

        return $result;
    }


    public static function delete_question_category_returns()
    {
        return new external_single_structure(
            array(
                'message' => new external_value(PARAM_TEXT, 'question category response'),
            )
        );
    }
}
