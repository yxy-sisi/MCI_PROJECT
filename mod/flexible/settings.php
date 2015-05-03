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
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');
require_once('model.php');

// Add admin panel first-level element called "POAS assignment plugins"
$ADMIN->add('modules', new admin_category('modflexibleplugins',
    new lang_string('flexibleplugins', 'flexible'), !$module->visible));

// Add admin panel item for graders
$ADMIN->add('modflexibleplugins', new admin_category('flexibleplugins',
    new lang_string('subplugintype_flexible_plural', 'flexible'), !$module->visible));

// Add admin panel item for answer plugins
$ADMIN->add('modflexibleplugins', new admin_category('flexibleanswertypesplugins',
    new lang_string('subplugintype_flexibleanswertypes_plural', 'flexible'), !$module->visible));

// Add admin panel item for taskgivers plugins
$ADMIN->add('modflexibleplugins', new admin_category('flexibletaskgiversplugins',
    new lang_string('subplugintype_flexibletaskgivers_plural', 'flexible'), !$module->visible));

// Add admin panel item for additional plugins
$ADMIN->add('modflexibleplugins', new admin_category('flexibleadditionalplugins',
    new lang_string('subplugintype_flexibleadditional_plural', 'flexible'), !$module->visible));

flexible_model::add_admin_plugin_settings('flexible', $ADMIN, $settings, $module);
flexible_model::add_admin_plugin_settings('flexibleanswertypes', $ADMIN, $settings, $module);
flexible_model::add_admin_plugin_settings('flexibletaskgivers', $ADMIN, $settings, $module);
flexible_model::add_admin_plugin_settings('flexibleadditional', $ADMIN, $settings, $module);