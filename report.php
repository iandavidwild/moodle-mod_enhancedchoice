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
 * @package    mod_enhancedchoice
 * @copyright  2013 Ian David Wild {@link http://heavy-horse.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id', PARAM_INT);   //moduleid
    $format     = optional_param('format', ENHANCEDCHOICE_PUBLISH_NAMES, PARAM_INT);
    $download   = optional_param('download', '', PARAM_ALPHA);
    $action     = optional_param('action', '', PARAM_ALPHA);
    $attemptids = optional_param_array('attemptid', array(), PARAM_INT); //get array of responses to delete.

    $url = new moodle_url('/mod/enhancedchoice/report.php', array('id'=>$id));
    if ($format !== ENHANCEDCHOICE_PUBLISH_NAMES) {
        $url->param('format', $format);
    }
    if ($download !== '') {
        $url->param('download', $download);
    }
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('enhancedchoice', $id)) {
        print_error("invalidcoursemodule");
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error("coursemisconf");
    }

    require_login($course, false, $cm);

    $context = context_module::instance($cm->id);

    require_capability('mod/enhancedchoice:readresponses', $context);

    if (!$choice = enhancedchoice_get_choice($cm->instance)) {
        print_error('invalidcoursemodule');
    }

    $strchoice = get_string("modulename", "enhancedchoice");
    $strchoices = get_string("modulenameplural", "enhancedchoice");
    $strresponses = get_string("responses", "enhancedchoice");

    add_to_log($course->id, "enhancedchoice", "report", "report.php?id=$cm->id", "$choice->id",$cm->id);

    if (data_submitted() && $action == 'delete' && has_capability('mod/enhancedchoice:deleteresponses',$context) && confirm_sesskey()) {
        enhancedchoice_delete_responses($attemptids, $choice, $cm, $course); //delete responses.
        redirect("report.php?id=$cm->id");
    }

    if (!$download) {
        $PAGE->navbar->add($strresponses);
        $PAGE->set_title(format_string($choice->name).": $strresponses");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        /// Check to see if groups are being used in this choice
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            groups_get_activity_group($cm, true);
            groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/enhancedchoice/report.php?id='.$id);
        }
    } else {
        $groupmode = groups_get_activity_groupmode($cm);
    }
    $users = enhancedchoice_get_response_data($choice, $cm, $groupmode);

    if ($download == "ods" && has_capability('mod/enhancedchoice:downloadresponses', $context)) {
        require_once("$CFG->libdir/odslib.class.php");

    /// Calculate file name
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($choice->name,true))).'.ods';
    /// Creating a workbook
        $workbook = new MoodleODSWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls = $workbook->add_worksheet($strresponses);

    /// Print names of all the fields
        $myxls->write_string(0,0,get_string("lastname"));
        $myxls->write_string(0,1,get_string("firstname"));
        $myxls->write_string(0,2,get_string("idnumber"));
        $myxls->write_string(0,3,get_string("group"));
        $myxls->write_string(0,4,get_string("choice","enhancedchoice"));

    /// generate the data for the body of the spreadsheet
        $i=0;
        $row=1;
        if ($users) {
            foreach ($users as $option => $userid) {
                $option_text = choice_get_option_text($choice, $option);
                foreach($userid as $user) {
                    $myxls->write_string($row,0,$user->lastname);
                    $myxls->write_string($row,1,$user->firstname);
                    $studentid=(!empty($user->idnumber) ? $user->idnumber : " ");
                    $myxls->write_string($row,2,$studentid);
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2. $ug->name;
                        }
                    }
                    $myxls->write_string($row,3,$ug2);

                    if (isset($option_text)) {
                        $myxls->write_string($row,4,format_string($option_text,true));
                    }
                    $row++;
                    $pos=4;
                }
            }
        }
        /// Close the workbook
        $workbook->close();

        exit;
    }

    //print spreadsheet if one is asked for:
    if ($download == "xls" && has_capability('mod/enhancedchoice:downloadresponses', $context)) {
        require_once("$CFG->libdir/excellib.class.php");

    /// Calculate file name
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($choice->name,true))).'.xls';
    /// Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls = $workbook->add_worksheet($strresponses);

    /// Print names of all the fields
        $myxls->write_string(0,0,get_string("lastname"));
        $myxls->write_string(0,1,get_string("firstname"));
        $myxls->write_string(0,2,get_string("idnumber"));
        $myxls->write_string(0,3,get_string("group"));
        $myxls->write_string(0,4,get_string("choice","enhancedchoice"));


    /// generate the data for the body of the spreadsheet
        $i=0;
        $row=1;
        if ($users) {
            foreach ($users as $option => $userid) {
                $option_text = choice_get_option_text($choice, $option);
                foreach($userid as $user) {
                    $myxls->write_string($row,0,$user->lastname);
                    $myxls->write_string($row,1,$user->firstname);
                    $studentid=(!empty($user->idnumber) ? $user->idnumber : " ");
                    $myxls->write_string($row,2,$studentid);
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2. $ug->name;
                        }
                    }
                    $myxls->write_string($row,3,$ug2);
                    if (isset($option_text)) {
                        $myxls->write_string($row,4,format_string($option_text,true));
                    }
                    $row++;
                }
            }
            $pos=4;
        }
        /// Close the workbook
        $workbook->close();
        exit;
    }

    // print text file
    if ($download == "txt" && has_capability('mod/enhancedchoice:downloadresponses', $context)) {
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($choice->name,true))).'.txt';

        header("Content-Type: application/download\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
        header("Pragma: public");

        /// Print names of all the fields

        echo get_string("firstname")."\t".get_string("lastname") . "\t". get_string("idnumber") . "\t";
        echo get_string("group"). "\t";
        echo get_string("choice","enhancedchoice"). "\n";

        /// generate the data for the body of the spreadsheet
        $i=0;
        if ($users) {
            foreach ($users as $option => $userid) {
                $option_text = choice_get_option_text($choice, $option);
                foreach($userid as $user) {
                    echo $user->lastname;
                    echo "\t".$user->firstname;
                    $studentid = " ";
                    if (!empty($user->idnumber)) {
                        $studentid = $user->idnumber;
                    }
                    echo "\t". $studentid."\t";
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2. $ug->name;
                        }
                    }
                    echo $ug2. "\t";
                    if (isset($option_text)) {
                        echo format_string($option_text,true);
                    }
                    echo "\n";
                }
            }
        }
        exit;
    }
    // Show those who haven't answered the question.
    if (!empty($choice->showunanswered)) {
        $choice->option[0] = get_string('notanswered', 'enhancedchoice');
        $choice->maxanswers[0] = 0;
    }

    $results = prepare_enhancedchoice_show_results($choice, $course, $cm, $users);
    $renderer = $PAGE->get_renderer('mod_enhancedchoice');
    echo $renderer->display_result($results, has_capability('mod/enhancedchoice:readresponses', $context));

   //now give links for downloading spreadsheets.
    if (!empty($users) && has_capability('mod/enhancedchoice:downloadresponses',$context)) {
        $downloadoptions = array();
        $options = array();
        $options["id"] = "$cm->id";
        $options["download"] = "ods";
        $button =  $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadods"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class'=>'reportoption'));

        $options["download"] = "xls";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadexcel"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class'=>'reportoption'));

        $options["download"] = "txt";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadtext"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class'=>'reportoption'));

        $downloadlist = html_writer::tag('ul', implode('', $downloadoptions));
        $downloadlist .= html_writer::tag('div', '', array('class'=>'clearfloat'));
        echo html_writer::tag('div',$downloadlist, array('class'=>'downloadreport'));
    }
    echo $OUTPUT->footer();

