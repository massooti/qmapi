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
 * Page for editing questions.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/config.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/formslib.php');
// require_once($CFG->wwwroot . '/mod/quiz/locallib.php');
require_once(__DIR__     . '/types/TrueFalseQuestion.php');
require_once(__DIR__     . '/answers/TrueFalseAnswer.php');


const QUESTION_TYPE  =  array(
    'truefalse',
    'match',
    'multichoice',
    'shortanswer',
    'essay',
);

class QuestionBuilder
{
    private static $questiontypes = array();

    public static function get_qtype($qtypename, $mustexist = true)
    {
        global $CFG;
        if (isset(self::$questiontypes[$qtypename])) {
            return self::$questiontypes[$qtypename];
        }
        $file = core_component::get_plugin_directory('qtype', $qtypename) . '/questiontype.php';
        if (!is_readable($file)) {
            if ($mustexist || $qtypename == 'missingtype') {
                throw new coding_exception('Unknown question type ' . $qtypename);
            } else {
                return self::get_qtype('missingtype');
            }
        }
        include_once($file);
        $class = 'qtype_' . $qtypename;
        if (!class_exists($class)) {
            throw new coding_exception("Class {$class} must be defined in {$file}.");
        }
        self::$questiontypes[$qtypename] = new $class();
        return self::$questiontypes[$qtypename];
    }


    /**
     * save or create a question category.
     * this method is use for when a client dosent set question category in parameter, 
     * and check if the given course context has a question category it dosent create it, otherwise
     * it generate a new question category and assign question to it.
     * @param integer $context_id the course context id which is for locating question category.
     * @param string $quizName name of containing the quiz information to save.
     * @return integer question_category_id
     */
    private function categoryMaker(int $context_id, string $quizName): int
    {
        global $DB;
        if ($DB->record_exists('question_categories', array('contextid' => $context_id, 'name' => 'Default for ' .

            $quizName)) == true) {
            $questionCategory = $DB->get_field('question_categories', 'id', array('contextid' => $context_id, 'name' => 'Default for ' . $quizName));
            return  $questionCategory;
        }
        // create question category
        $cat = new stdClass();
        $cat->parent = 0;
        $cat->contextid = $context_id;
        $cat->name = 'top';
        $cat->info = '  ';
        $cat->infoformat = 0;
        $cat->sortorder = 999;
        $cat->stamp = make_unique_id_code();
        $cat->idnumber = null;
        $categoryId = $DB->insert_record("question_categories", $cat);
        /**************/
        $childCat = new stdClass();
        $childCat->parent = $categoryId;
        $childCat->contextid = $context_id;
        $childCat->name = 'Default for ' . $quizName;
        $childCat->info = 'The default category for questions shared in context ' . $quizName . '.';
        $childCat->infoformat = 0;
        $childCat->sortorder = 999;
        $childCat->stamp = make_unique_id_code();
        $childCat->idnumber = null;
        $childCategoryId = $DB->insert_record("question_categories", $childCat);

        $questionCategory = $categoryId;

        // Log the creation of this category.
        $category = new stdClass();
        $category->id = $categoryId;
        $category->contextid = $context_id;
        $event = \core\event\question_category_created::create_from_question_category_instance($category);
        $event->trigger();

        return $questionCategory;
    }

    /**
     * create a question slot instance
     * @param object $quiz containing the quiz information which question is made for.
     * @param object $question containg the question information.
     */
    private function slotMaker(object $quiz, object $question, $maxmark = null): void
    {
        global $DB;

        $slots = $DB->get_records(
            'quiz_slots',
            array('quizid' => $quiz->id),
            'slot',
            'questionid, slot, page, id'
        );
        if (array_key_exists($question->id, $slots)) {
            // $transaction->allow_commit();
            return false;
        }

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }


        // Add the new question slot instance.
        $slot = new stdClass();
        $slot->quizid = $quiz->id;
        $slot->questionid = $question->id;

