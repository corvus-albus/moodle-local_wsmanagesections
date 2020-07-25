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
require_once($CFG->dirroot . '/course/lib.php');

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
            // If more then one section is created, the sectionnumber for already created ones will increas.
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

    // Functionset for update_sectionnames() **************************************************************************************.

    /**
     * Parameter description for update_sectionnames().
     *
     * @return external_function_parameters.
     */
    public static function update_sectionnames_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sections' => new external_multiple_structure(
                    new external_single_structure(
                        array('sectionnumber' => new external_value(PARAM_INT, 'sectionnumber'),
                            'sectionname' => new external_value(PARAM_TEXT, 'new name of the section')
                        )
                    ), 'sections to rename', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Update sectionnames.
     *
     * This function updates the names of sections.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sections Array of array with sectionnumber and sectionname for each section to be renamed.
     * @return array Array with warnings.
     */
    public static function update_sectionnames($courseid, $sections) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::update_sectionnames_parameters(), array(
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
        $lastsectionnumber = $courseformat->get_last_section_number();

        $warnings = array();
        foreach ($sections as $secname) {
            // Catch any exception while updating course and return as warning to user.
            try {
                // Test if $secname['sectionnumber'] is present in the course.
                if ($secname['sectionnumber'] < 0 or $secname['sectionnumber'] > $lastsectionnumber) {
                    throw new moodle_exception('invalidsectionnumber', 'local_wsmanagesections', '',
                        array('sectionnumber' => $secname['sectionnumber'], 'lastsectionnumber' => $lastsectionnumber));
                }

                // Get the section that belongs to $secname['sectionnumber'].
                foreach ($coursesections as $key => $section) {
                    if ($key == $sectiondata['sectionnumber']) {
                        break;
                    }
                }

                // Rename section.
                $newtitle = clean_param($secname['sectionname'], PARAM_TEXT);
                if (strval($section->name) !== strval($newtitle)) {
                    course_update_section($section->course, $section, array('name' => $newtitle));
                }
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
     * Parameter description for update_sectionnames().
     *
     * @return external_description
     */
    public static function update_sectionnames_returns() {
        return new external_single_structure(
            array(
                'warnings' => new external_warnings()
            )
        );
    }

    // Functionset for update_sectionformatoptions() ******************************************************************************.

    /**
     * Parameter description for update_sectionformatoptions().
     *
     * @return external_function_parameters.
     */
    public static function update_sectionformatoptions_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sections' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'sectionnumber' => new external_value(PARAM_INT, 'sectionnumber'),
                            'sectionformatoptions' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'section format option name'),
                                        'value' => new external_value(PARAM_TEXT, 'section format option value')
                                    )
                                ), 'additional options for particular course format', VALUE_OPTIONAL),
                        )
                    ), 'sections to update', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Update section format options.
     *
     * This function updates the section format options that are specific to the course format.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sections Array of arrays with sectionnumber and sectionformatoptions (name, value).
     * @return array Array with warnings.
     */
    public static function update_sectionformatoptions($courseid, $sections) {
        global $CFG, $DB;

        $warnings = array();

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::update_sectionformatoptions_parameters(), array(
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
        $lastsectionnumber = $courseformat->get_last_section_number();

        foreach ($params['sections'] as $sectiondata) {
            // Catch any exception while updating sections and return as warning to user.
            try {
                // Test if $sectiondata['sectionnumber'] is present in the course.
                if ($sectiondata['sectionnumber'] < 0 or $sectiondata['sectionnumber'] > $lastsectionnumber) {
                    throw new moodle_exception('invalidsectionnumber', 'local_wsmanagesections', '',
                        array('sectionnumber' => $sectiondata['sectionnumber'], 'lastsectionnumber' => $lastsectionnumber));
                }

                // Collect sectionformatoptions in array $data.
                $data = array();
                if (!empty($sectiondata['sectionformatoptions'])) {
                    foreach ($sectiondata['sectionformatoptions'] as $option) {
                        if (isset($option['name']) && isset($option['value'])) {
                            $data[$option['name']] = $option['value'];
                        }
                    }
                }

                // Get the section that belongs to $sectiondata['sectionnumber'].
                foreach ($coursesections as $key => $section) {
                    if ($key == $sectiondata['sectionnumber']) {
                        break;
                    }
                }

                // Update section format options.
                course_update_section($courseid, $section, $data);
            } catch (Exception $e) {
                $warning = array();
                $warning['sectionnumber'] = $sectiondata['sectionnumber'];
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
     * Parameter description for update_sectionformatoptions().
     *
     * @return external_description
     */
    public static function update_sectionformatoptions_returns() {
        return new external_single_structure(
            array(
                'warnings' => new external_warnings()
            )
        );
    }

    // Functionset for get_sectionnames() *****************************************************************************************.

    /**
     * Parameter description for get_sectionnames().
     *
     * @return external_function_parameters.
     */
    public static function get_sectionnames_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sectionnumbers' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'sectionnumber (position of section)')
                            , 'List of sectionnumbers. Wrong numbers will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return names of all sections of the given course.',
                                        VALUE_DEFAULT, array()),
                'sectionids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of section')
                            , 'List of sectionids. Wrong ids will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return names of all sections of the given course.',
                                        VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Get sectionnames.
     *
     * This function gets the names of sections.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sectionnumbers Array of sectionnumbers (int, optional).
     * @param array $sectionids Array of section ids (int, optional).
     * @return array Array with array for each section ('sectionnumber', 'sectionid', 'sectionname').
     */
    public static function get_sectionnames($courseid, $sectionnumbers = [], $sectionids = []) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::get_sectionnames_parameters(), array(
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
                $sectionsinfo[] = array(
                    'sectionnumber' => $section->section,
                    'sectionid' => $section->id,
                    'sectionname' => format_string(get_section_name($course, $section)));
            }
        }

        return $sectionsinfo;
    }

    /**
     * Parameter description for get_sectionnames().
     *
     * @return external_description
     */
    public static function get_sectionnames_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'sectionnumber'  => new external_value(PARAM_INT, 'sectionnumber (position of section)'),
                            'sectionid' => new external_value(PARAM_INT, 'section id'),
                            'sectionname' => new external_value(PARAM_TEXT, 'section name')
                        )
                )
        );
    }

    // Functionset for get_sectionformatoptions() *********************************************************************************.

    /**
     * Parameter description for get_sectionformatoptions().
     *
     * @return external_function_parameters.
     */
    public static function get_sectionformatoptions_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'sectionnumbers' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'sectionnumber (position of section)')
                            , 'List of sectionnumbers. Wrong numbers will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return format options of all sections of the given course.',
                                        VALUE_DEFAULT, array()),
                'sectionids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'id of section')
                            , 'List of sectionids. Wrong ids will be ignored.
                                If list of sectionnumbers and list of sectionids are empty
                                then return format options of all sections of the given course.',
                                        VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Get section format options.
     *
     * This function gets the section format options that are specific to the course format.
     *
     * @param int $courseid Courseid of the belonging course.
     * @param array $sectionnumbers Array of sectionnumbers (int, optional).
     * @param array $sectionids Array of section ids (int, optional).
     * @return array Array with array for each section ('sectionnumber', 'sectionid', Array with format options).
     */
    public static function get_sectionformatoptions($courseid, $sectionnumbers = [], $sectionids = []) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');

        // Validate parameters passed from web service.
        $params = self::validate_parameters(self::get_sectionformatoptions_parameters(), array(
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
                $sectioninfo['sectionnumber'] = $section->section;
                $sectioninfo['sectionid'] = $section->id;
                // Get section format options and write them to sectioninfo.
                $sectionformatoptions = $courseformat->get_format_options($section);
                $sectioninfo['sectionformatoptions'] = array();
                foreach ($sectionformatoptions as $key => $value) {
                    $sectioninfo['sectionformatoptions'][] = array(
                        'name' => $key,
                        'value' => $value);
                }
                $sectionsinfo[] = $sectioninfo;
            }
        }

        return $sectionsinfo;
    }

    /**
     * Parameter description for get_sectionformatoptions().
     *
     * @return external_description
     */
    public static function get_sectionformatoptions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'sectionnumber'  => new external_value(PARAM_INT, 'sectionnumber (position of section)'),
                    'sectionid' => new external_value(PARAM_INT, 'section id'),
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
}
