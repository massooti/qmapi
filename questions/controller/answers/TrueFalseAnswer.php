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
 * Question type class for the true-false question type.
 *
 * @package    qtype
 * @subpackage truefalse
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/dml/moodle_database.php');


/**
 * The true-false question type class.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class TrueFalseAnswer extends question_type
{
    /**
     * Save the file belonging to one text field.
     *
     * @param array $field the data from the form (or from import). This will
     *      normally have come from the formslib editor element, so it will be an
     *      array with keys 'text', 'format' and 'itemid'. However, when we are
     *      importing, it will be an array with keys 'text', 'format' and 'files'
     * @param object $context the context the question is in.
     * @param string $component indentifies the file area question.
     * @param string $filearea indentifies the file area questiontext,
     *      generalfeedback, answerfeedback, etc.
     * @param int $itemid identifies the file area.
     *
     * @return string the text for this field, after files have been processed.
     */
    protected function import_or_save_files($field, $context, $component, $filearea, $itemid)
    {
        if (!empty($field['itemid'])) {
            // This is the normal case. We are safing the questions editing form.
            return file_save_draft_area_files(
                $field['itemid'],
                $context->id,
                $component,
                $filearea,
                $itemid,
                $this->fileoptions,
                trim($field['text'])
            );
        } else if (!empty($field['files'])) {
            // This is the case when we are doing an import.
            foreach ($field['files'] as $file) {
                $this->import_file($context, $component,  $filearea, $itemid, $file);
            }
        }
        return trim($field['text']);
    }




    public function save_answer_true_false($question, $answerOpt)
    {
        global $DB;

        // $transaction = new moodle_transaction($DB);
        // $transaction = $DB->start_delegated_transaction(); //If an exception is thrown in the below code, all DB queries in this code will be rollback.
        $context = $question->contextid;
        // Fetch old answer ids so that we can reuse them.
        $oldanswers = $DB->get_records(
            'question_answers',
            array('question' => $question->id),
            'id ASC'
        );

        // Save the true answer - update an existing answer if possible.
        $answer = array_shift($oldanswers);
        if (!$answer) {
            $true_answer = new stdClass();
            $true_answer->question = $question->id;
            $true_answer->answer = $answerOpt[0]['answer'];
            $true_answer->feedback = '';
            $true_answer->id = $DB->insert_record('question_answers', $true_answer);
        }

        $true_answer->answer   = get_string($answerOpt[0]['answer'], 'qtype_truefalse');
        $true_answer->fraction = 1;
        $true_answer->feedback = $answerOpt[0]['tfeedback'];/* $this->import_or_save_files(
            $answerOpt[0]['tfeedback'],
            $context,
            'question',
            'answerfeedback',
            $true_answer->id
        ); */
        $true_answer->feedbackformat = $question->generalfeedbackformat;

        $DB->update_record('question_answers', $true_answer);
        $trueid =  $true_answer->id;
        // Save the false answer - update an existing answer if possible.
        $answer = array_shift($oldanswers);
        if (!$answer) {
            $fasle_answer = new stdClass();
            $fasle_answer->question = $question->id;
            $fasle_answer->answer = '';
            $fasle_answer->feedback = $answerOpt[0]['ffeedback'];
            $fasle_answer->id = $DB->insert_record('question_answers', $fasle_answer);
        }

        $fasle_answer->answer   = get_string('false', 'qtype_truefalse');
        $fasle_answer->fraction = 1 - (int)$question->defaultmark;
        $fasle_answer->feedback = $answerOpt[0]['ffeedback'];/* $this->import_or_save_files(
            $answerOpt[0]['ffeedback'],
            $context,
            'question',
            'answerfeedback',
            $fasle_answer->id */
        // );
        $fasle_answer->feedbackformat = $question->generalfeedbackformat;

        // $DB->execute('UPDATE question_answers SET', )
        $DB->update_record('question_answers', $fasle_answer);
        $falseid = $fasle_answer->id;


        // Delete any left over old answer records.
        $fs = get_file_storage();
        foreach ($oldanswers as $oldanswer) {
            $fs->delete_area_files($context->id, 'question', 'answerfeedback', $oldanswer->id);
            $DB->delete_records('question_answers', array('id' => $oldanswer->id));
        }

        // Save question options in question_truefalse table.
        if ($options = $DB->get_record('question_truefalse', array('question' => $question->id))) {

            // No need to do anything, since the answer IDs won't have changed
            // But we'll do it anyway, just for robustness.
            $options->trueanswer  = $trueid;
            $options->falseanswer = $falseid;
            $DB->update_record('question_truefalse', $options);
        } else {
            $options = new stdClass();
            $options->question    = $question->id;
            $options->trueanswer  = $trueid;
            $options->falseanswer = $falseid;
            $DB->insert_record('question_truefalse', $options);
        }
        // $transaction->allow_commit();

        // $this->save_hints($question);

        return $options;
    }

    /**
     * Loads the question type specific options for the question.
     **/
    public function get_question_options($question)
    {
        global $DB, $OUTPUT;
        parent::get_question_options($question);
        // Get additional information from database
        // and attach it to the question object.
        if (!$question->options = $DB->get_record(
            'question_truefalse',
            array('question' => $question->id)
        )) {
            echo $OUTPUT->notification('Error: Missing question options!');
            return false;
        }
        // Load the answers.
        if (!$question->options->answers = $DB->get_records(
            'question_answers',
            array('question' =>  $question->id),
            'id ASC'
        )) {
            echo $OUTPUT->notification('Error: Missing question answers for truefalse question ' .
                $question->id . '!');
            return false;
        }

        return true;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata)
    {
        parent::initialise_question_instance($question, $questiondata);
        $answers = $questiondata->options->answers;
        if ($answers[$questiondata->options->trueanswer]->fraction > 0.99) {
            $question->rightanswer = true;
        } else {
            $question->rightanswer = false;
        }
        $question->truefeedback =  $answers[$questiondata->options->trueanswer]->feedback;
        $question->falsefeedback = $answers[$questiondata->options->falseanswer]->feedback;
        $question->truefeedbackformat =
            $answers[$questiondata->options->trueanswer]->feedbackformat;
        $question->falsefeedbackformat =
            $answers[$questiondata->options->falseanswer]->feedbackformat;
        $question->trueanswerid =  $questiondata->options->trueanswer;
        $question->falseanswerid = $questiondata->options->falseanswer;
    }

    public function delete_question($questionid, $contextid)
    {
        global $DB;
        $DB->delete_records('question_truefalse', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function move_files($questionid, $oldcontextid, $newcontextid)
    {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid)
    {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata)
    {
        return 0.5;
    }

    public function get_possible_responses($questiondata)
    {
        return array(
            $questiondata->id => array(
                0 => new question_possible_response(
                    get_string('false', 'qtype_truefalse'),
                    $questiondata->options->answers[$questiondata->options->falseanswer]->fraction
                ),
                1 => new question_possible_response(
                    get_string('true', 'qtype_truefalse'),
                    $questiondata->options->answers[$questiondata->options->trueanswer]->fraction
                ),
                null => question_possible_response::no_response()
            )
        );
    }
}
