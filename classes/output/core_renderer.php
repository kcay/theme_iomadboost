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

namespace theme_iomadboost\output;

use moodle_url;
use html_writer;
use get_string;

defined('MOODLE_INTERNAL') || die;

/**
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_iomadboost
 * @copyright  2012 Bas Brands, www.basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {

    /**
     * Returns HTML to display a "Turn editing on/off" button in a form.
     *
     * @param moodle_url $url The URL + params to send through when clicking the button
     * @param string $method
     * @return string HTML the button
     */
    public function edit_button(moodle_url $url, string $method = 'post') {
        if ($this->page->theme->haseditswitch) {
            return;
        }
        $url->param('sesskey', sesskey());
        if ($this->page->user_is_editing()) {
            $url->param('edit', 'off');
            $editstring = get_string('turneditingoff');
        } else {
            $url->param('edit', 'on');
            $editstring = get_string('turneditingon');
        }
        $button = new \single_button($url, $editstring, $method, \single_button::BUTTON_PRIMARY);
        return $this->render_single_button($button);
    }

    /**
     * Renders the "breadcrumb" for all pages in iomadboost.
     *
     * @return string the HTML for the navbar.
     */
    public function navbar(): string {
        $newnav = new \theme_iomadboost\iomadboostnavbar($this->page);
        return $this->render_from_template('core/navbar', $newnav);
    }

    /**
     * Renders the context header for the page.
     *
     * @param array $headerinfo Heading information.
     * @param int $headinglevel What 'h' level to make the heading.
     * @return string A rendered context header.
     */
    public function context_header($headerinfo = null, $headinglevel = 1): string {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $context = $this->page->context;
        $heading = null;
        $imagedata = null;
        $userbuttons = null;

        // Make sure to use the heading if it has been set.
        if (isset($headerinfo['heading'])) {
            $heading = $headerinfo['heading'];
        } else {
            $heading = $this->page->heading;
        }

        // The user context currently has images and buttons. Other contexts may follow.
        if ((isset($headerinfo['user']) || $context->contextlevel == CONTEXT_USER) && $this->page->pagetype !== 'my-index') {
            if (isset($headerinfo['user'])) {
                $user = $headerinfo['user'];
            } else {
                // Look up the user information if it is not supplied.
                $user = $DB->get_record('user', array('id' => $context->instanceid));
            }

            // If the user context is set, then use that for capability checks.
            if (isset($headerinfo['usercontext'])) {
                $context = $headerinfo['usercontext'];
            }

            // Only provide user information if the user is the current user, or a user which the current user can view.
            // When checking user_can_view_profile(), either:
            // If the page context is course, check the course context (from the page object) or;
            // If page context is NOT course, then check across all courses.
            $course = ($this->page->context->contextlevel == CONTEXT_COURSE) ? $this->page->course : null;

            if (user_can_view_profile($user, $course)) {
                // Use the user's full name if the heading isn't set.
                if (empty($heading)) {
                    $heading = fullname($user);
                }

                $imagedata = $this->user_picture($user, array('size' => 100));

                // Check to see if we should be displaying a message button.
                if (!empty($CFG->messaging) && has_capability('moodle/site:sendmessage', $context)) {
                    $userbuttons = array(
                        'messages' => array(
                            'buttontype' => 'message',
                            'title' => get_string('message', 'message'),
                            'url' => new moodle_url('/message/index.php', array('id' => $user->id)),
                            'image' => 't/message',
                            'linkattributes' => \core_message\helper::messageuser_link_params($user->id),
                            'page' => $this->page
                        )
                    );

                    if ($USER->id != $user->id) {
                        $iscontact = \core_message\api::is_contact($USER->id, $user->id);
                        $isrequested = \core_message\api::get_contact_requests_between_users($USER->id, $user->id);
                        $contacturlaction = '';
                        $linkattributes = \core_message\helper::togglecontact_link_params(
                            $user,
                            $iscontact,
                            true,
                            !empty($isrequested),
                        );
                        // If the user is not a contact.
                        if (!$iscontact) {
                            if ($isrequested) {
                                // We just need the first request.
                                $requests = array_shift($isrequested);
                                if ($requests->userid == $USER->id) {
                                    // If the user has requested to be a contact.
                                    $contacttitle = 'contactrequestsent';
                                } else {
                                    // If the user has been requested to be a contact.
                                    $contacttitle = 'waitingforcontactaccept';
                                }
                                $linkattributes = array_merge($linkattributes, [
                                    'class' => 'disabled',
                                    'tabindex' => '-1',
                                ]);
                            } else {
                                // If the user is not a contact and has not requested to be a contact.
                                $contacttitle = 'addtoyourcontacts';
                                $contacturlaction = 'addcontact';
                            }
                            $contactimage = 't/addcontact';
                        } else {
                            // If the user is a contact.
                            $contacttitle = 'removefromyourcontacts';
                            $contacturlaction = 'removecontact';
                            $contactimage = 't/removecontact';
                        }
                        $userbuttons['togglecontact'] = array(
                                'buttontype' => 'togglecontact',
                                'title' => get_string($contacttitle, 'message'),
                                'url' => new moodle_url('/message/index.php', array(
                                        'user1' => $USER->id,
                                        'user2' => $user->id,
                                        $contacturlaction => $user->id,
                                        'sesskey' => sesskey())
                                ),
                                'image' => $contactimage,
                                'linkattributes' => $linkattributes,
                                'page' => $this->page
                            );
                    }

                    $this->page->requires->string_for_js('changesmadereallygoaway', 'moodle');
                }
            } else {
                $heading = null;
            }
        }

        $prefix = null;
        if ($context->contextlevel == CONTEXT_MODULE) {
            if ($this->page->course->format === 'singleactivity') {
                $heading = format_string($this->page->course->fullname, true, ['context' => $context]);
            } else {
                $heading = $this->page->cm->get_formatted_name();
                $iconurl = $this->page->cm->get_icon_url();
                $iconclass = $iconurl->get_param('filtericon') ? '' : 'nofilter';
                $iconattrs = [
                    'class' => "icon activityicon $iconclass",
                    'aria-hidden' => 'true'
                ];
                $imagedata = html_writer::img($iconurl->out(false), '', $iconattrs);
                $purposeclass = plugin_supports('mod', $this->page->activityname, FEATURE_MOD_PURPOSE);
                $purposeclass .= ' activityiconcontainer icon-size-6';
                $purposeclass .= ' modicon_' . $this->page->activityname;
                $isbranded = component_callback('mod_' . $this->page->activityname, 'is_branded', [], false);
                $imagedata = html_writer::tag('div', $imagedata, ['class' => $purposeclass . ($isbranded ? ' isbranded' : '')]);
                if (!empty($USER->editing)) {
                    $prefix = get_string('modulename', $this->page->activityname);
                }
            }
        }

        $contextheader = new \context_header($heading, $headinglevel, $imagedata, $userbuttons, $prefix);
        return $this->render($contextheader);
    }

    /**
     * See if this is the first view of the current cm in the session if it has fake blocks.
     *
     * (We track up to 100 cms so as not to overflow the session.)
     * This is done for drawer regions containing fake blocks so we can show blocks automatically.
     *
     * @return boolean true if the page has fakeblocks and this is the first visit.
     */
    public function firstview_fakeblocks(): bool {
        global $SESSION;

        $firstview = false;
        if ($this->page->cm) {
            if (!$this->page->blocks->region_has_fakeblocks('side-pre')) {
                return false;
            }
            if (!property_exists($SESSION, 'firstview_fakeblocks')) {
                $SESSION->firstview_fakeblocks = [];
            }
            if (array_key_exists($this->page->cm->id, $SESSION->firstview_fakeblocks)) {
                $firstview = false;
            } else {
                $SESSION->firstview_fakeblocks[$this->page->cm->id] = true;
                $firstview = true;
                if (count($SESSION->firstview_fakeblocks) > 100) {
                    array_shift($SESSION->firstview_fakeblocks);
                }
            }
        }
        return $firstview;
    }

    // IOMAD Boost customisations.

    /**
     * The standard tags that should be included in the <head> tag
     * including a meta description for the front page
     * We cheekily add un-cached CSS for Iomad here
     *
     * @return string HTML fragment.
     */
    public function standard_head_html() {
        global $SITE, $PAGE, $DB;

        // Inject additional 'live' css
        $css = '';

        // Get company colours
        $companyid = \iomad::get_my_companyid(\context_system::instance(), false);
        if ($companyrec = $DB->get_record('company', array('id' => $companyid))) {
            $company = $DB->get_record('company', array('id' => $companyid), '*', MUST_EXIST);
            $linkcolor = $company->linkcolor;
            if ($linkcolor) {
                $css .= 'a {color: ' . $linkcolor . '} ';
            }
            $headingcolor = $company->headingcolor;
            if ($headingcolor) {
                $css .= '.navbar {background-color: ' . $headingcolor . '!important} ';
            }
            $maincolor = $company->maincolor;
            if ($maincolor) {
                $css .= 'body, #nav-drawer {background-color: ' . $maincolor . '!important} ';
            }

            $css .= $company->customcss;
        }

        $output = parent::standard_head_html();

        if ($css) {
            $output .= '<style>' . $css . '</style>';
        }

        return $output;
    }

    /**
     * This code renders the navbar button to control the display of the custom menu
     * on smaller screens.
     *
     * Do not display the button if the menu is empty.
     *
     * @return string HTML fragment
     */
    public function navbar_button() {
        global $CFG, $DB;

        $custommenuitems = false;
        // Deal with company custom menu items.
        if ($companyid = \iomad::get_my_companyid(\context_system::instance(), false)) {
            if ($companyrec = $DB->get_record('company', array('id' => $companyid))) {
                if (!empty($companyrec->custommenuitems)) {
                    $custommenuitems = true;
                }
            }
        }

        if (empty($CFG->custommenuitems) && $this->lang_menu() == '' && empty($custommenuitems)) {
            return '';
        }

        $iconbar = html_writer::tag('span', '', array('class' => 'icon-bar'));
        $button = html_writer::tag('a', $iconbar . "\n" . $iconbar. "\n" . $iconbar, array(
            'class'       => 'btn btn-navbar',
            'data-toggle' => 'collapse',
            'data-target' => '.nav-collapse'
        ));
        return $button;
    }

    /**
     * Returns URL to the stored file via pluginfile.php.
     *
     * Note the theme must also implement pluginfile.php handler,
     * theme revision is used instead of the itemid.
     *
     * @param string $setting
     * @param string $filearea
     * @return string protocol relative URL or null if not present
     */
    public function setting_file_url($setting, $filearea) {
        global $CFG;

            return null;
        if (empty($this->settings->$setting)) {
            return null;
        }

        $component = 'theme_'.$this->name;
        $itemid = theme_get_revision();
        $filepath = $this->settings->$setting;
        $syscontext = context_system::instance();

        $url = moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php", "/$syscontext->id/$component/$filearea/$itemid".$filepath);

        // Now this is tricky because the we can not hardcode http or https here, lets use the relative link.
        // Note: unfortunately moodle_url does not support //urls yet.

        $url = preg_replace('|^https?://|i', '//', $url->out(false));

        return $url;
    }
}