        if ($maxmark !== null) {
            $slot->maxmark = $maxmark;
        } else {
            $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $question->id));
        }
        if (is_int($quiz->questionsperpage) && $quiz->questionsperpage >= 1) {
            // Adding on a given page.
            $lastslotbefore = 0;
            foreach (array_reverse($slots) as $otherslot) {
                if ($otherslot->page > $quiz->questionsperpage) {
                    $DB->set_field('quiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
                } else {
                    $lastslotbefore = $otherslot->slot;
                    break;
                }
            }
            $slot->slot = $lastslotbefore + 1;
            $slot->page = min($quiz->questionsperpage, $maxpage + 1);

            quiz_update_section_firstslots($quiz->id, 1, max($lastslotbefore, 1));
        } else {
            $lastslot = end($slots);
            if ($lastslot) {
                $slot->slot = $lastslot->slot + 1;
            } else {
                $slot->slot = 1;
            }
            if ($quiz->questionsperpage && $numonlastpage >= $quiz->questionsperpage) {
                $slot->page = $maxpage + 1;
            } else {
                $slot->page = $maxpage;
            }
        }

        $DB->insert_record('quiz_slots', $slot);
    }

    /**
     * create and save question instance.
     * 
     * @param object $parameter is the all the and input wich came from client to the server ,
     * in better meaning, this method create a question for given data paramater.
     * @param integer  $course_id given course id wich question should make for it.
     * @return array question , and some extra information. 
     */

    public function questionMaker($parameter, $course_id)
    {
        global $DB, $USER;
        $qtypeobj = $this->get_qtype($parameter[0]['type']);
        $courseContext = context_course::instance((int)$course_id);
        $cm = null;
        $contexts = new question_edit_contexts($courseContext);

        $quiz = $DB->get_record('quiz', array('id' => $parameter[0]['quizid']));
        //get question category object
        $category = $DB->get_record('question_categories', array('id' => $parameter[0]['question_category']));

        if (empty($category)) {
            $category = $this->categoryMaker($courseContext->id, $quiz->name);
        }


        $question = new stdClass();
        $question->category = $category;
        $question->qtype = $parameter[0]['type'];
        $question->createdby = $USER->id;
        // Check that users are allowed to create this question type at the moment.
        if (!question_bank::qtype_enabled($parameter[0]['type'])) {
            print_error('can not make question due to UNKNOWN ERROR');
        }
        if (!$category = $DB->get_record('question_categories', array('id' => $question->category))) {
            print_error('categorydoesnotexist', 'question');
        }

        // Check permissions
        $question->formoptions = new stdClass();
        $categorycontext = context::instance_by_id($category->contextid);
        $question->contextid = $courseContext->id;
        $addpermission = has_capability('moodle/question:add', $categorycontext);

        $question->formoptions->canedit = question_has_capability_on($question, 'edit');
        $question->formoptions->canmove = (question_has_capability_on($question, 'move') && $addpermission);
        $question->formoptions->cansaveasnew = false;
        $question->formoptions->repeatelements = true;
        $formeditable = true;
        require_capability('moodle/question:add', $categorycontext);

        $question->formoptions->mustbeusable = false;
        $mform = $qtypeobj->create_editing_form('question.php', $question, $category, $contexts, $formeditable);

        $toform = fullclone($question); // send the question object and a few more parameters to the form
        $toform->category  = "{$category->id},{$category->contextid}";
        $toform->scrollpos = 0;
        $toform->makecopy  = 0;
        $toform->courseid  = $course_id;
        $toform->inpopup = 0;
        // $mform->set_data($toform);
        // $fromform = $mform->get_data();


        switch ($parameter[0]['type']) {
            case (QUESTION_TYPE[0]):
                $truefalse = new TrueFalseQuestion();
                $slot = $truefalse->save_question_true_false($question, $parameter);

                $this->slotMaker($quiz, $question);
                return $slot;
                break;
            case (QUESTION_TYPE[1]):
                return false;

            default:
                return "not found";
        }
    }
}

class AnswerBuilder
{

    // /** @var string the answer. */
    // public $answer;

    // /** @var integer one of the FORMAT_... constans. */
    // public $answerformat = FORMAT_PLAIN;

    // /** @var number the fraction this answer is worth. */
    // public $fraction;

    // /** @var string the feedback for this answer. */
    // public $feedback;

    /** @var integer one of the FORMAT_... constans. */
    public $feedbackformat;
    /** @var string one type of question type */
    private $questionType;
    /**object of question given to the answer wich is going to create */
    private $questionObject;
    /**
     * Constructor.
     * @param int $id the answer.
     * @param string $answer the answer.
     * @param number $fraction the fraction this answer is worth.
     * @param string $feedback the feedback for this answer.
     * @param int $feedbackformat the format of the feedback.
     */
    public function __construct(array $question)
    {
        global $DB;

        $this->questionObject = $question['question'];
        $this->questionId = $question['id'];
        $this->questionType = $question['type'];

        // $context = $question;

        // $oldanswers = $this->get_old_answers();
        // We need separate arrays for answers and extra answer data, so no JOINS there.
        $extraanswerfields = $this->extra_answer_fields();

        $isextraanswerfields = is_array($extraanswerfields);
        $extraanswertable = '';
        $oldanswerextras = array();
        if ($isextraanswerfields) {
            $extraanswertable = array_shift($extraanswerfields);
            if (!empty($oldanswers)) {
                $oldanswerextras = $DB->get_records_sql("SELECT * FROM {{$extraanswertable}} WHERE " .
                    'answerid IN (SELECT id FROM {question_answers} WHERE question = ' . $question['id'] . ')');
            }
        }

        return $this;
    }



    // private function get_old_answers()
    // {
    //     global $DB;

    //     return $DB->get_records(
    //         'question_answers',
    //         array('question' => $this->questionId),
    //         'id ASC'
    //     );
    // }

    /**
     * If your question type has a table that extends the question_answers table,
     * make this method return an array wherer the first element is the table name,
     * and the subsequent entries are the column names (apart from id and answerid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */

    public function extra_answer_fields()
    {
        return null;
    }

    /**
     * this method create answer for given question.
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function answerMaker(array $answer)
    {
        global $DB;

        switch ($this->questionType) {
            case (QUESTION_TYPE[0]):
                $truefalse = new TrueFalseAnswer();
                return $truefalse->save_answer_true_false($this->questionObject, $answer);
                break;
            case (QUESTION_TYPE[1]):
                return false;
                break;
        }
    }
}
