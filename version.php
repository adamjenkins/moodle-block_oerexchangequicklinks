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
 * Version information for block_oerexchangequicklinks.
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_oerexchangequicklinks';
$plugin->version   = 2026072901;
// 2025041400 = the Moodle 5.0 branching version — matches $supported's floor
// (and composer.json's ">=5.0 <5.3"); was 2024100700 (Moodle 4.5), which let
// a site below the tested range install the plugin.
$plugin->requires  = 2025041400;
$plugin->supported = [500, 502];
$plugin->release   = '1.0.1';
$plugin->maturity  = MATURITY_STABLE;

// This block only makes sense installed alongside the Exchange catalogue
// plugin it reads from; there is no subplugin relationship available for
// block types, so the installer dependency check is the real enforcement
// mechanism (see dev-docs/oer-platform/BLOCKS-DESIGN.md, "Why not
// subplugins").
$plugin->dependencies = ['local_oerexchange' => ANY_VERSION];
