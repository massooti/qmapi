<?php

/**
 * External question API
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function create_question_proccess($newparent, $quiz, $newinfoformat = FORMAT_HTML, $idnumber = null, $context_id, $qtype, $maxmark = null, $page = 0, $questionCategory = [])
{
    global $DB, $CFG;
    if (empty($quiz->name)) {
        print_error('categorynamecantbeblank', 'question');
    }

    require_capability('moodle/question:managecategory', context::instance_by_id($context_id));

    if ((string)$idnumber === '') {
        $idnumber = null;
    } else if (!empty($context_id)) {
        // While this check already exists in the form validation, this is a backstop preventing unnecessary errors.
        if ($DB->record_exists(
            'question_categories',
            ['idnumber' => $idnumber, 'contextid' => $context_id]
        )) {
            $idnumber = null;
        }
    }

    $transaction = $DB->start_delegated_transaction();

    if (empty($questionCategory)) {
        // create question category
        $cat = new stdClass();
        $cat->parent = 0;
        $cat->contextid = $context_id;
        $cat->name = 'top';
        $cat->info = '  ';
        $cat->infoformat = $newinfoformat;
        $cat->sortorder = 999;
        $cat->stamp = make_unique_id_code();
        $cat->idnumber = $idnumber;
        $categoryId = $DB->insert_record("question_categories", $cat);
        /**************/
        $childCat = new stdClass();
        $childCat->parent = $categoryId;
        $childCat->contextid = $context_id;
        $childCat->name = 'Default for ' . $quiz->name;
        $childCat->info = 'The default category for questions shared in context ' . $quiz->name . '.';
        $childCat->infoformat = $newinfoformat;
        $childCat->sortorder = 999;
        $childCat->stamp = make_unique_id_code();
        $childCat->idnumber = $idnumber;
        $childCategoryId = $DB->insert_record("question_categories", $childCat);

        $questionCategory = $categoryId;

        // Log the creation of this category.
        $category = new stdClass();
        $category->id = $categoryId;
        $category->contextid = $context_id;
        $event = \core\event\question_category_created::create_from_question_category_instance($category);
        $event->trigger();
    }

    // create question
    $questionObj = new stdClass();
    $questionObj->category = $questionCategory;
    $questionObj->qtype = $qtype;
    $questionObj->name = 'question name';
    $questionObj->questiontext = 'question text';
    $questionObj->questiontextmat = 1;
    $questionObj->generalfeedback = 'generalfeedback';
    $questionObj->generalfeedbackformat = 1;
    $questionObj->defaultmark = 1;
    $questionObj->length = 1;
    $questionObj->stamp = make_unique_id_code();
    $questionObj->version = make_unique_id_code();
    $questionObj->text = 'question text';
    $questionObj->createdby = 2;
    $questionObj->idnumber = null;
    $questionid = $DB->insert_record("question", $questionObj);


    $slots = $DB->get_records(
        'quiz_slots',
        array('quizid' => $quiz->id),
        'slot',
        'questionid, slot, page, id'
    );
    if (array_key_exists($questionid, $slots)) {
        $transaction->allow_commit();
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

    // Add the new question instance.
    $slot = new stdClass();
    $slot->quizid = $quiz->id;
    $slot->questionid = $questionid;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }
    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('quiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

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

    $slotId = $DB->insert_record('quiz_slots', $slot);

    $transaction->allow_commit();


    return [
        'quiz_id' => $quiz->id,
        'question_id' => $questionid,
        'question_category' => $questionCategory,
        'question_category_parent' => $questionCategory ?: $questionCategory - 1,
        'slot_id' => $slotId,
        'message' => 'question created successfully'

    ];
}
