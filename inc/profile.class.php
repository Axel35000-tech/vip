<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 vip plugin for GLPI
 Copyright (C) 2016-2022 by the vip Development Team.

 https://github.com/InfotelGLPI/vip
 -------------------------------------------------------------------------

 LICENSE

 This file is part of vip.

 vip is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 vip is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with vip. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginVipProfile
 *
 * This class manages the profile rights of the plugin
 */
class PluginVipProfile extends Profile {

   /**
    * Get tab name for item
    *
    * @param CommonGLPI $item
    * @param type       $withtemplate
    *
    * @return string
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Profile'
          && $item->getField('interface') != 'helpdesk') {
         return __('VIP', 'vip');
      }
      return '';
   }

   /**
    * display tab content for item
    *
    * @global type      $CFG_GLPI
    *
    * @param CommonGLPI $item
    * @param type       $tabnum
    * @param type       $withtemplate
    *
    * @return boolean
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      if ($item->getType() == 'Profile') {
         $ID   = $item->getID();
         $prof = new self();

         self::addDefaultProfileInfos($ID,
                                      ['plugin_vip' => 0]);
         $prof->showForm($ID);
      }

      return true;
   }

   /**
    * show profile form
    *
    * @param type $ID
    * @param type $options
    *
    * @return boolean
    */
   function showForm($profiles_id = 0, $openform = TRUE, $closeform = TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $rights = $this->getAllRights();
      $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                         'default_class' => 'tab_bg_2',
                                                         'title'         => __('General')]);
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $profiles_id]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      $this->showLegend();
   }

   /**
    * Get all rights
    *
    * @param type $all
    *
    * @return array
    */
   static function getAllRights($all = false) {

      $rights = [
         ['itemtype' => 'PluginVipGroup',
               'label'    => __('VIP', 'vip'),
               'field'    => 'plugin_vip'
         ]
      ];

      return $rights;
   }

   /**
    * Init profiles
    *
    **/

   static function translateARight($old_right) {
      switch ($old_right) {
         case '':
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return ALLSTANDARDRIGHT;
         case '0':
         case '1':
            return $old_right;

         default :
            return 0;
      }
   }


   /**
    * @since 0.85
    * Migration rights from old system to the new one for one profile
    *
    * @param $profiles_id the profile ID
    */
   static function migrateOneProfile($profiles_id) {
      global $DB;
      //Cannot launch migration if there's nothing to migrate...
      if (!$DB->tableExists('glpi_plugin_vip_profiles')) {
         return true;
      }

      foreach ($DB->request('glpi_plugin_vip_profiles',
                            "`profiles_id`='$profiles_id'") as $profile_data) {

         $matching       = ['show_vip_tab' => 'plugin_vip'];
         $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
         foreach ($matching as $old => $new) {
            if (!isset($current_rights[$old])) {
               $right = self::translateARight($profile_data[$old]);
               switch ($new) {
                  case 'plugin_vip' :
                     $right = 0;
                     if ($profile_data[$old] == '1') {
                        $right = ALLSTANDARDRIGHT;
                     }
                     break;
               }

               $query = "UPDATE `glpi_profilerights` 
                         SET `rights`='" . $right . "' 
                         WHERE `name`='$new' AND `profiles_id`='$profiles_id'";
               $DB->query($query);
            }
         }
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function initProfile() {
      global $DB;
      $profile = new self();
      $dbu = new DbUtils();
      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if ($dbu->countElementsInTable("glpi_profilerights",
                                  ["name" => $data['field']]) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

      //Migration old rights in new ones
       $query = array(
           'SELECT' => 'id',
           'FROM' => 'glpi_profiles'
       );
      foreach ($DB->request($query) as $prof) {
         self::migrateOneProfile($prof['id']);
      }
      $query = array(
          'SELECT' => '*',
          'FROM' => 'glpi_profilerights',
          'WHERE' => array(
              'AND' => array(
                  'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                  'name' => array('LIKE' => '%plugin_vip%')
              )
          ),
      );
      foreach ($DB->request($query) as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function changeProfile() {
      global $DB;
       $query = array(
           'SELECT' => '*',
           'FROM' => 'glpi_profilerights',
           'WHERE' => array(
               'AND' => array(
                   'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                   'name' => array('LIKE' => '%plugin_vip%')
               )
           ),
       );
      foreach ($DB->request($query) as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }

   }

   static function createFirstAccess($profiles_id) {

      $rights = ['plugin_vip' => ALLSTANDARDRIGHT];

      self::addDefaultProfileInfos($profiles_id,
                                   $rights, true);

   }

   /**
    * @param $profile
    **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {

      $profileRight = new ProfileRight();
      $dbu = new DbUtils();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                  ["profiles_id" => $profiles_id,
                                   "name"        => $right]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights',
                                   ["profiles_id" => $profiles_id,
                                    "name"        => $right])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }

}
