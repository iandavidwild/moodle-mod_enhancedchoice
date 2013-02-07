<?php

    require_once("../../config.php");
    require_once("lib.php");
    require_once($CFG->libdir . '/completionlib.php');

    $id         = required_param('id', PARAM_INT);                 // Course Module ID
    $action     = optional_param('action', '', PARAM_ALPHA);
    $attemptids = optional_param_array('attemptid', array(), PARAM_INT); // array of attempt ids for delete action

    $url = new moodle_url('/mod/enhancedchoice/view.php', array('id'=>$id));
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('enhancedchoice', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    require_course_login($course, false, $cm);

    if (!$choice = enhancedchoice_get_choice($cm->instance)) {
        print_error('invalidcoursemodule');
    }

    $strchoice = get_string('modulename', 'enhancedchoice');
    $strchoices = get_string('modulenameplural', 'enhancedchoice');

    $context = context_module::instance($cm->id);

    if ($action == 'delchoice' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/enhancedchoice:choose') and $choice->allowupdate) {
        if ($answer = $DB->get_record('enhancedchoice_answers', array('choiceid' => $choice->id, 'userid' => $USER->id))) {
            $DB->delete_records('enhancedchoice_answers', array('id' => $answer->id));

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $choice->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE);
            }
        }
    }

    $PAGE->set_title(format_string($choice->name));
    $PAGE->set_heading($course->fullname);

    // Mark viewed by user (if required)
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

/// Submit any new data if there is any
    if (data_submitted() && is_enrolled($context, NULL, 'mod/enhancedchoice:choose') && confirm_sesskey()) {
        $timenow = time();
        if (has_capability('mod/enhancedchoice:deleteresponses', $context)) {
            if ($action == 'delete') { //some responses need to be deleted
                enhancedchoice_delete_responses($attemptids, $choice, $cm, $course); //delete responses.
                redirect("view.php?id=$cm->id");
            }
        }
        $answer = optional_param('answer', '', PARAM_INT);

        if (empty($answer)) {
            redirect("view.php?id=$cm->id", get_string('mustchooseone', 'enhancedchoice'));
        } else {
            enhancedchoice_user_submit_response($answer, $choice, $USER->id, $course, $cm);
        }
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('choicesaved', 'enhancedchoice'),'notifysuccess');
    } else {
        echo $OUTPUT->header();
    }


/// Display the choice and possibly results
    add_to_log($course->id, "enhancedchoice", "view", "view.php?id=$cm->id", $choice->id, $cm->id);

    /// Check to see if groups are being used in this choice
    $groupmode = groups_get_activity_groupmode($cm);

    if ($groupmode) {
        groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/enhancedchoice/view.php?id='.$id);
    }
    $allresponses = enhancedchoice_get_response_data($choice, $cm, $groupmode);   // Big function, approx 6 SQL calls per user


    if (has_capability('mod/enhancedchoice:readresponses', $context)) {
        enhancedchoice_show_reportlink($allresponses, $cm);
    }

    echo '<div class="clearer"></div>';

    if ($choice->intro) {
        echo $OUTPUT->box(format_module_intro('enhancedchoice', $choice, $cm->id), 'generalbox', 'intro');
    }

    $timenow = time();
    $current = false;  // Initialise for later
    //if user has already made a selection, and they are not allowed to update it or if choice is not open, show their selected answer.
    if (isloggedin() && ($current = $DB->get_record('enhancedchoice_answers', array('choiceid' => $choice->id, 'userid' => $USER->id))) &&
        (empty($choice->allowupdate) || ($timenow > $choice->timeclose)) ) {
        echo $OUTPUT->box(get_string("yourselection", "enhancedchoice", userdate($choice->timeopen)).": ".format_string(enhancedchoice_get_option_text($choice, $current->optionid)), 'generalbox', 'yourselection');
    }

/// Print the form
    $choiceopen = true;
    if ($choice->timeclose !=0) {
        if ($choice->timeopen > $timenow ) {
            echo $OUTPUT->box(get_string("notopenyet", "enhancedchoice", userdate($choice->timeopen)), "generalbox notopenyet");
            echo $OUTPUT->footer();
            exit;
        } else if ($timenow > $choice->timeclose) {
            echo $OUTPUT->box(get_string("expired", "enhancedchoice", userdate($choice->timeclose)), "generalbox expired");
            $choiceopen = false;
        }
    }

    if ( (!$current or $choice->allowupdate) and $choiceopen and is_enrolled($context, NULL, 'mod/enhancedchoice:choose')) {
    // They haven't made their choice yet or updates allowed and choice is open

        $options = enhancedchoice_prepare_options($choice, $USER, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_enhancedchoice');
        echo $renderer->display_options($options, $cm->id, $choice->display);
        $choiceformshown = true;
    } else {
        $choiceformshown = false;
    }

    if (!$choiceformshown) {
        $sitecontext = context_system::instance();

        if (isguestuser()) {
            // Guest account
            echo $OUTPUT->confirm(get_string('noguestchoose', 'enhancedchoice').'<br /><br />'.get_string('liketologin'),
                         get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
        } else if (!is_enrolled($context)) {
            // Only people enrolled can make a choice
            $SESSION->wantsurl = qualified_me();
            $SESSION->enrolcancel = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';

            $coursecontext = context_course::instance($course->id);
            $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

            echo $OUTPUT->box_start('generalbox', 'notice');
            echo '<p align="center">'. get_string('notenrolledchoose', 'enhancedchoice') .'</p>';
            echo $OUTPUT->container_start('continuebutton');
            echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
            echo $OUTPUT->container_end();
            echo $OUTPUT->box_end();

        }
    }

    // print the results at the bottom of the screen
    if ( $choice->showresults == ENHANCEDCHOICE_SHOWRESULTS_ALWAYS or
        ($choice->showresults == ENHANCEDCHOICE_SHOWRESULTS_AFTER_ANSWER and $current) or
        ($choice->showresults == ENHANCEDCHOICE_SHOWRESULTS_AFTER_CLOSE and !$choiceopen)) {

        if (!empty($choice->showunanswered)) {
            $choice->option[0] = get_string('notanswered', 'enhancedchoice');
            $choice->maxanswers[0] = 0;
        }
        $results = prepare_enhancedchoice_show_results($choice, $course, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_enhancedchoice');
        echo $renderer->display_result($results);

    } else if (!$choiceformshown) {
        echo $OUTPUT->box(get_string('noresultsviewable', 'enhancedchoice'));
    }

    echo $OUTPUT->footer();
