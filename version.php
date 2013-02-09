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
 * Version information
 *
 * @package    mod
 * @subpackage enhancedchoice
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}, Ian David Wild {@link http://heavy-horse.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$module->version   = 2013020400;       // The current module version (Date: YYYYMMDDXX)
$module->requires  = 2011070100;    // Requires this Moodle version (2.1)
$module->component = 'mod_enhancedchoice';     // Full name of the plugin (used for diagnostics)
$module->maturity  = MATURITY_ALPHA;	// How stable the plugin is
$module->release   = '0.1 (Build: 2013020900)';  // Human-readable version name
$module->cron      = 0;
