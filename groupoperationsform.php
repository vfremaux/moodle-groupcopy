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
 * @author      Valery Fremaux valery.fremaux@gmail.com
 * @version     0.0.1
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package     local_groupcopy
 * @category    local
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class Group_Operations_Setup1_Form extends moodleform {

    public function definition() {
        global $CFG;

        $mform =& $this->_form;
        $text = $this->_customdata['text'];
        $options = $this->_customdata['options'];
        $courseid = $this->_customdata['courseid'];

        $mform->addElement('header', 'general', ''); // Fill in the data depending on page params.

        // Later using set_data.
        $mform->addElement('select', 'fromcourse', $text, $options);

        // Buttons.
        $submit_string = get_string('importfromthiscourse', 'local_groupcopy');
        $this->add_action_buttons(false, $submit_string);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setConstants(array('id' => $courseid));

    }

    public function validation($data, $files) {
        return parent::validation($data, $files);
    }
}

class Group_Operations_Setup2_Form extends moodleform {

    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $roleoptions = $this->_customdata['roles'];
        $groupoptions = $this->_customdata['groups'];
        $sourcecourseid = $this->_customdata['fromcourseid']; // Target course.
        $courseid = $this->_customdata['courseid']; // Origin course.

        $mform->addElement('header', 'groups', get_string('groupcopychoosegroups', 'local_groupcopy')); //fill in the data depending on page params

        // Later using set_data.
        foreach ($groupoptions as $gid => $group) {
            $mform->addElement('checkbox', 'groupids'.$gid, $group->name, '');
            $mform->setDefault('groupids'.$gid, 1);
        }

        $mform->addElement('header', 'roles', get_string('groupcopychooseroles', 'local_groupcopy')); //fill in the data depending on page params

        // Later using set_data.
        foreach ($roleoptions as $rid => $role) {
            $mform->addElement('checkbox', 'roleids'.$rid, $role->localname, '');
            $mform->setDefault('roleids'.$rid, 1);
        }

        $mform->addElement('header', 'modes', get_string('groupcopyoptions', 'local_groupcopy'));

        $ismeta = $DB->get_record('enrol', array('id' => $courseid, 'enrol' => 'meta'));
        if (!$ismeta) {
            $strpreclean = get_string('preclean', 'local_groupcopy');
            $strprecleanadv = get_string('precleanadv', 'local_groupcopy');
            $mform->addElement('checkbox', 'deleteallusersbeforecopy', $strpreclean, $strprecleanadv);
        }

        $strpreclean = get_string('copygroupings', 'local_groupcopy');
        $mform->addElement('checkbox', 'copygroupings', $strpreclean, '');

        // Buttons.
        $submit_string = get_string('importgroupsandusers', 'local_groupcopy');
        $this->add_action_buttons(true, $submit_string);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setConstants(array('id' => $courseid));

        $mform->addElement('hidden', 'fromcourse');
        $mform->setType('fromcourse', PARAM_INT);
        $mform->setConstants(array('fromcourse' => $sourcecourseid));
    }

    public function validation($data, $files) {
        return parent::validation($data, $files);
    }
}