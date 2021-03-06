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
 * IMS Enterprise file enrolment plugin.
 *
 * This plugin lets the user specify an IMS Enterprise file to be processed.
 * The IMS Enterprise file is mainly parsed on a regular cron,
 * but can also be imported via the UI (Admin Settings).
 * @package    enrol_imsenterprise
 * @copyright  2010 Eugene Venter
 * @author     Eugene Venter - based on code by Dan Stowell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/group/lib.php');


/**
 * IMS Enterprise file enrolment plugin.
 *
 * @copyright  2010 Eugene Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_imsenterprise_plugin extends enrol_plugin {

    /**
     * @var $logfp resource file pointer for writing log data to.
     */
    protected $logfp;

    /**
     * @var $continueprocessing bool flag to determine if processing should continue.
     */
    protected $continueprocessing;

    /**
     * @var $xmlcache string cache of xml lines.
     */
    protected $xmlcache;

<<<<<<< HEAD
    $this->logfp = false; // File pointer for writing log data to
    if (!empty($logtolocation)) {
        $this->logfp = fopen($logtolocation, 'a');
    }

    if ( file_exists($filename) ) {
        @set_time_limit(0);
        $starttime = time();

        $this->log_line('----------------------------------------------------------------------');
        $this->log_line("IMS Enterprise enrol cron process launched at " . userdate(time()));
        $this->log_line('Found file '.$filename);
        $this->xmlcache = '';

        // Make sure we understand how to map the IMS-E roles to Moodle roles
        $this->load_role_mappings();
        // Make sure we understand how to map the IMS-E course names to Moodle course names.
        $this->load_course_mappings();

        $md5 = md5_file($filename); // NB We'll write this value back to the database at the end of the cron
        $filemtime = filemtime($filename);

        // Decide if we want to process the file (based on filepath, modification time, and MD5 hash)
        // This is so we avoid wasting the server's efforts processing a file unnecessarily
        if (empty($prev_path)  || ($filename != $prev_path)) {
            $fileisnew = true;
        } elseif (isset($prev_time) && ($filemtime <= $prev_time)) {
            $fileisnew = false;
            $this->log_line('File modification time is not more recent than last update - skipping processing.');
        } elseif (isset($prev_md5) && ($md5 == $prev_md5)) {
            $fileisnew = false;
            $this->log_line('File MD5 hash is same as on last update - skipping processing.');
        } else {
            $fileisnew = true; // Let's process it!
        }

        if ($fileisnew) {

            $listoftags = array('group', 'person', 'member', 'membership', 'comments', 'properties'); // The list of tags which should trigger action (even if only cache trimming)
            $this->continueprocessing = true; // The <properties> tag is allowed to halt processing if we're demanding a matching target

            // FIRST PASS: Run through the file and process the group/person entries
            if (($fh = fopen($filename, "r")) != false) {

                $line = 0;
                while ((!feof($fh)) && $this->continueprocessing) {

                    $line++;
                    $curline = fgets($fh);
                    $this->xmlcache .= $curline; // Add a line onto the XML cache

                    while (true) {
                      // If we've got a full tag (i.e. the most recent line has closed the tag) then process-it-and-forget-it.
                      // Must always make sure to remove tags from cache so they don't clog up our memory
                      if ($tagcontents = $this->full_tag_found_in_cache('group', $curline)) {
                          $this->process_group_tag($tagcontents);
                          $this->remove_tag_from_cache('group');
                      } elseif($tagcontents = $this->full_tag_found_in_cache('person', $curline)) {
                          $this->process_person_tag($tagcontents);
                          $this->remove_tag_from_cache('person');
                      } elseif($tagcontents = $this->full_tag_found_in_cache('membership', $curline)) {
                          $this->process_membership_tag($tagcontents);
                          $this->remove_tag_from_cache('membership');
                      } elseif($tagcontents = $this->full_tag_found_in_cache('comments', $curline)) {
                          $this->remove_tag_from_cache('comments');
                      } elseif($tagcontents = $this->full_tag_found_in_cache('properties', $curline)) {
                          $this->process_properties_tag($tagcontents);
                          $this->remove_tag_from_cache('properties');
                      } else {
                          break;
                      }
                    } // End of while-tags-are-detected
                } // end of while loop
                fclose($fh);
                fix_course_sortorder();
            } // end of if(file_open) for first pass

            /*


            SECOND PASS REMOVED
            Since the IMS specification v1.1 insists that "memberships" should come last,
            and since vendors seem to have done this anyway (even with 1.0),
            we can sensibly perform the import in one fell swoop.


            // SECOND PASS: Now go through the file and process the membership entries
            $this->xmlcache = '';
            if (($fh = fopen($filename, "r")) != false) {
                $line = 0;
                while ((!feof($fh)) && $this->continueprocessing) {
                    $line++;
                    $curline = fgets($fh);
                    $this->xmlcache .= $curline; // Add a line onto the XML cache

                    while(true){
                  // Must always make sure to remove tags from cache so they don't clog up our memory
                  if($tagcontents = $this->full_tag_found_in_cache('group', $curline)){
                          $this->remove_tag_from_cache('group');
                      }elseif($tagcontents = $this->full_tag_found_in_cache('person', $curline)){
                          $this->remove_tag_from_cache('person');
                      }elseif($tagcontents = $this->full_tag_found_in_cache('membership', $curline)){
                          $this->process_membership_tag($tagcontents);
                          $this->remove_tag_from_cache('membership');
                      }elseif($tagcontents = $this->full_tag_found_in_cache('comments', $curline)){
                          $this->remove_tag_from_cache('comments');
                      }elseif($tagcontents = $this->full_tag_found_in_cache('properties', $curline)){
                          $this->remove_tag_from_cache('properties');
                      }else{
                    break;
                  }
                }
                } // end of while loop
                fclose($fh);
            } // end of if(file_open) for second pass
=======
    /**
     * @var $coursemappings array of mappings between IMS data fields and moodle course fields.
     */
    protected $coursemappings;

    /**
     * @var $rolemappings array of mappings between IMS roles and moodle roles.
     */
    protected $rolemappings;
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

    /**
     * Read in an IMS Enterprise file.
     * Originally designed to handle v1.1 files but should be able to handle
     * earlier types as well, I believe.
     *
     */
    public function cron() {
        global $CFG;

        // Get configs.
        $imsfilelocation = $this->get_config('imsfilelocation');
        $logtolocation = $this->get_config('logtolocation');
        $mailadmins = $this->get_config('mailadmins');
        $prevtime = $this->get_config('prev_time');
        $prevmd5 = $this->get_config('prev_md5');
        $prevpath = $this->get_config('prev_path');

        if (empty($imsfilelocation)) {
            $filename = "$CFG->dataroot/1/imsenterprise-enrol.xml";  // Default location.
        } else {
            $filename = $imsfilelocation;
        }

        $this->logfp = false;
        if (!empty($logtolocation)) {
            $this->logfp = fopen($logtolocation, 'a');
        }

        $fileisnew = false;
        if ( file_exists($filename) ) {
            @set_time_limit(0);
            $starttime = time();

            $this->log_line('----------------------------------------------------------------------');
            $this->log_line("IMS Enterprise enrol cron process launched at " . userdate(time()));
            $this->log_line('Found file '.$filename);
            $this->xmlcache = '';

            // Make sure we understand how to map the IMS-E roles to Moodle roles.
            $this->load_role_mappings();
            // Make sure we understand how to map the IMS-E course names to Moodle course names.
            $this->load_course_mappings();

            $md5 = md5_file($filename); // NB We'll write this value back to the database at the end of the cron.
            $filemtime = filemtime($filename);

            // Decide if we want to process the file (based on filepath, modification time, and MD5 hash)
            // This is so we avoid wasting the server's efforts processing a file unnecessarily.
            if (empty($prevpath)  || ($filename != $prevpath)) {
                $fileisnew = true;
            } else if (isset($prevtime) && ($filemtime <= $prevtime)) {
                $this->log_line('File modification time is not more recent than last update - skipping processing.');
            } else if (isset($prevmd5) && ($md5 == $prevmd5)) {
                $this->log_line('File MD5 hash is same as on last update - skipping processing.');
            } else {
                $fileisnew = true; // Let's process it!
            }

            if ($fileisnew) {

                // The <properties> tag is allowed to halt processing if we're demanding a matching target.
                $this->continueprocessing = true;

                // Run through the file and process the group/person entries.
                if (($fh = fopen($filename, "r")) != false) {

                    $line = 0;
                    while ((!feof($fh)) && $this->continueprocessing) {

                        $line++;
                        $curline = fgets($fh);
                        $this->xmlcache .= $curline; // Add a line onto the XML cache.

                        while (true) {
                            // If we've got a full tag (i.e. the most recent line has closed the tag) then process-it-and-forget-it.
                            // Must always make sure to remove tags from cache so they don't clog up our memory.
                            if ($tagcontents = $this->full_tag_found_in_cache('group', $curline)) {
                                $this->process_group_tag($tagcontents);
                                $this->remove_tag_from_cache('group');
                            } else if ($tagcontents = $this->full_tag_found_in_cache('person', $curline)) {
                                $this->process_person_tag($tagcontents);
                                $this->remove_tag_from_cache('person');
                            } else if ($tagcontents = $this->full_tag_found_in_cache('membership', $curline)) {
                                $this->process_membership_tag($tagcontents);
                                $this->remove_tag_from_cache('membership');
                            } else if ($tagcontents = $this->full_tag_found_in_cache('comments', $curline)) {
                                $this->remove_tag_from_cache('comments');
                            } else if ($tagcontents = $this->full_tag_found_in_cache('properties', $curline)) {
                                $this->process_properties_tag($tagcontents);
                                $this->remove_tag_from_cache('properties');
                            } else {
                                break;
                            }
                        }
                    }
                    fclose($fh);
                    fix_course_sortorder();
                }

                $timeelapsed = time() - $starttime;
                $this->log_line('Process has completed. Time taken: '.$timeelapsed.' seconds.');

            }

            // These variables are stored so we can compare them against the IMS file, next time round.
            $this->set_config('prev_time', $filemtime);
            $this->set_config('prev_md5',  $md5);
            $this->set_config('prev_path', $filename);

<<<<<<< HEAD
    } else { // end of if(file_exists)
        $this->log_line('File not found: '.$filename);
    }

    if (!empty($mailadmins)) {
        $msg = "An IMS enrolment has been carried out within Moodle.\nTime taken: $timeelapsed seconds.\n\n";
        if (!empty($logtolocation)){
            if ($this->logfp){
                $msg .= "Log data has been written to:\n";
                $msg .= "$logtolocation\n";
                $msg .= "(Log file size: ".ceil(filesize($logtolocation)/1024)."Kb)\n\n";
            } else {
                $msg .= "The log file appears not to have been successfully written.\nCheck that the file is writeable by the server:\n";
                $msg .= "$logtolocation\n\n";
            }
        } else {
            $msg .= "Logging is currently not active.";
        }
=======
        } else {
            $this->log_line('File not found: '.$filename);
        }

        if (!empty($mailadmins) && $fileisnew) {
            $timeelapsed = isset($timeelapsed) ? $timeelapsed : 0;
            $msg = "An IMS enrolment has been carried out within Moodle.\nTime taken: $timeelapsed seconds.\n\n";
            if (!empty($logtolocation)) {
                if ($this->logfp) {
                    $msg .= "Log data has been written to:\n";
                    $msg .= "$logtolocation\n";
                    $msg .= "(Log file size: ".ceil(filesize($logtolocation) / 1024)."Kb)\n\n";
                } else {
                    $msg .= "The log file appears not to have been successfully written.\n";
                    $msg .= "Check that the file is writeable by the server:\n";
                    $msg .= "$logtolocation\n\n";
                }
            } else {
                $msg .= "Logging is currently not active.";
            }
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_imsenterprise';
            $eventdata->name              = 'imsenterprise_enrolment';
            $eventdata->userfrom          = get_admin();
            $eventdata->userto            = get_admin();
            $eventdata->subject           = "Moodle IMS Enterprise enrolment notification";
            $eventdata->fullmessage       = $msg;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);

            $this->log_line('Notification email sent to administrator.');

        }

        if ($this->logfp) {
            fclose($this->logfp);
        }

<<<<<<< HEAD
    if ($this->logfp) {
      fclose($this->logfp);
    }


} // end of cron() function

/**
* Check if a complete tag is found in the cached data, which usually happens
* when the end of the tag has only just been loaded into the cache.
* Returns either false, or the contents of the tag (including start and end).
* @param string $tagname Name of tag to look for
* @param string $latestline The very last line in the cache (used for speeding up the match)
*/
function full_tag_found_in_cache($tagname, $latestline){ // Return entire element if found. Otherwise return false.
    if (strpos(strtolower($latestline), '</'.strtolower($tagname).'>')===false) {
        return false;
    } elseif (preg_match('{(<'.$tagname.'\b.*?>.*?</'.$tagname.'>)}is', $this->xmlcache, $matches)) {
        return $matches[1];
    } else return false;
}

/**
* Remove complete tag from the cached data (including all its contents) - so
* that the cache doesn't grow to unmanageable size
* @param string $tagname Name of tag to look for
*/
function remove_tag_from_cache($tagname){ // Trim the cache so we're not in danger of running out of memory.
    ///echo "<p>remove_tag_from_cache: $tagname</p>";  flush();  ob_flush();
    //  echo "<p>remove_tag_from_cache:<br />".htmlspecialchars($this->xmlcache);
    $this->xmlcache = trim(preg_replace('{<'.$tagname.'\b.*?>.*?</'.$tagname.'>}is', '', $this->xmlcache, 1)); // "1" so that we replace only the FIRST instance
    //  echo "<br />".htmlspecialchars($this->xmlcache)."</p>";
}

/**
* Very simple convenience function to return the "recstatus" found in person/group/role tags.
* 1=Add, 2=Update, 3=Delete, as specified by IMS, and we also use 0 to indicate "unspecified".
* @param string $tagdata the tag XML data
* @param string $tagname the name of the tag we're interested in
*/
function get_recstatus($tagdata, $tagname){
    if (preg_match('{<'.$tagname.'\b[^>]*recstatus\s*=\s*["\'](\d)["\']}is', $tagdata, $matches)) {
        // echo "<p>get_recstatus($tagname) found status of $matches[1]</p>";
        return intval($matches[1]);
    } else {
        // echo "<p>get_recstatus($tagname) found nothing</p>";
        return 0; // Unspecified
=======
    }

    /**
     * Check if a complete tag is found in the cached data, which usually happens
     * when the end of the tag has only just been loaded into the cache.
     *
     * @param string $tagname Name of tag to look for
     * @param string $latestline The very last line in the cache (used for speeding up the match)
     * @return bool|string false, or the contents of the tag (including start and end).
     */
    protected function full_tag_found_in_cache($tagname, $latestline) {
        // Return entire element if found. Otherwise return false.
        if (strpos(strtolower($latestline), '</'.strtolower($tagname).'>') === false) {
            return false;
        } else if (preg_match('{(<'.$tagname.'\b.*?>.*?</'.$tagname.'>)}is', $this->xmlcache, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
    }

<<<<<<< HEAD
/**
* Process the group tag. This defines a Moodle course.
* @param string $tagconents The raw contents of the XML element
*/
function process_group_tag($tagcontents) {
    global $DB;

    // Get configs
    $truncatecoursecodes    = $this->get_config('truncatecoursecodes');
    $createnewcourses       = $this->get_config('createnewcourses');
    $updatecourses          = $this->get_config('updatecourses');
    $createnewcategories    = $this->get_config('createnewcategories');
    $categoryseparator      = trim($this->get_config('categoryseparator'));

    if (!$categoryseparator) {
        $categoryseparator = '|';
    }

    // Process tag contents
    $group = new stdClass();
    if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
        $group->coursecode = trim($matches[1]);
=======
    /**
     * Remove complete tag from the cached data (including all its contents) - so
     * that the cache doesn't grow to unmanageable size
     *
     * @param string $tagname Name of tag to look for
     */
    protected function remove_tag_from_cache($tagname) {
        // Trim the cache so we're not in danger of running out of memory.
        // "1" so that we replace only the FIRST instance.
        $this->xmlcache = trim(preg_replace('{<'.$tagname.'\b.*?>.*?</'.$tagname.'>}is', '', $this->xmlcache, 1));
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
    }
<<<<<<< HEAD
    if (preg_match('{<description>.*?<long>(.*?)</long>.*?</description>}is', $tagcontents, $matches)) {
        $group->description = trim($matches[1]);
=======

<<<<<<< HEAD
    if (preg_match('{<description>.*?<long>(.*?)</long>.*?</description>}is', $tagcontents, $matches)) {
        $group->long = trim($matches[1]);
>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
    }
    if (preg_match('{<description>.*?<short>(.*?)</short>.*?</description>}is', $tagcontents, $matches)) {
        $group->short = trim($matches[1]);
    }
    if (preg_match('{<description>.*?<full>(.*?)</full>.*?</description>}is', $tagcontents, $matches)) {
        $group->full = trim($matches[1]);
=======
    /**
     * Very simple convenience function to return the "recstatus" found in person/group/role tags.
     * 1=Add, 2=Update, 3=Delete, as specified by IMS, and we also use 0 to indicate "unspecified".
     *
     * @param string $tagdata the tag XML data
     * @param string $tagname the name of the tag we're interested in
     * @return int recstatus value
     */
    protected static function get_recstatus($tagdata, $tagname) {
        if (preg_match('{<'.$tagname.'\b[^>]*recstatus\s*=\s*["\'](\d)["\']}is', $tagdata, $matches)) {
            return intval($matches[1]);
        } else {
            return 0; // Unspecified.
        }
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
    }

    /**
     * Process the group tag. This defines a Moodle course.
     *
     * @param string $tagcontents The raw contents of the XML element
     */
    protected function process_group_tag($tagcontents) {
        global $DB;

        // Get configs.
        $truncatecoursecodes    = $this->get_config('truncatecoursecodes');
        $createnewcourses       = $this->get_config('createnewcourses');
        $createnewcategories    = $this->get_config('createnewcategories');

        // Process tag contents.
        $group = new stdClass();
        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $group->coursecode = trim($matches[1]);
        }

        if (preg_match('{<description>.*?<long>(.*?)</long>.*?</description>}is', $tagcontents, $matches)) {
            $group->long = trim($matches[1]);
        }
        if (preg_match('{<description>.*?<short>(.*?)</short>.*?</description>}is', $tagcontents, $matches)) {
            $group->short = trim($matches[1]);
        }
        if (preg_match('{<description>.*?<full>(.*?)</full>.*?</description>}is', $tagcontents, $matches)) {
            $group->full = trim($matches[1]);
        }

        if (preg_match('{<org>.*?<orgunit>(.*?)</orgunit>.*?</org>}is', $tagcontents, $matches)) {
            $group->category = trim($matches[1]);
        }

        $recstatus = ($this->get_recstatus($tagcontents, 'group'));

        if (empty($group->coursecode)) {
            $this->log_line('Error: Unable to find course code in \'group\' element.');
        } else {
            // First, truncate the course code if desired.
            if (intval($truncatecoursecodes) > 0) {
                $group->coursecode = ($truncatecoursecodes > 0)
                    ? substr($group->coursecode, 0, intval($truncatecoursecodes))
                    : $group->coursecode;
            }

            // For compatibility with the (currently inactive) course aliasing, we need this to be an array.
            $group->coursecode = array($group->coursecode);

<<<<<<< HEAD
        // Third, check if the course(s) exist
        foreach ($group->coursecode as $coursecode) {
            $coursecode = trim($coursecode);
            // Set shortname to description or description to shortname if one is set but not the other.
            $nodescription = !isset($group->description);
            $noshortname = !isset($group->shortName);
            if ( $nodescription && $noshortname) {
                // If neither short nor long description are set let if fail
                $this->log_line("Neither long nor short name are set for $coursecode");
            } else if ($nodescription) {
                // If short and ID exist, then give the long short's value, then give short the ID's value
                $group->description = $group->shortName;
                $group->shortName = $coursecode;
            } else if ($noshortname) {
                // If long and ID exist, then map long to long, then give short the ID's value.
                $group->shortName = $coursecode;
            }
            if (!$DB->get_field('course', 'id', array('idnumber'=>$coursecode))) {
                if (!$createnewcourses) {
                    $this->log_line("Course $coursecode not found in Moodle's course idnumbers.");
                } else {
<<<<<<< HEAD
=======

>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
                    // Create the (hidden) course(s) if not found
                    $courseconfig = get_config('moodlecourse'); // Load Moodle Course shell defaults
=======
            // Third, check if the course(s) exist.
            foreach ($group->coursecode as $coursecode) {
                $coursecode = trim($coursecode);
                if (!$DB->get_field('course', 'id', array('idnumber' => $coursecode))) {
                    if (!$createnewcourses) {
                        $this->log_line("Course $coursecode not found in Moodle's course idnumbers.");
                    } else {

                        // Create the (hidden) course(s) if not found
                        $courseconfig = get_config('moodlecourse'); // Load Moodle Course shell defaults.
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

                        // New course.
                        $course = new stdClass();
                        foreach ($this->coursemappings as $courseattr => $imsname) {

                            if ($imsname == 'ignore') {
                                continue;
                            }

                            // Check if the IMS file contains the mapped tag, otherwise fallback on coursecode.
                            if ($imsname == 'coursecode') {
                                $course->{$courseattr} = $coursecode;
                            } else if (!empty($group->{$imsname})) {
                                $course->{$courseattr} = $group->{$imsname};
                            } else {
                                $this->log_line('No ' . $imsname . ' description tag found for '
                                    .$coursecode . ' coursecode, using ' . $coursecode . ' instead');
                                $course->{$courseattr} = $coursecode;
                            }
                        }

<<<<<<< HEAD
                    $course->idnumber = $coursecode;
                    $course->format = $courseconfig->format;
                    $course->visible = $courseconfig->visible;
                    $course->newsitems = $courseconfig->newsitems;
                    $course->showgrades = $courseconfig->showgrades;
                    $course->showreports = $courseconfig->showreports;
                    $course->maxbytes = $courseconfig->maxbytes;
                    $course->groupmode = $courseconfig->groupmode;
                    $course->groupmodeforce = $courseconfig->groupmodeforce;
                    $course->enablecompletion = $courseconfig->enablecompletion;
                    // Insert default names for teachers/students, from the current language

                    // Handle course categorisation (taken from the group.org.orgunit field if present)
                    if (strlen($group->category)>0) {
                        // Categories can be nested now...
                        $sep = '{\\'.$categoryseparator.'}';
                        $matches = preg_split($sep, $group->category, -1, PREG_SPLIT_NO_EMPTY);
                        // iterate through each category to get to the last
                        // one, create it if necessary (and allowed)
                        $catid = 0;
                        $t_catname = '';
                        foreach ($matches as $catname) {
                            $catname = trim($catname);
                            if (strlen($t_catname)) {
                                $t_catname .= ' / ';
                            }
                            $t_catname .= $catname;
                            $parentid = $catid;
                            if ($catid = $DB->get_field('course_categories', 'id', array('name'=>$catname,'parent'=>$parentid))) {
                                $course->category = $catid;
                                continue; // This category exists, skip to the next one
                            }
                            if ($createnewcategories) {
                                // Create this category
                                $newcat = new stdClass();
                                $newcat->name = $catname;
                                $newcat->visible = 0;
                                $newcat->parent = $parentid;
                                  $catid = $DB->insert_record('course_categories', $newcat);
                                $course->category = $catid;
                                $this->log_line("Created new (hidden) category '$t_catname'");
                            } else {
                                // we encountered a category that doesn't
                                // exist and we can't create it; set the
                                // default category for this course.
                                $course->category = 1; // Miscellaneous
                                $this->log_line("Cannot create requested category '$group->category'; setting to Miscellaneous.");
                                break;
                            }
                        }
                    } else {
                        // If no category was specified, set to Misc
                        $course->category = 1; // Miscellaneous
                    }

                    $course->timecreated = time();
                    $course->startdate = time();
                    // Choose a sort order that puts us at the start of the list!
                    $course->sortorder = 0;
                    $courseid = $DB->insert_record('course', $course);
=======
                        $course->idnumber = $coursecode;
                        $course->format = $courseconfig->format;
                        $course->visible = $courseconfig->visible;
                        $course->newsitems = $courseconfig->newsitems;
                        $course->showgrades = $courseconfig->showgrades;
                        $course->showreports = $courseconfig->showreports;
                        $course->maxbytes = $courseconfig->maxbytes;
                        $course->groupmode = $courseconfig->groupmode;
                        $course->groupmodeforce = $courseconfig->groupmodeforce;
                        $course->enablecompletion = $courseconfig->enablecompletion;
                        // Insert default names for teachers/students, from the current language.

                        // Handle course categorisation (taken from the group.org.orgunit field if present).
                        if (!empty($group->category)) {
                            // If the category is defined and exists in Moodle, we want to store it in that one.
                            if ($catid = $DB->get_field('course_categories', 'id', array('name' => $group->category))) {
                                $course->category = $catid;
                            } else if ($createnewcategories) {
                                // Else if we're allowed to create new categories, let's create this one.
                                $newcat = new stdClass();
                                $newcat->name = $group->category;
                                $newcat->visible = 0;
                                $catid = $DB->insert_record('course_categories', $newcat);
                                $course->category = $catid;
                                $this->log_line("Created new (hidden) category, #$catid: $newcat->name");
                            } else {
                                // If not found and not allowed to create, stick with default.
                                $this->log_line('Category '.$group->category.' not found in Moodle database, so using '.
                                    'default category instead.');
                                $course->category = $this->get_default_category_id();
                            }
                        } else {
                            $course->category = $this->get_default_category_id();
                        }
                        $course->timecreated = time();
                        $course->startdate = time();
                        // Choose a sort order that puts us at the start of the list!
                        $course->sortorder = 0;
                        $courseid = $DB->insert_record('course', $course);
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

                        // Setup default enrolment plugins.
                        $course->id = $courseid;
                        enrol_course_updated(true, $course, null);

                        // Setup the blocks.
                        $course = $DB->get_record('course', array('id' => $courseid));
                        blocks_add_default_course_blocks($course);

                        // Create default 0-section.
                        course_create_sections_if_missing($course, 0);

                        add_to_log(SITEID, "course", "new", "view.php?id=$course->id", "$course->fullname (ID $course->id)");

                        $this->log_line("Created course $coursecode in Moodle (Moodle ID is $course->id)");
                    }
                } else if ($recstatus == 3 && ($courseid = $DB->get_field('course', 'id', array('idnumber' => $coursecode)))) {
                    // If course does exist, but recstatus==3 (delete), then set the course as hidden.
                    $DB->set_field('course', 'visible', '0', array('id' => $courseid));
                }
<<<<<<< HEAD
            } else if ($recstatus==2 && ($courseid = $DB->get_field('course', 'id', array('idnumber'=>$coursecode)))) {
                if ($updatecourses) {
                    // Update
                    $did_update = 0;
                    $fullname = $DB->get_field('course', 'fullname', array('idnumber'=>$coursecode));
                    if ($fullname != $group->description) {
                        $DB->set_field('course', 'fullname', $group->description, array('idnumber'=>$coursecode));
                        add_to_log(SITEID, "course", "update", "view.php?id=$courseid", "$group->description (ID $courseid)");
                        $did_update = 1;
                    }
                    $shortname = $DB->get_field('course', 'shortname', array('idnumber'=>$coursecode));
                    if ($shortname != $group->shortName) {
                        $DB->set_field('course', 'shortname', $group->shortName, array('idnumber'=>$coursecode));
                        add_to_log(SITEID, "course", "update", "view.php?id=$courseid", "$group->shortName (ID $courseid)");
                        $did_update = 1;
                    }
                    if ($did_update) {
                        $this->log_line("Updated course $coursecode in Moodle (Moodle ID is $courseid)");
                    }
                } else {
                    $this->log_line("Ignoring update to course $coursecode");
                }
            } else if ($recstatus==3 && ($courseid = $DB->get_field('course', 'id', array('idnumber'=>$coursecode)))) {
                // If course does exist, but recstatus==3 (delete), then set the course as hidden
                $DB->set_field('course', 'visible', '0', array('id'=>$courseid));
                add_to_log(SITEID, "course", "delete", "view.php?id=$courseid", "$group->description (ID $courseid)");
                $this->log_line("Deleted (set to not visible) course $coursecode in Moodle (Moodle ID is $courseid)");
            }
        } // End of foreach(coursecode)
    }
} // End process_group_tag()

/**
* Process the person tag. This defines a Moodle user.
* @param string $tagconents The raw contents of the XML element
*/
function process_person_tag($tagcontents){
    global $CFG, $DB;

    // Get plugin configs
    $imssourcedidfallback   = $this->get_config('imssourcedidfallback');
    $fixcaseusernames       = $this->get_config('fixcaseusernames');
    $fixcasepersonalnames   = $this->get_config('fixcasepersonalnames');
    $imsdeleteusers         = $this->get_config('imsdeleteusers');
    $createnewusers         = $this->get_config('createnewusers');
    $imsupdateusers         = $this->get_config('imsupdateusers');

    $person = new stdClass();
    if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
        $person->idnumber = trim($matches[1]);
    }
    if (preg_match('{<name>.*?<n>.*?<given>(.+?)</given>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
        $person->firstname = trim($matches[1]);
    }
    if (preg_match('{<name>.*?<n>.*?<family>(.+?)</family>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
        $person->lastname = trim($matches[1]);
    }
    if (preg_match('{<userid\s+authenticationtype\s*=\s*"*(.+?)"*>.*?</userid>}is', $tagcontents, $matches)) {
        $person->auth = trim($matches[1]);
    }
    if (preg_match('{<userid.*?>(.*?)</userid>}is', $tagcontents, $matches)) {
        $person->username = trim($matches[1]);
    }
    if ($imssourcedidfallback && trim($person->username)=='') {
      // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied
      // NB We don't use an "elseif" because the tag may be supplied-but-empty
        $person->username = $person->idnumber;
    }
    if (preg_match('{<email>(.*?)</email>}is', $tagcontents, $matches)) {
        $person->email = trim($matches[1]);
    }
    if (preg_match('{<url>(.*?)</url>}is', $tagcontents, $matches)) {
        $person->url = trim($matches[1]);
    }
    if (preg_match('{<adr>.*?<locality>(.+?)</locality>.*?</adr>}is', $tagcontents, $matches)) {
        $person->city = trim($matches[1]);
    }
    if (preg_match('{<adr>.*?<country>(.+?)</country>.*?</adr>}is', $tagcontents, $matches)) {
        $person->country = trim($matches[1]);
    }

    // Fix case of some of the fields if required
    if ($fixcaseusernames && isset($person->username)) {
        $person->username = strtolower($person->username);
    }
    if ($fixcasepersonalnames) {
        if (isset($person->firstname)) {
            $person->firstname = ucwords(strtolower($person->firstname));
        }
        if (isset($person->lastname)) {
            $person->lastname = ucwords(strtolower($person->lastname));
=======
            }
        }
    }

    /**
     * Process the person tag. This defines a Moodle user.
     *
     * @param string $tagcontents The raw contents of the XML element
     */
    protected function process_person_tag($tagcontents) {
        global $CFG, $DB;

        // Get plugin configs.
        $imssourcedidfallback   = $this->get_config('imssourcedidfallback');
        $fixcaseusernames       = $this->get_config('fixcaseusernames');
        $fixcasepersonalnames   = $this->get_config('fixcasepersonalnames');
        $imsdeleteusers         = $this->get_config('imsdeleteusers');
        $createnewusers         = $this->get_config('createnewusers');

        $person = new stdClass();
        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $person->idnumber = trim($matches[1]);
        }
        if (preg_match('{<name>.*?<n>.*?<given>(.+?)</given>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
            $person->firstname = trim($matches[1]);
        }
        if (preg_match('{<name>.*?<n>.*?<family>(.+?)</family>.*?</n>.*?</name>}is', $tagcontents, $matches)) {
            $person->lastname = trim($matches[1]);
        }
        if (preg_match('{<userid>(.*?)</userid>}is', $tagcontents, $matches)) {
            $person->username = trim($matches[1]);
        }
        if ($imssourcedidfallback && trim($person->username) == '') {
            // This is the point where we can fall back to useing the "sourcedid" if "userid" is not supplied
            // NB We don't use an "elseif" because the tag may be supplied-but-empty.
            $person->username = $person->idnumber;
        }
        if (preg_match('{<email>(.*?)</email>}is', $tagcontents, $matches)) {
            $person->email = trim($matches[1]);
        }
        if (preg_match('{<url>(.*?)</url>}is', $tagcontents, $matches)) {
            $person->url = trim($matches[1]);
        }
        if (preg_match('{<adr>.*?<locality>(.+?)</locality>.*?</adr>}is', $tagcontents, $matches)) {
            $person->city = trim($matches[1]);
        }
        if (preg_match('{<adr>.*?<country>(.+?)</country>.*?</adr>}is', $tagcontents, $matches)) {
            $person->country = trim($matches[1]);
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
        }

        // Fix case of some of the fields if required.
        if ($fixcaseusernames && isset($person->username)) {
            $person->username = strtolower($person->username);
        }
        if ($fixcasepersonalnames) {
            if (isset($person->firstname)) {
                $person->firstname = ucwords(strtolower($person->firstname));
            }
            if (isset($person->lastname)) {
                $person->lastname = ucwords(strtolower($person->lastname));
            }
        }

        $recstatus = ($this->get_recstatus($tagcontents, 'person'));

<<<<<<< HEAD
    // Now if the recstatus is 3, we should delete the user if-and-only-if the setting for delete users is turned on
<<<<<<< HEAD
    // In the "users" table we can do this by setting deleted=1
    if ($recstatus==3) {
        if ($imsdeleteusers) { // If we're allowed to delete user records
            // Make sure their "deleted" field is set to one
            $DB->set_field('user', 'deleted', 1, array('username'=>$person->username));
            $this->log_line("Marked user record for user '$person->username' (ID number $person->idnumber) as deleted.");
        } else {
=======
    if($recstatus==3){
=======
        // Now if the recstatus is 3, we should delete the user if-and-only-if the setting for delete users is turned on.
        if ($recstatus == 3) {
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

            if ($imsdeleteusers) { // If we're allowed to delete user records.
                // Do not dare to hack the user.deleted field directly in database!!!
                $params = array('username' => $person->username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted ' => 0);
                if ($user = $DB->get_record('user', $params)) {
                    if (delete_user($user)) {
                        $this->log_line("Deleted user '$person->username' (ID number $person->idnumber).");
                    } else {
                        $this->log_line("Error deleting '$person->username' (ID number $person->idnumber).");
                    }
                } else {
                    $this->log_line("Can not delete user '$person->username' (ID number $person->idnumber) - user does not exist.");
                }
            } else {
                $this->log_line("Ignoring deletion request for user '$person->username' (ID number $person->idnumber).");
            }
<<<<<<< HEAD
        }else{
>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
            $this->log_line("Ignoring deletion request for user '$person->username' (ID number $person->idnumber).");
        }

    } else if ($recstatus==2) { // Update
        if ($imsupdateusers) {
            if ($id = $DB->get_field('user', 'id', array('idnumber'=>$person->idnumber))) {
                $person->id = $id;
                $DB->update_record('user', $person);
                $this->log_line("Updated user $person->username");
            } else {
                $this->log_line("Ignoring update request for non-existent user $person->username");
            }
        } else {
            $this->log_line("Ignoring update request for user $person->username");
        }
    } else { // Add record
        // If the user exists (matching sourcedid) then we don't need to do anything.
        if (!$DB->get_field('user', 'id', array('idnumber'=>$person->idnumber)) && $createnewusers) {
            // If they don't exist and haven't a defined username, we log this as a potential problem.
            if ((!isset($person->username)) || (strlen($person->username)==0)) {
                $this->log_line("Cannot create new user for ID # $person->idnumber - no username listed in IMS data for this person.");
            } else if ($DB->get_field('user', 'id', array('username'=>$person->username))){
                // If their idnumber is not registered but their user ID is, then add their idnumber to their record
                $DB->set_field('user', 'idnumber', $person->idnumber, array('username'=>$person->username));
            } else {

            // If they don't exist and they have a defined username, and $createnewusers == true, we create them.
<<<<<<< HEAD
            $person->lang = 'manual'; //TODO: this needs more work due tu multiauth changes
            if (!$person->auth) {
                $person->auth = $CFG->auth;
            }
=======
            $person->lang = $CFG->lang;
            $auth = explode(',', $CFG->auth); //TODO: this needs more work due tu multiauth changes, use first auth for now
            $auth = reset($auth);
            $person->auth = $auth;
>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
            $person->confirmed = 1;
            $person->timemodified = time();
            $person->mnethostid = $CFG->mnet_localhost_id;
            $id = $DB->insert_record('user', $person);
    /*
    Photo processing is deactivated until we hear from Moodle dev forum about modification to gdlib.

                                 //Antoni Mas. 07/12/2005. If a photo URL is specified then we might want to load
                                 // it into the user's profile. Beware that this may cause a heavy overhead on the server.
                                 if($CFG->enrol_processphoto){
                                   if(preg_match('{<photo>.*?<extref>(.*?)</extref>.*?</photo>}is', $tagcontents, $matches)){
                                     $person->urlphoto = trim($matches[1]);
                                   }
                                   //Habilitam el flag que ens indica que el personatge t foto prpia.
                                   $person->picture = 1;
                                   //Llibreria creada per nosaltres mateixos.
                                   require_once($CFG->dirroot.'/lib/gdlib.php');
                                   if ($usernew->picture = save_profile_image($id, $person->urlphoto,'user')) { TODO: use process_new_icon() instead
                                     $DB->set_field('user', 'picture', $usernew->picture, array('id'=>$id));  /// Note picture in DB
                                   }
                                 }
    */
                $this->log_line("Created user record for user '$person->username' (ID number $person->idnumber).");
=======

        } else { // Add or update record.

            // If the user exists (matching sourcedid) then we don't need to do anything.
            if (!$DB->get_field('user', 'id', array('idnumber' => $person->idnumber)) && $createnewusers) {
                // If they don't exist and haven't a defined username, we log this as a potential problem.
                if ((!isset($person->username)) || (strlen($person->username) == 0)) {
                    $this->log_line("Cannot create new user for ID # $person->idnumber".
                        "- no username listed in IMS data for this person.");
                } else if ($DB->get_field('user', 'id', array('username' => $person->username))) {
                    // If their idnumber is not registered but their user ID is, then add their idnumber to their record.
                    $DB->set_field('user', 'idnumber', $person->idnumber, array('username' => $person->username));
                } else {

                    // If they don't exist and they have a defined username, and $createnewusers == true, we create them.
                    $person->lang = $CFG->lang;
                    // TODO: MDL-15863 this needs more work due to multiauth changes, use first auth for now.
                    $auth = explode(',', $CFG->auth);
                    $auth = reset($auth);
                    $person->auth = $auth;
                    $person->confirmed = 1;
                    $person->timemodified = time();
                    $person->mnethostid = $CFG->mnet_localhost_id;
                    $id = $DB->insert_record('user', $person);
                    $this->log_line("Created user record ('.$id.') for user '$person->username' (ID number $person->idnumber).");
                }
            } else if ($createnewusers) {
                $this->log_line("User record already exists for user '$person->username' (ID number $person->idnumber).");

                // It is totally wrong to mess with deleted users flag directly in database!!!
                // There is no official way to undelete user, sorry..
            } else {
                $this->log_line("No user record found for '$person->username' (ID number $person->idnumber).");
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
            }

<<<<<<< HEAD
<<<<<<< HEAD
            // Make sure their "deleted" field is set to zero.
            $DB->set_field('user', 'deleted', 0, array('idnumber'=>$person->idnumber));
        } else {
=======
            // It is totally wrong to mess with deleted users flag directly in database!!!
            // There is no official way to undelete user, sorry..
        }else{
>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
            $this->log_line("No user record found for '$person->username' (ID number $person->idnumber).");
=======
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
        }

    }

    /**
     * Process the membership tag. This defines whether the specified Moodle users
     * should be added/removed as teachers/students.
     *
     * @param string $tagcontents The raw contents of the XML element
     */
    protected function process_membership_tag($tagcontents) {
        global $DB;

        // Get plugin configs.
        $truncatecoursecodes = $this->get_config('truncatecoursecodes');
        $imscapitafix = $this->get_config('imscapitafix');

<<<<<<< HEAD
/**
* Process the membership tag. This defines whether the specified Moodle users
* should be added/removed as teachers/students.
* @param string $tagconents The raw contents of the XML element
*/
function process_membership_tag($tagcontents){
    global $DB;

    // Get plugin configs
    $truncatecoursecodes = $this->get_config('truncatecoursecodes');
    $imscapitafix = $this->get_config('imscapitafix');

    $memberstally = 0;
    $membersuntally = 0;

    // In order to reduce the number of db queries required, group name/id associations are cached in this array:
    $groupids = array();

    $ship = new stdClass();

    if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
        $ship->coursecode = ($truncatecoursecodes > 0)
                                 ? substr(trim($matches[1]), 0, intval($truncatecoursecodes))
                                 : trim($matches[1]);
        $ship->courseid = $DB->get_field('course', 'id', array('idnumber'=>$ship->coursecode));
    }
    if ($ship->courseid && preg_match_all('{<member>(.*?)</member>}is', $tagcontents, $membermatches, PREG_SET_ORDER)) {
        $courseobj = new stdClass();
        $courseobj->id = $ship->courseid;

        foreach ($membermatches as $mmatch) {
            $member = new stdClass();
            $memberstoreobj = new stdClass();
            if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $mmatch[1], $matches)) {
                $member->idnumber = trim($matches[1]);
            }
            if (preg_match('{<role\s+roletype=["\'](.+?)["\'].*?>}is', $mmatch[1], $matches)) {
                $member->roletype = trim($matches[1]); // 01 means Student, 02 means Instructor, 3 means ContentDeveloper, and there are more besides
            } elseif ($imscapitafix && preg_match('{<roletype>(.+?)</roletype>}is', $mmatch[1], $matches)) {
                // The XML that comes out of Capita Student Records seems to contain a misinterpretation of the IMS specification!
                $member->roletype = trim($matches[1]); // 01 means Student, 02 means Instructor, 3 means ContentDeveloper, and there are more besides
            }
            if (preg_match('{<role\b.*?<status>(.+?)</status>.*?</role>}is', $mmatch[1], $matches)) {
                $member->status = trim($matches[1]); // 1 means active, 0 means inactive - treat this as enrol vs unenrol
            }

            $recstatus = ($this->get_recstatus($mmatch[1], 'role'));
            if ($recstatus==3) {
              $member->status = 0; // See above - recstatus of 3 (==delete) is treated the same as status of 0
              //echo "<p>process_membership_tag: unenrolling member due to recstatus of 3</p>";
            }

            $timeframe = new stdClass();
            $timeframe->begin = 0;
            $timeframe->end = 0;
            if (preg_match('{<role\b.*?<timeframe>(.+?)</timeframe>.*?</role>}is', $mmatch[1], $matches)) {
                $timeframe = $this->decode_timeframe($matches[1]);
            }
            if (preg_match('{<role\b.*?<extension>.*?<cohort>(.+?)</cohort>.*?</extension>.*?</role>}is', $mmatch[1], $matches)) {
                $member->groupname = trim($matches[1]);
                // The actual processing (ensuring a group record exists, etc) occurs below, in the enrol-a-student clause
            }

            $rolecontext = context_course::instance($ship->courseid);
            $rolecontext = $rolecontext->id; // All we really want is the ID
//$this->log_line("Context instance for course $ship->courseid is...");
//print_r($rolecontext);

            // Add or remove this student or teacher to the course...
            $memberstoreobj->userid = $DB->get_field('user', 'id', array('idnumber'=>$member->idnumber));
            $memberstoreobj->enrol = 'imsenterprise';
            $memberstoreobj->course = $ship->courseid;
            $memberstoreobj->time = time();
            $memberstoreobj->timemodified = time();
            if ($memberstoreobj->userid) {

                // Decide the "real" role (i.e. the Moodle role) that this user should be assigned to.
                // Zero means this roletype is supposed to be skipped.
                $moodleroleid = $this->rolemappings[$member->roletype];
                if (!$moodleroleid) {
                    $this->log_line("SKIPPING role $member->roletype for $memberstoreobj->userid ($member->idnumber) in course $memberstoreobj->course");
                    continue;
                }

                if (intval($member->status) == 1) {
                    // Enrol the member
=======
        $memberstally = 0;
        $membersuntally = 0;

        // In order to reduce the number of db queries required, group name/id associations are cached in this array.
        $groupids = array();

        $ship = new stdClass();

        if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $tagcontents, $matches)) {
            $ship->coursecode = ($truncatecoursecodes > 0)
                ? substr(trim($matches[1]), 0, intval($truncatecoursecodes))
                : trim($matches[1]);
            $ship->courseid = $DB->get_field('course', 'id', array('idnumber' => $ship->coursecode));
        }
        if ($ship->courseid && preg_match_all('{<member>(.*?)</member>}is', $tagcontents, $membermatches, PREG_SET_ORDER)) {
            $courseobj = new stdClass();
            $courseobj->id = $ship->courseid;

            foreach ($membermatches as $mmatch) {
                $member = new stdClass();
                $memberstoreobj = new stdClass();
                if (preg_match('{<sourcedid>.*?<id>(.+?)</id>.*?</sourcedid>}is', $mmatch[1], $matches)) {
                    $member->idnumber = trim($matches[1]);
                }
                if (preg_match('{<role\s+roletype=["\'](.+?)["\'].*?>}is', $mmatch[1], $matches)) {
                    // 01 means Student, 02 means Instructor, 3 means ContentDeveloper, and there are more besides.
                    $member->roletype = trim($matches[1]);
                } else if ($imscapitafix && preg_match('{<roletype>(.+?)</roletype>}is', $mmatch[1], $matches)) {
                    // The XML that comes out of Capita Student Records seems to contain a misinterpretation of
                    // the IMS specification! 01 means Student, 02 means Instructor, 3 means ContentDeveloper,
                    // and there are more besides.
                    $member->roletype = trim($matches[1]);
                }
                if (preg_match('{<role\b.*?<status>(.+?)</status>.*?</role>}is', $mmatch[1], $matches)) {
                    // 1 means active, 0 means inactive - treat this as enrol vs unenrol.
                    $member->status = trim($matches[1]);
                }

                $recstatus = ($this->get_recstatus($mmatch[1], 'role'));
                if ($recstatus == 3) {
                    // See above - recstatus of 3 (==delete) is treated the same as status of 0.
                    $member->status = 0;
                }

                $timeframe = new stdClass();
                $timeframe->begin = 0;
                $timeframe->end = 0;
                if (preg_match('{<role\b.*?<timeframe>(.+?)</timeframe>.*?</role>}is', $mmatch[1], $matches)) {
                    $timeframe = $this->decode_timeframe($matches[1]);
                }
                if (preg_match('{<role\b.*?<extension>.*?<cohort>(.+?)</cohort>.*?</extension>.*?</role>}is',
                        $mmatch[1], $matches)) {
                    $member->groupname = trim($matches[1]);
                    // The actual processing (ensuring a group record exists, etc) occurs below, in the enrol-a-student clause.
                }
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

                // Add or remove this student or teacher to the course...
                $memberstoreobj->userid = $DB->get_field('user', 'id', array('idnumber' => $member->idnumber));
                $memberstoreobj->enrol = 'imsenterprise';
                $memberstoreobj->course = $ship->courseid;
                $memberstoreobj->time = time();
                $memberstoreobj->timemodified = time();
                if ($memberstoreobj->userid) {

                    // Decide the "real" role (i.e. the Moodle role) that this user should be assigned to.
                    // Zero means this roletype is supposed to be skipped.
                    $moodleroleid = $this->rolemappings[$member->roletype];
                    if (!$moodleroleid) {
                        $this->log_line("SKIPPING role $member->roletype for $memberstoreobj->userid "
                            ."($member->idnumber) in course $memberstoreobj->course");
                        continue;
                    }

                    if (intval($member->status) == 1) {
                        // Enrol the member.

<<<<<<< HEAD
                    $this->log_line("Enrolled user #$memberstoreobj->userid ($member->idnumber) to role $member->roletype in course $ship->coursecode");
                    $memberstally++;

                    // At this point we can also ensure the group membership is recorded if present
                    if (isset($member->groupname)) {
                        // Create the group if it doesn't exist - either way, make sure we know the group ID
                        if (isset($groupids[$member->groupname])) {
                            $member->groupid = $groupids[$member->groupname]; // Recall the group ID from cache if available
                        } else {
                            if ($groupid = $DB->get_field('groups', 'id', array('courseid'=>$ship->courseid, 'name'=>$member->groupname))) {
                                $member->groupid = $groupid;
                                $groupids[$member->groupname] = $groupid; // Store ID in cache
=======
                        $einstance = $DB->get_record('enrol',
                            array('courseid' => $courseobj->id, 'enrol' => $memberstoreobj->enrol));
                        if (empty($einstance)) {
                            // Only add an enrol instance to the course if non-existent.
                            $enrolid = $this->add_instance($courseobj);
                            $einstance = $DB->get_record('enrol', array('id' => $enrolid));
                        }

                        $this->enrol_user($einstance, $memberstoreobj->userid, $moodleroleid, $timeframe->begin, $timeframe->end);

                        $this->log_line("Enrolled user #$memberstoreobj->userid ($member->idnumber) "
                            ."to role $member->roletype in course $memberstoreobj->course");
                        $memberstally++;

                        // At this point we can also ensure the group membership is recorded if present.
                        if (isset($member->groupname)) {
                            // Create the group if it doesn't exist - either way, make sure we know the group ID.
                            if (isset($groupids[$member->groupname])) {
                                $member->groupid = $groupids[$member->groupname]; // Recall the group ID from cache if available.
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
                            } else {
                                $params = array('courseid' => $ship->courseid, 'name' => $member->groupname);
                                if ($groupid = $DB->get_field('groups', 'id', $params)) {
                                    $member->groupid = $groupid;
                                    $groupids[$member->groupname] = $groupid; // Store ID in cache.
                                } else {
                                    // Attempt to create the group.
                                    $group = new stdClass();
                                    $group->name = $member->groupname;
                                    $group->courseid = $ship->courseid;
                                    $group->timecreated = time();
                                    $group->timemodified = time();
                                    $groupid = $DB->insert_record('groups', $group);
                                    $this->log_line('Added a new group for this course: '.$group->name);
                                    $groupids[$member->groupname] = $groupid; // Store ID in cache.
                                    $member->groupid = $groupid;
                                    // Invalidate the course group data cache just in case.
                                    cache_helper::invalidate_by_definition('core', 'groupdata', array(), array($ship->courseid));
                                }
                            }
                            // Add the user-to-group association if it doesn't already exist.
                            if ($member->groupid) {
                                groups_add_member($member->groupid, $memberstoreobj->userid,
                                    'enrol_imsenterprise', $einstance->id);
                            }
                        }

                    } else if ($this->get_config('imsunenrol')) {
                        // Unenrol member.

                        $einstances = $DB->get_records('enrol',
                            array('enrol' => $memberstoreobj->enrol, 'courseid' => $courseobj->id));
                        foreach ($einstances as $einstance) {
                            // Unenrol the user from all imsenterprise enrolment instances.
                            $this->unenrol_user($einstance, $memberstoreobj->userid);
                        }

                        $membersuntally++;
                        $this->log_line("Unenrolled $member->idnumber from role $moodleroleid in course");
                    }

<<<<<<< HEAD
                    $membersuntally++;
                    $this->log_line("Unenrolled $member->idnumber from role $moodleroleid in course $ship->coursecode");
=======
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
                }
            }
            $this->log_line("Added $memberstally users to course $ship->coursecode");
            if ($membersuntally > 0) {
                $this->log_line("Removed $membersuntally users from course $ship->coursecode");
            }
        }
<<<<<<< HEAD
        if ($memberstally > 0) {
            $this->log_line("Added $memberstally users to course $ship->coursecode");
        }
        if ($membersuntally > 0) {
            $this->log_line("Removed $membersuntally users from course $ship->coursecode");
=======
    } // End process_membership_tag().

    /**
     * Process the properties tag. The only data from this element
     * that is relevant is whether a <target> is specified.
     *
     * @param string $tagcontents The raw contents of the XML element
     */
    protected function process_properties_tag($tagcontents) {
        $imsrestricttarget = $this->get_config('imsrestricttarget');

        if ($imsrestricttarget) {
            if (!(preg_match('{<target>'.preg_quote($imsrestricttarget).'</target>}is', $tagcontents, $matches))) {
                $this->log_line("Skipping processing: required target \"$imsrestricttarget\" not specified in this data.");
                $this->continueprocessing = false;
            }
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
        }
    }

<<<<<<< HEAD
/**
* Process the properties tag. The only data from this element
* that is relevant is whether a <target> is specified.
* @param string $tagconents The raw contents of the XML element
*/
function process_properties_tag($tagcontents){
    $imsrestricttarget = $this->get_config('imsrestricttarget');

    if ($imsrestricttarget) {
        if (!(preg_match('{<target>'.preg_quote($imsrestricttarget).'</target>}is', $tagcontents, $matches))) {
            $this->log_line("Skipping processing: required target \"$imsrestricttarget\" not specified in this data.");
            $this->continueprocessing = false;
=======
    /**
     * Store logging information. This does two things: uses the {@link mtrace()}
     * function to print info to screen/STDOUT, and also writes log to a text file
     * if a path has been specified.
     * @param string $string Text to write (newline will be added automatically)
     */
    protected function log_line($string) {

        if (!PHPUNIT_TEST) {
            mtrace($string);
        }
        if ($this->logfp) {
            fwrite($this->logfp, $string . "\n");
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
        }
    }

<<<<<<< HEAD
/**
* Store logging information. This does two things: uses the {@link mtrace()}
* function to print info to screen/STDOUT, and also writes log to a text file
* if a path has been specified.
* @param string $string Text to write (newline will be added automatically)
*/
function log_line($string){
<<<<<<< HEAD
    mtrace($string);
    if ($this->logfp) {
=======

    if (!PHPUNIT_TEST) {
        mtrace($string);
    }
    if($this->logfp) {
>>>>>>> 838d78a9ff4290e2bca304a5232204f04fc910ec
        fwrite($this->logfp, $string . "\n");
=======
    /**
     * Process the INNER contents of a <timeframe> tag, to return beginning/ending dates.
     *
     * @param string $string tag to decode.
     * @return stdClass beginning and/or ending is returned, in unix time, zero indicating not specified.
     */
    protected static function decode_timeframe($string) {
        $ret = new stdClass();
        $ret->begin = $ret->end = 0;
        // Explanatory note: The matching will ONLY match if the attribute restrict="1"
        // because otherwise the time markers should be ignored (participation should be
        // allowed outside the period).
        if (preg_match('{<begin\s+restrict="1">(\d\d\d\d)-(\d\d)-(\d\d)</begin>}is', $string, $matches)) {
            $ret->begin = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
        }
        if (preg_match('{<end\s+restrict="1">(\d\d\d\d)-(\d\d)-(\d\d)</end>}is', $string, $matches)) {
            $ret->end = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
        }
        return $ret;
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f
    }

<<<<<<< HEAD
/**
* Process the INNER contents of a <timeframe> tag, to return beginning/ending dates.
*/
function decode_timeframe($string){ // Pass me the INNER CONTENTS of a <timeframe> tag - beginning and/or ending is returned, in unix time, zero indicating not specified
    $ret = new stdClass();
    $ret->begin = $ret->end = 0;
    // Explanatory note: The matching will ONLY match if the attribute restrict="1"
    // because otherwise the time markers should be ignored (participation should be
    // allowed outside the period)
    if (preg_match('{<begin\s+restrict="1">(\d\d\d\d)-(\d\d)-(\d\d)</begin>}is', $string, $matches)) {
        $ret->begin = mktime(0,0,0, $matches[2], $matches[3], $matches[1]);
    }
    if (preg_match('{<end\s+restrict="1">(\d\d\d\d)-(\d\d)-(\d\d)</end>}is', $string, $matches)) {
        $ret->end = mktime(0,0,0, $matches[2], $matches[3], $matches[1]);
    }
    return $ret;
} // End decode_timeframe
=======
    /**
     * Load the role mappings (from the config), so we can easily refer to
     * how an IMS-E role corresponds to a Moodle role
     */
    protected function load_role_mappings() {
        require_once('locallib.php');
>>>>>>> 5386f0bbfe279002af3ca217ff50615449dc652f

        $imsroles = new imsenterprise_roles();
        $imsroles = $imsroles->get_imsroles();

        $this->rolemappings = array();
        foreach ($imsroles as $imsrolenum => $imsrolename) {
            $this->rolemappings[$imsrolenum] = $this->rolemappings[$imsrolename] = $this->get_config('imsrolemap' . $imsrolenum);
        }
    }

    /**
     * Load the name mappings (from the config), so we can easily refer to
     * how an IMS-E course properties corresponds to a Moodle course properties
     */
    protected function load_course_mappings() {
        require_once('locallib.php');

        $imsnames = new imsenterprise_courses();
        $courseattrs = $imsnames->get_courseattrs();

        $this->coursemappings = array();
        foreach ($courseattrs as $courseattr) {
            $this->coursemappings[$courseattr] = $this->get_config('imscoursemap' . $courseattr);
        }
    }

    /**
     * Called whenever anybody tries (from the normal interface) to remove a group
     * member which is registered as being created by this component. (Not called
     * when deleting an entire group or course at once.)
     * @param int $itemid Item ID that was stored in the group_members entry
     * @param int $groupid Group ID
     * @param int $userid User ID being removed from group
     * @return bool True if the remove is permitted, false to give an error
     */
    public function enrol_imsenterprise_allow_group_member_remove($itemid, $groupid, $userid) {
        return false;
    }


    /**
     * Get the default category id (often known as 'Miscellaneous'),
     * statically cached to avoid multiple DB lookups on big imports.
     *
     * @return int id of default category.
     */
    private function get_default_category_id() {
        global $CFG;
        require_once($CFG->libdir.'/coursecatlib.php');

        static $defaultcategoryid = null;

        if ($defaultcategoryid === null) {
            $category = coursecat::get_default();
            $defaultcategoryid = $category->id;
        }

        return $defaultcategoryid;
    }
}
