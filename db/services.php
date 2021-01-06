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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    localwstemplate
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(
    'qmapi_quiz_create_quiz_section' => array(
        'classname'     => 'mapi_quiz_external',
        'methodname'    => 'create_quiz_section',
        'description'   => 'Create Quiz/Exam for a given course',
        'type'          => 'write',
        'classpath'     => 'local/qmapi/externallib.php',
        'capabilities'  => 'mod/quiz:addinstance', 'moodle/course:create', 'moodle/course:visibility', 'mod/quiz:view', 'mod/quiz:addinstance', 'mod/quiz:attempt', 'mod/quiz:manage', 'mod/quiz:viewreports'
    ),
    'qmapi_quiz_edit_quiz_section' => array(
        'classname'     => 'mapi_quiz_external',
        'methodname'    => 'edit_quiz_section',
        'description'   => 'Edit Quiz/Exam for given course',
        'type'          => 'write',
        'classpath'     => 'local/qmapi/externallib.php',
        'capabilities'  => 'mod/quiz:addinstance', 'moodle/course:create', 'moodle/course:visibility', 'mod/quiz:view',  'mod/quiz:attempt', 'mod/quiz:manage', 'mod/quiz:viewreports'
    ),
    'qmapi_quiz_delete_quiz_section' => array(
        'classname'     => 'mapi_quiz_external',
        'methodname'    => 'delete_quiz_section',
        'description'   => 'Delete Quiz/Exam for a given course',
        'type'          => 'write',
        'classpath'     => 'local/qmapi/externallib.php',
        'capabilities'  => 'mod/quiz:addinstance', 'moodle/course:create', 'moodle/course:visibility', 'mod/quiz:view', 'mod/quiz:addinstance', 'mod/quiz:attempt', 'mod/quiz:manage', 'mod/quiz:viewreports'
    ),

    'qmapi_question_create_question' => array(
        'classname' => 'mapi_question_external',
        'methodname' => 'create_question',
        'description' => 'create question for given quiz',
        'type' => 'write',
        'classpath'     => 'local/qmapi/questions/externallib.php',
        'capabilities'  => 'moodle/question:add', 'moodle/question:editall', 'moodle/question:viewall', 'moodle/course:visibility', 'mod/quiz:view', 'mod/quiz:manage', 'mod/quiz:viewreports'
    ),
);

//We define the services to install as pre-build services. A pre-build service is not editable by administrator.
// $services = array(
//     'quizes' => array(
//         'functions' => array('mapi_quiz_create_quiz_section'), //web service function name
//         'requiredcapability' => 'moodle/course:create', 'moodle/course:visibility', 'mod/quiz:view', 'mod/quiz:addinstance', 'mod/quiz:attempt', 'mod/quiz:manage', 'mod/quiz:viewreports',
//         'restrictedusers' => 1,
//         'enabled' => 0, //used only when installing the services
//     ),
//     'question' => array(
//         'functions' => array('mapi_question_create_question'), //web service function name
//         'requiredcapability' => 'moodle/question:add', 'moodle/course:create', 'moodle/course:visibility', 'mod/quiz:view', 'mod/quiz:addinstance', 'mod/quiz:attempt', 'mod/quiz:manage', 'mod/quiz:viewreports',
//         'restrictedusers' => 1,
//         'enabled' => 0, //used only when installing the services
//     ),

// );
