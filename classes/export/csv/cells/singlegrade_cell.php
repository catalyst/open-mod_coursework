<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\grade_judge;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;

/**
 * Class singlegrade_cell
 */
class singlegrade_cell extends cell_base{

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return array|mixed|null|string
     */
    public function get_cell($submission, $student, $stage_identifier){

        $stage_identifier = ($this->coursework->get_max_markers() == 1) ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);

        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);
        if($this->coursework->is_using_rubric()){
            $gradedata = [];
            $this->get_rubric_scores_gradedata($grade, $gradedata); // multiple parts are handled here
        } else {
            $gradedata = (!$grade)? '': $this->get_actual_grade($grade->grade);
        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return array|mixed|string
     * @throws \coding_exception
     */
    public function get_header($stage){

        if ($this->coursework->is_using_rubric()) {
            $strings = [];
            $criterias = $this->coursework->get_rubric_criteria();
            foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                $strings['singlegrade'.$criteria['id']] = $criteria['description'];
                $strings['singlegrade'.$criteria['id'].'comment'] = 'Comment for: '.$criteria['description'] ;
            }
        } else {
            $strings = get_string('grade', 'coursework');
        }
            return $strings;
    }

    public function validate_cell($value, $submissionid, $stage_identifier='', $uploadedgradecells = []) {

        global $PAGE, $DB, $USER;

        if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) || has_capability('mod/coursework:editinitialgrade', $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            $errormsg = '';

            if (!empty($value) && !$this->coursework->is_using_rubric()) {
                $gradejudge = new grade_judge($this->coursework);
                if (!$gradejudge->grade_in_scale($value)){
                    $errormsg = get_string('valuenotincourseworkscale', 'coursework');
                    if (is_numeric($value)) {
                        // if scale is numeric get max allowed scale
                        $errormsg .= ' '. get_string('max_cw_mark', 'coursework').' '. $this->coursework->grade;
                    }
                }
            } else {

                //we won't be processing this line if it has no values, empty wont tell us this as it thinks that an array with
                //keys isnt. We will use array_filter whhich will return all values from the array if this is empty then we have
                //nothing to do

                $arrayvalues = array_filter($value);

                //if there are no values we don't need to do anything
                if (!empty($arrayvalues)) {

                    $i = 0;
                    $s = 0;

                    $criterias = $this->coursework->get_rubric_criteria();

                    foreach ($value as $data) {

                        //check if the value is empty however it can be 0
                        if (empty($data) && $data != 0) {
                            $errormsg .= ' ' . get_string('rubric_grade_cannot_be_empty', 'coursework');
                        }

                        //only check grades fields that will be even numbered
                        if ($i % 2 == 0) {

                            //get the current criteria
                            $criteria = array_shift($criterias);

                            //lets check if the value given is valid for the current rubric criteria
                            if (!$this->value_in_rubric($criteria, $data)) {
                                // if scale is numeric get max allowed scale
                                $errormsg .= ' ' . get_string('rubric_invalid_value', 'coursework') . ' ' . $data;
                            }
                            $s++;
                        }
                        $i++;
                    }
                }

            }

            if (!empty($errormsg))  return $errormsg;

            $dbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));

            $submission = \mod_coursework\models\submission::find($dbrecord);

            //is this submission ready to be graded
            if (!$submission->ready_to_grade() && $submission->get_state() < \mod_coursework\models\submission::FULLY_GRADED) return get_string('submissionnotreadytograde', 'coursework');

            //if you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            //is the current user an assessor at any of this submissions grading stages or do they have administer grades
            if ($this->coursework->allocation_enabled() && !$this->coursework->is_assessor($USER) && !has_capability('mod/coursework:administergrades', $PAGE->context))
                return get_string('nopermissiontogradesubmission', 'coursework');

                        //has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED)  return $submission->get_status_text();

            //has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            if (!$this->coursework->allocation_enabled() && !empty($feedback)) {
                //was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $PAGE->context) && !has_capability('mod/coursework:administergrades', $PAGE->context))
                    return get_string('nopermissiontoeditgrade', 'coursework');

            }

            $ability = new ability(user::find($USER), $this->coursework);

            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            //if (!$ability->can('edit', $feedback))   return get_string('nopermissiontoeditgrade', 'coursework');

            //does a feedback exist for this stage
            if (empty($feedback)) {

                $feedback_params = array(
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stage_identifier,
                );
                $new_feedback = feedback::build($feedback_params);

                //this is a new feedback check it against the new ability checks
                if (!$ability->can('new', $new_feedback))   return get_string('nopermissiontogradesubmission', 'coursework');
            } else {
                //this is a new feedback check it against the edit ability checks
                if (!$ability->can('edit', $feedback))   return get_string('nopermissiontoeditgrade', 'coursework');
            }

        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }

        return true;
    }

    /***
     * Check that the given value is within the values that can be excepted by the given rubric criteria
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that should be checked to see if it is valid
     * @return bool
     */
    function    value_in_rubric($criteria,    $value)         {

        global  $DB;

        $valuefound = false;

        $levels = $criteria['levels'];

        if (is_numeric($value) ) {
            foreach ($levels as $level) {

                if ((int)$level['score'] == (int)$value) {

                    $valuefound = true;
                    break;
                }

            }
        }

        return $valuefound;
    }

    /**
     * Takes the given cells and returns the cells with the singlegrade cell replaced by the rubric headers if the coursework instance
     * makes use of rubrics
     *
     * @param $csv_cells
     *
     */
    function    get_rubrics($coursework, $csv_cells)        {

        if ($coursework->is_using_rubric()) {

            $rubricheaders = [];

            $criterias = $coursework->get_rubric_criteria();

            foreach ($criterias as  $criteria) {
                $rubricheaders[] = $criteria['description'];
                $rubricheaders[] = $criteria['description']." comment";
            }

            //find out the position of singlegrade
            $position = array_search('singlegrade', $csv_cells);
            //get all data from the position of the singlegrade to the length of rubricheaders
           // $csv_cells = array_splice($csv_cells,5, 1, $rubricheaders);

            $start_cells = array_slice($csv_cells,0, $position,true);
            $end_cells = array_slice($csv_cells, $position+1,count($csv_cells),true);

            $cells = array_merge($start_cells, $rubricheaders);

            $cells = array_merge($cells, $end_cells);

        }

        return $cells;
    }

}