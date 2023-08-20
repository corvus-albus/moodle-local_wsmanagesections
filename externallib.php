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
 * Web service library functions
 *
 * @package    local_wsmanagesections
 * @copyright  2020 corvus albus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');

/**
 * Web service API definition.
 *
 * @package local_wsmanagesections
 * @copyright 2020 corvus albus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_wsmanagesections_external extends external_api {

    // Functionset for create_sections() ******************************************************************************************.

    /**
     * Parameter description for create_sections().
     *
     * @return external_function_parameters.
     */
    public static function create_sections_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'position' => new external_value(PARAM_INT, 'Insert section at position; 0 means at the end.'),
                'number' => new external_value(PARAM_INT, 'Number of sections to create. Default is 1.', VALUE_DEFAULT, 1)
            )
        );
    }

    /**
     * Create $number sections at $position.
     *
     * This function creates $number new sections at position $position.
     * If $position = 0 the sections are appended to the end of the course.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param int $position Position the section is created at.
     * @param int $number Number of section to create.
     * @return array Array of arrays with sectionid and sectionnumber for each created section.
     */
    public static function create_sections($courseid, $position, $number) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::create_sections_parameters(), array(
            'courseid' => $courseid,
            'position' => $position,
            'number' => $number));

        if (! ($course = $DB->get_record('course', array('id' => $params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'local_wsmanagesections', '', $courseid);
        }

        require_login($course);
        require_capability('moodle/course:update', context_course::instance($courseid));

        $courseformat = course_get_format($course);

        // Test if courseformat allows sections.
        if (!$courseformat->uses_sections()) {
            throw new moodle_exception('courseformatwithoutsections', 'local_wsmanagesections', '', $courseformat);
        }

        $lastsectionnumber = $courseformat->get_last_section_number();
        $maxsections = $courseformat->get_max_sections();

        // Test if the desired number of section is lower than maxsections of the courseformat.
        $desirednumsections = $lastsectionnumber + $number;
        if ($desirednumsections > $maxsections) {
            throw new moodle_exception('toomanysections', 'local_wsmanagesections', '',
                array('max' => $maxsections, 'desired' => $desirednumsections));
        }

        if ($position > 0) {
            // Inserting sections at any position except in the very end requires capability to move sections.
            require_capability('moodle/course:movesections', context_course::instance($course->id));
        }

        $return = array();
        for ($i = 1; $i <= max($number, 1); $i ++) {
            $section = course_create_section($course, $position);
            // If more then one section is created, the sectionnumber for already created ones will increase.
            $return[] = array('sectionid' => $section->id, 'sectionnumber' => $section->section + $number - $i);
        }

        return  $return;
    }

    /**
     * Parameter description for create_sections().
     *
     * @return external_description
     */
    public static function create_sections_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'sectionid' => new external_value(PARAM_INT, 'section id'),
                            'sectionnumber'  => new external_value(PARAM_INT, 'position of the section'),
                        )
                )
        );
    }

    // Functionset for delete_sections() ******************************************************************************************.

    /**
     * Parameter description for delete_sections().
     *
     * @return external_function_parameters.
     */
    public static function delete_sections_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sectionnumbers' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'sectionnumber (position of section)')
                            , 'List of sectionnumbers. Wrong numbers will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then delete all sections despite of the first.',
                                        VALUE_DEFAULT, array()),
                'sectionids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of section')
                            , 'List of sectionids. Wrong ids will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then delete all sections despite of the first.',
                                        VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Delete sections.
     *
     * This function deletes given sections.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sectionnumbers Array of sectionnumbers (int, optional).
     * @param array $sectionids Array of section ids (int, optional).
     * @return array Array with results.
     */
    public static function delete_sections($courseid, $sectionnumbers = [], $sectionids = []) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::delete_sections_parameters(), array(
            'courseid' => $courseid,
            'sectionnumbers' => $sectionnumbers,
            'sectionids' => $sectionids));

        if (! ($course = $DB->get_record('course', array('id' => $params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'local_wsmanagesections', '', $courseid);
        }

        require_login($course);
        require_capability('moodle/course:update', context_course::instance($courseid));

        $courseformat = course_get_format($course);
        // Test if courseformat allows sections.
        if (!$courseformat->uses_sections()) {
            throw new moodle_exception('courseformatwithoutsections', 'local_wsmanagesections', '', $courseformat);
        }

        $lastsectionnumber = $courseformat->get_last_section_number();
        $coursesections = get_fast_modinfo($course)->get_section_info_all();

        // Collect mentioned sectionnumbers in $secnums.
        $secnums = array();
        // Test if $sectionnumbers are part of $coursesections and collect secnums. Inapt numbers will be ignored.
        if (!empty($sectionnumbers)) {
            foreach ($sectionnumbers as $num) {
                if ($num >= 0 and $num <= $lastsectionnumber) {
                    $secnums[] = $num;
                }
            }
        }
        // Test if $sectionids are part of $coursesections and collect secnums. Inapt ids will be ignored.
        if (!empty($sectionids)) {
            foreach ($coursesections as $section) {
                $coursesecids[] = $section->id;
            }
            foreach ($sectionids as $id) {
                if ($pos = array_search($id, $coursesecids)) {
                    $secnums[] = $pos;
                }
            }
        }
        // Collect all sectionnumbers, if paramters are empty.
        if (empty($sectionnumbers) and empty($sectionids)) {
            $secnums = range(1, $lastsectionnumber);
        }
        $secnums = array_unique($secnums, SORT_NUMERIC);
        sort($secnums, SORT_NUMERIC);

        $results = array();
        // Delete desired sections. Saver to start at the end of the course.
        foreach (array_reverse($coursesections) as $section) {
            if (in_array($section->section, $secnums)) {
                $results[] = array(
                    'id' => $section->id,
                    'number' => $section->section,
                    'name' => format_string(get_section_name($course, $section)),
                    'deleted' => $courseformat->delete_section($section, true));
            }
        }

        return $results;
    }

    /**
     * Parameter description for delete_sections().
     *
     * @return external_description
     */
    public static function delete_sections_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'section id'),
                            'number' => new external_value(PARAM_INT, 'position of the section'),
                            'name' => new external_value(PARAM_TEXT, 'sectionname'),
                             'deleted' => new external_value(PARAM_BOOL, 'deleted (true/false)'),
                        )
                )
        );
    }

    // Functionset for move_section() *********************************************************************************************.

    /**
     * Parameter description for move_section().
     *
     * @return external_function_parameters.
     */
    public static function move_section_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sectionnumber' => new external_value(PARAM_INT, 'number of section'),
                'position' => new external_value(PARAM_INT, 'Move section to position. For position > sectionnumber
                    move to the end of the course.'),
            )
        );
    }

    /**
     * Move a section
     *
     * This function moves a section to position $position.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param int $sectionnumber The sectionnumber of the section to be moved.
     * @param int $position Position the section is moved to.
     * @return null.
     */
    public static function move_section($courseid, $sectionnumber, $position) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::move_section_parameters(), array(
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'position' => $position));

        if (! ($course = $DB->get_record('course', array('id' => $params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'local_wsmanagesections', '', $courseid);
        }

        require_login($course);
        require_capability('moodle/course:update', context_course::instance($courseid));
        require_capability('moodle/course:movesections', context_course::instance($course->id));

        $courseformat = course_get_format($course);
        // Test if courseformat allows sections.
        if (!$courseformat->uses_sections()) {
            throw new moodle_exception('courseformatwithoutsections', 'local_wsmanagesections', '', $courseformat);
        }

        $lastsectionnumber = $courseformat->get_last_section_number();
        // Test if section with $sectionnumber exist.
        if ($sectionnumber < 0 or $sectionnumber > $lastsectionnumber) {
            throw new moodle_exception('invalidsectionnumber', 'local_wsmanagesections', '',
                array('sectionnumber' => $sectionumber, 'lastsectionnumber' => $lastsectionnumber));
        }

        // Move section.
        if (!move_section_to($course, $sectionnumber, $position)) {
            throw new moodle_exception('movesectionerror', 'local_wsmanagesections');
        }

        return  null;
    }

    /**
     * Parameter description for move_section().
     *
     * @return external_description
     */
    public static function move_section_returns() {
        return null;
    }

    // Functionset for get_sections() *********************************************************************************************.

    /**
     * Parameter description for get_sections().
     *
     * @return external_function_parameters.
     */
    public static function get_sections_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sectionnumbers' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'sectionnumber (position of section)')
                            , 'List of sectionnumbers. Wrong numbers will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return infos of all sections of the given course.',
                                        VALUE_DEFAULT, array()),
                'sectionids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of section')
                            , 'List of sectionids. Wrong ids will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return infos of all sections of the given course.',
                                        VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Get sectioninfos.
     *
     * This function returns sectioninfos.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sectionnumbers Array of sectionnumbers (int, optional).
     * @param array $sectionids Array of section ids (int, optional).
     * @return array Array with array for each section.
     */
    public static function get_sections($courseid, $sectionnumbers = [], $sectionids = []) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::get_sections_parameters(), array(
            'courseid' => $courseid,
            'sectionnumbers' => $sectionnumbers,
            'sectionids' => $sectionids));

        if (! ($course = $DB->get_record('course', array('id' => $params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'local_wsmanagesections', '', $courseid);
        }

        require_login($course);
        require_capability('moodle/course:view', context_course::instance($courseid));

        $courseformat = course_get_format($course);
        // Test if courseformat allows sections.
        if (!$courseformat->uses_sections()) {
            throw new moodle_exception('courseformatwithoutsections', 'local_wsmanagesections', '', $courseformat);
        }

        $lastsectionnumber = $courseformat->get_last_section_number();
        $coursesections = get_fast_modinfo($course)->get_section_info_all();

        // Collect mentioned sectionnumbers in $secnums.
        $secnums = array();
        // Test if $sectionnumbers are part of $coursesections and collect secnums. Inapt numbers will be ignored.
        if (!empty($sectionnumbers)) {
            foreach ($sectionnumbers as $num) {
                if ($num >= 0 and $num <= $lastsectionnumber) {
                    $secnums[] = $num;
                }
            }
        }
        // Test if $sectionids are part of $coursesections and collect secnums. Inapt ids will be ignored.
        if (!empty($sectionids)) {
            foreach ($coursesections as $section) {
                $coursesecids[] = $section->id;
            }
            foreach ($sectionids as $id) {
                if ($pos = array_search($id, $coursesecids)) {
                    $secnums[] = $pos;
                }
            }
        }
        // Collect all sectionnumbers, if paramters are empty.
        if (empty($sectionnumbers) and empty($sectionids)) {
            $secnums = range(0, $lastsectionnumber);
        }
        $secnums = array_unique($secnums, SORT_NUMERIC);
        sort($secnums, SORT_NUMERIC);

        // Arrange the requested informations.
        $sectionsinfo = array();
        foreach ($coursesections as $section) {
            if (in_array($section->section, $secnums)) {
                // Collect sectionformatoptions.
                $sectionformatoptions = $courseformat->get_format_options($section);
                $formatoptionslist = array();
                foreach ($sectionformatoptions as $key => $value) {
                    $formatoptionslist[] = array(
                        'name' => $key,
                        'value' => $value);
                }
                // Write sectioninfo to returned array.
                $sectionsinfo[] = array(
                    'sectionnum' => $section->section,
                    'id' => $section->id,
                    'name' => format_string(get_section_name($course, $section)),
                    'summary' => $section->summary,
                    'summaryformat' => $section->summaryformat,
                    'visible' => $section->visible,
                    'uservisible' => $section->uservisible,
                    'availability' => $section->availability,
                    'highlight' => $course->marker == $section->section ? 1 : 0,
                    'sequence' => $section->sequence,
                    'courseformat' => $course->format,
                    'sectionformatoptions' => $formatoptionslist,
                );

            }
        }

        return $sectionsinfo;
    }

    /**
     * Parameter description for get_sections().
     *
     * @return external_description
     */
    public static function get_sections_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'sectionnum'  => new external_value(PARAM_INT, 'sectionnumber (position of section)'),
                            'id' => new external_value(PARAM_INT, 'section id'),
                            'name' => new external_value(PARAM_TEXT, 'section name'),
                            'summary' => new external_value(PARAM_RAW, 'Section description'),
                            'summaryformat' => new external_format_value('summary'),
                            'visible' => new external_value(PARAM_INT, 'is the section visible', VALUE_OPTIONAL),
                            'uservisible' => new external_value(PARAM_BOOL,
                                    'Is the section visible for the user?', VALUE_OPTIONAL),
                            'availability' => new external_value(PARAM_RAW, 'Availability information.', VALUE_OPTIONAL),
                            'highlighted' => new external_value(PARAM_BOOL,
                                    'Is the section marked as highlighted?', VALUE_OPTIONAL),
                            'sequence' => new external_value(PARAM_TEXT, 'sequence of module ids in the section'),
                            'courseformat' => new external_value(PARAM_PLUGIN,
                                    'course format: weeks, topics, social, site,..'),
                            'sectionformatoptions' => new external_multiple_structure(
                                new external_single_structure(
                                    array('name' => new external_value(PARAM_ALPHANUMEXT, 'section format option name'),
                                        'value' => new external_value(PARAM_RAW, 'section format option value')
                                )), 'additional section format options for particular course format', VALUE_OPTIONAL
                            )
                        )
                )
        );
    }

    // Functionset for update_sections() ******************************************************************************************.

    /**
     * Parameter description for update_sections().
     *
     * @return external_function_parameters.
     */
    public static function update_sections_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sections' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'type' => new external_value(PARAM_TEXT,
                                'num/id: identify section by sectionnumber or id. Default to num', VALUE_DEFAULT, 'num'),
                            'section' => new external_value(PARAM_INT, 'depending on type: sectionnumber or sectionid'),
                            'name' => new external_value(PARAM_TEXT, 'new name of the section', VALUE_OPTIONAL),
                            'summary' => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL),
                            'summaryformat' => new external_format_value('summary', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, '1: available to student, 0: not available', VALUE_OPTIONAL),
                            'highlight' => new external_value(PARAM_INT, '1: highlight, 0: remove highlight', VALUE_OPTIONAL),
                            'sectionformatoptions' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'section format option name'),
                                        'value' => new external_value(PARAM_RAW, 'section format option value')
                                    )
                                ), 'additional options for particular course format', VALUE_OPTIONAL),
                        )
                ), 'sections to update', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Update sections.
     *
     * This function updates settings of sections.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sections Array of array with settings for each section to be updated.
     * @return array Array with warnings.
     */
    public static function update_sections($courseid, $sections) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::update_sections_parameters(), array(
            'courseid' => $courseid,
            'sections' => $sections));

        if (! ($course = $DB->get_record('course', array('id' => $params['courseid'])))) {
            throw new moodle_exception('invalidcourseid', 'local_wsmanagesections', '', $courseid);
        }

        require_login($course);
        require_capability('moodle/course:update', context_course::instance($courseid));

        $courseformat = course_get_format($course);
        // Test if courseformat allows sections.
        if (!$courseformat->uses_sections()) {
            throw new moodle_exception('courseformatwithoutsections', 'local_wsmanagesections', '', $courseformat);
        }

        $coursesections = get_fast_modinfo($course)->get_section_info_all();
        $warnings = array();

        foreach ($sections as $sectiondata) {
            // Catch any exception while updating course and return as warning to user.
            try {
                // Get the section that belongs to $secname['sectionnumber'].
                $found = 0;
                foreach ($coursesections as $key => $cs) {
                    if ($sectiondata['type'] == 'id' and $sectiondata['section'] == $cs->id) {
                        $found = 1;
                    } else if ($sectiondata['section'] == $key) {
                        $found = 1;
                    }
                    if ($found == 1) {
                        $section = $cs;
                        break;
                    }
                }
                // Section with the desired number/id not found.
                if ($found == 0) {
                    throw new moodle_exception('sectionnotfound', 'local_wsmanagesections', '', $sectiondata['section']);
                }

                // Sectiondata has mostly the right struture to insert it in course_update_section.
                // Just unset some keys "type", "section", "highlight" and "sectionformatoptions".
                $data = $sectiondata;
                foreach (['type', 'section', 'highlight', 'sectionformatoptions'] as $unset) {
                    unset($data[$unset]);
                }

                 // Set or unset marker if neccessary.
                if (isset($sectiondata['highlight'])) {
                    require_capability('moodle/course:setcurrentsection', context_course::instance($courseid));
                    if ($sectiondata['highlight'] == 1  and $course->marker != strval($section->section)) {
                        course_set_marker($courseid, strval($section->section));
                    } else if ($sectiondata['highlight'] == 0 and $course->marker == $section->section) {
                        course_set_marker($courseid, "0");
                    }
                }

                // Add sectionformatoptions with data['name'] = 'value'.
                if (!empty($sectiondata['sectionformatoptions'])) {
                    foreach ($sectiondata['sectionformatoptions'] as $option) {
                        if (isset($option['name']) && isset($option['value'])) {
                            $data[$option['name']] = $option['value'];
                        }
                    }
                }

                // Update remaining sectionsettings.
                course_update_section($section->course, $section, $data);
            } catch (Exception $e) {
                $warning = array();
                $warning['sectionnumber'] = $sectionnumber;
                $warning['sectionid'] = $section->id;
                if ($e instanceof moodle_exception) {
                    $warning['warningcode'] = $e->errorcode;
                } else {
                    $warning['warningcode'] = $e->getCode();
                }
                $warning['message'] = $e->getMessage();
                $warnings[] = $warning;
            }
        }

        $result = array();
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Parameter description for update_sections().
     *
     * @return external_description
     */
    public static function update_sections_returns() {
        return new external_single_structure(
            array(
                'warnings' => new external_warnings()
            )
        );
    }
}
