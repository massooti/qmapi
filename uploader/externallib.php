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
require_once($CFG->libdir . "/filelib.php");
class mapi_uploader_external extends external_api
{

    public static function upload_file_section_parameters()
    {
        return new external_function_parameters(
            array(
                'file' => new external_single_structure(
                    array(
                        'content'  => new external_value(PARAM_FILE, 'file content , (sample.jpg, sample.xlsx, etc...)', VALUE_OPTIONAL),
                        'contextid' => new external_value(PARAM_INT, 'context id', VALUE_DEFAULT, 1),
                        'component' => new external_value(PARAM_COMPONENT, 'component', VALUE_OPTIONAL),
                        'filearea'  => new external_value(PARAM_AREA, 'file area', VALUE_OPTIONAL),
                        'itemid'    => new external_value(PARAM_INT, 'associated id', VALUE_OPTIONAL),
                        'contextlevel' => new external_value(PARAM_ALPHA, 'The context level to put the file in,
                                    (block, course, coursecat, system, user, module)', VALUE_DEFAULT, null),
                    ),

                ),
            ),
            'Upload file to given context id'

        );
    }



    public static function upload_file_section($file)
    {
        global $USER, $CFG;

        // $file = self::validate_parameters(self::upload_parameters(), array(
        //     'contextid' => $contextid, 'component' => $component, 'filearea' => $filearea, 'itemid' => $itemid,
        //     'filepath' => $filepath, 'filename' => $filename, 'filecontent' => $filecontent, 'contextlevel' => $contextlevel,
        //     'instanceid' => $instanceid
        // ));

        var_dump($file);
        die();
        $params = self::validate_parameters(self::upload_file_section_parameters(), array('file' => $file));
        $result = array();
        $result['contextid'] = 1;
        return $result['contextid'];
    }

    public static function upload_file_section_returns()
    {

        return new external_single_structure(
            array(
                'contextid' => new external_value(PARAM_INT, 'context id'),

            )
        );
    }
}
