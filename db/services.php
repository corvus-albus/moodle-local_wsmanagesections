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
 * Web service definitions for local_wsmanagesections
 *
 * @package    local_wsmanagesections
 * @copyright  2020 corvus albus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_wsmanagesections_create_sections' => array(
         'classname' => 'local_wsmanagesections_external',
         'methodname' => 'create_sections',
         'classpath' => 'local/wsmanagesections/externallib.php',
         'description' => 'Create sections at given position.',
         'type' => 'create',
         'capabilities' => 'moodle/course:update, moodle/course:movesections',
    ),
    'local_wsmanagesections_delete_sections' => array(
         'classname' => 'local_wsmanagesections_external',
         'methodname' => 'delete_sections',
         'classpath' => 'local/wsmanagesections/externallib.php',
         'description' => 'Delete sections.',
         'type' => 'create',
         'capabilities' => 'moodle/course:update',
    ),
    'local_wsmanagesections_move_section' => array(
         'classname' => 'local_wsmanagesections_external',
         'methodname' => 'move_section',
         'classpath' => 'local/wsmanagesections/externallib.php',
         'description' => 'Move section to given position.',
         'type' => 'create',
         'capabilities' => 'moodle/course:update, moodle/course:movesections',
    ),
    'local_wsmanagesections_get_sections' => array(
         'classname' => 'local_wsmanagesections_external',
         'methodname' => 'get_sections',
         'classpath' => 'local/wsmanagesections/externallib.php',
         'description' => 'Get settings of sections.',
         'type' => 'create',
         'capabilities' => 'moodle/course:view',
    ),
    'local_wsmanagesections_update_sections' => array(
         'classname' => 'local_wsmanagesections_external',
         'methodname' => 'update_sections',
         'classpath' => 'local/wsmanagesections/externallib.php',
         'description' => 'Update sections.',
         'type' => 'create',
         'capabilities' => 'moodle/course:update',
    ),
);
