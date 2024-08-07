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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\export\csv\cells;

/**
 * Class submissionfileid_cell
 */
class submissionfileid_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier) {
        return  $this->coursework->get_username_hash($submission->allocatableid);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {
        return  get_string('submissionfileid', 'coursework');
    }

    public function validate_cell($value, $submissionid, $stage_dentifier='', $uploadedgradecells = []) {
        global $DB;

        if (empty($value)) {
            return 'No submission hash value entered';
        }

        $subdbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));

        $submission = \mod_coursework\models\submission::find($subdbrecord);

        $hash = $this->coursework->get_username_hash($submission->allocatableid);

        return ($value == $hash) ? true : get_string('submissionnotfound', 'coursework');
    }

}
