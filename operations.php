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
 * Print an overview of groupings & group membership
 *
 * @author      Valery Fremaux valery.fremaux@gmail.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package     local_groupcopy
 * @category    local
 */
require('../../config.php');
require_once($CFG->dirroot.'/local/groupcopy/groupoperationsform.php');

$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('group-index');

$courseid = required_param('id', PARAM_INT);

$returnurl = new moodle_url('/group/index.php', array('id' => $courseid));
$thisurl = new moodle_url('/local/groupcopy/operations.php', array('id' => $courseid));
$nexturl = new moodle_url('/local/groupcopy/operations_perform.php', array('id' => $courseid));

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

// Security.

// Make sure that the user has permissions to manage groups.
require_course_login($course, true);

$context = context_course::instance($courseid);
require_capability('moodle/course:managegroups', $context);

$strgroups = get_string('groups');
$strparticipants = get_string('participants');

$syscontext = context_system::instance();

// If we're not a course creator , we can only import from our own courses.
if (has_capability('moodle/course:create', $syscontext)) {
    $creator = true;
}

/*
 * get all course contexts the user has managegroups capability on
 * those courses are legitimate as source for group structure copying.
 * @see
 */

$tcourseids = array();

if ($teachers = get_user_capability_course('moodle/course:managegroups')) {
    foreach ($teachers as $teacher) {
        if ($teacher->id != $courseid && $teacher->id != SITEID) {
            $tcourseids[] = $teacher->id;
        }
    }
}

$taughtcourses = array();
if (!empty($tcourseids)) {
    $taughtcourses = $DB->get_records_list('course', 'id', $tcourseids, 'sortorder');
}

if (!empty($creator)) {
    $catcourses = get_courses($course->category, 'c.sortorder ASC', 'c.id, c.fullname');
} else {
    $catcourses = array();
}

$options = array();
foreach ($taughtcourses as $tcourse) {
    if ($tcourse->id != $course->id && $tcourse->id != SITEID) {
        $options[$tcourse->id] = format_string($tcourse->fullname);
    }
}

$params = array('options' => $options, 'courseid' => $course->id, 'text' => get_string('coursestaught', 'local_groupcopy'));
$mform1 = new Group_Operations_Setup1_Form($nexturl, $params);

if ($data = $mform1->get_data()) {
    $params = array('id' => $data->id, 'fromcourse' => $data->fromcourse);
    redirect(new moodle_url('/local/groupcopy/operations_perform.php', $params));
}

$url = new moodle_url('/local/groupcopy/operations.php');

// Print the page and form.
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading(get_string('pluginname', 'local_groupcopy'));
$PAGE->set_title(get_string('pluginname', 'local_groupcopy'));

$strparticipants = get_string('participants');
$strheading = get_string('pluginname', 'local_groupcopy');
$strgroups = get_string('groups');
$PAGE->navbar->add($strparticipants, new moodle_url('/user/index.php', array('id' => $courseid)));
$PAGE->navbar->add($strgroups);
$PAGE->navbar->add($strheading);

// Print header.
echo $OUTPUT->header();

$currenttab = 'operations';
require_once($CFG->dirroot.'/group/tabs.php');

echo $OUTPUT->heading(get_string('importallgroupstructure', 'local_groupcopy'));

$mform1->display();

echo $OUTPUT->footer();
