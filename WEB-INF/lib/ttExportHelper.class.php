<?php
// +----------------------------------------------------------------------+
// | Anuko Time Tracker
// +----------------------------------------------------------------------+
// | Copyright (c) Anuko International Ltd. (https://www.anuko.com)
// +----------------------------------------------------------------------+
// | LIBERAL FREEWARE LICENSE: This source code document may be used
// | by anyone for any purpose, and freely redistributed alone or in
// | combination with other software, provided that the license is obeyed.
// |
// | There are only two ways to violate the license:
// |
// | 1. To redistribute this code in source form, with the copyright
// |    notice or license removed or altered. (Distributing in compiled
// |    forms without embedded copyright notices is permitted).
// |
// | 2. To redistribute modified versions of this code in *any* form
// |    that bears insufficient indications that the modifications are
// |    not the work of the original author(s).
// |
// | This license applies to this document only, not any other software
// | that it may be combined with.
// |
// +----------------------------------------------------------------------+
// | Contributors:
// | https://www.anuko.com/time_tracker/credits.htm
// +----------------------------------------------------------------------+

import('ttTeamHelper');
import('ttTimeHelper');

// ttExportHelper - this class is used to export team data to a file.
class ttExportHelper {
  var $fileName    = null;    // Name of the file with data.

  // The following arrays are maps between entity ids in the file versus the database.
  // We write to the file sequentially (1,2,3...) while in the database the entities have different ids.
  var $userMap     = array(); // User ids.
  var $projectMap  = array(); // Project ids.
  var $taskMap     = array(); // Task ids.
  var $clientMap   = array(); // Client ids.
  var $invoiceMap  = array(); // Invoice ids.
  var $customFieldMap       = array(); // Custom field ids.
  var $customFieldOptionMap = array(); // Custop field option ids.
  var $logMap      = array(); // Time log ids.

  // createDataFile creates a file with all data for a given team.
  function createDataFile($compress = false) {
    global $user;

    // Create a temporary file.
    $dirName = dirname(TEMPLATE_DIR . '_c/.');
    $tmp_file = tempnam($dirName, 'tt');

    // Open the file for writing.
    $file = fopen($tmp_file, 'wb');
    if (!$file) return false;

    // Write XML to the file.
    fwrite($file, "<?xml version=\"1.0\"?>\n");
    fwrite($file, "<pack>\n");

    // Write team info.
    fwrite($file, "<team currency=\"".$user->currency."\" lock_spec=\"".$user->lock_spec."\" lang=\"".$user->lang.
      "\" decimal_mark=\"".$user->decimal_mark."\" date_format=\"".$user->date_format."\" time_format=\"".$user->time_format.
      "\" week_start=\"".$user->week_start."\" workday_hours=\"".$user->workday_hours.
      "\" plugins=\"".$user->plugins."\" tracking_mode=\"".$user->tracking_mode."\" task_required=\"".$user->task_required.
      "\" record_type=\"".$user->record_type."\">\n");
    fwrite($file, "  <name><![CDATA[".$user->team."]]></name>\n");
    fwrite($file, "  <address><![CDATA[".$user->address."]]></address>\n");
    fwrite($file, "</team>\n");

    // Prepare user map.
    $users = ttTeamHelper::getAllUsers($user->team_id, true);
    foreach ($users as $key=>$user_item)
      $this->userMap[$user_item['id']] = $key + 1;

    // Prepare project map.
    $projects = ttTeamHelper::getAllProjects($user->team_id, true);
    foreach ($projects as $key=>$project_item)
      $this->projectMap[$project_item['id']] = $key + 1;

    // Prepare task map.
    $tasks = ttTeamHelper::getAllTasks($user->team_id, true);
    foreach ($tasks as $key=>$task_item)
      $this->taskMap[$task_item['id']] = $key + 1;

    // Prepare client map.
    $clients = ttTeamHelper::getAllClients($user->team_id, true);
    foreach ($clients as $key=>$client_item)
      $this->clientMap[$client_item['id']] = $key + 1;

    // Prepare invoice map.
    $invoices = ttTeamHelper::getAllInvoices();
    foreach ($invoices as $key=>$invoice_item)
      $this->invoiceMap[$invoice_item['id']] = $key + 1;

    // Prepare custom fields map.
    $custom_fields = ttTeamHelper::getAllCustomFields($user->team_id);
    foreach ($custom_fields as $key=>$custom_field)
      $this->customFieldMap[$custom_field['id']] = $key + 1;

    // Prepare custom field options map.
    $custom_field_options = ttTeamHelper::getAllCustomFieldOptions($user->team_id);
    foreach ($custom_field_options as $key=>$option)
      $this->customFieldOptionMap[$option['id']] = $key + 1;

    // Write users.
    fwrite($file, "<users>\n");
    foreach ($users as $user_item) {
      fwrite($file, "  <user id=\"".$this->userMap[$user_item['id']]."\" login=\"".htmlentities($user_item['login'])."\" password=\"".$user_item['password']."\" role=\"".$user_item['role']."\" client_id=\"".$this->clientMap[$user_item['client_id']]."\" rate=\"".$user_item['rate']."\" email=\"".$user_item['email']."\" status=\"".$user_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$user_item['name']."]]></name>\n");
      fwrite($file, "  </user>\n");
    }
    fwrite($file, "</users>\n");

    // Write tasks.
    fwrite($file, "<tasks>\n");
    foreach ($tasks as $task_item) {
      fwrite($file, "  <task id=\"".$this->taskMap[$task_item['id']]."\" status=\"".$task_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$task_item['name']."]]></name>\n");
      fwrite($file, "    <description><![CDATA[".$task_item['description']."]]></description>\n");
      fwrite($file, "  </task>\n");
    }
    fwrite($file, "</tasks>\n");
    unset($tasks);

    // Write projects.
    fwrite($file, "<projects>\n");
    foreach ($projects as $project_item) {
      if($project_item['tasks']){
        $tasks = explode(',', $project_item['tasks']);
        $tasks_mapped = array();
        foreach ($tasks as $item)
          $tasks_mapped[] = $this->taskMap[$item];
        $tasks_str = implode(',', $tasks_mapped);
      }
      fwrite($file, "  <project id=\"".$this->projectMap[$project_item['id']]."\" tasks=\"".$tasks_str."\" status=\"".$project_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$project_item['name']."]]></name>\n");
      fwrite($file, "    <description><![CDATA[".$project_item['description']."]]></description>\n");
      fwrite($file, "  </project>\n");
    }
    fwrite($file, "</projects>\n");
    unset($projects);

    // Write user to project binds.
    fwrite($file, "<user_project_binds>\n");
    $user_binds = ttTeamHelper::getUserToProjectBinds($user->team_id);
    foreach ($user_binds as $bind) {
      $user_id = $this->userMap[$bind['user_id']];
      $project_id = $this->projectMap[$bind['project_id']];
      fwrite($file, "  <user_project_bind user_id=\"{$user_id}\" project_id=\"{$project_id}\" rate=\"".$bind['rate']."\" status=\"".$bind['status']."\"/>\n");
    }
    fwrite($file, "</user_project_binds>\n");
    unset($user_binds);

    // Write clients.
    fwrite($file, "<clients>\n");
    foreach ($clients as $client_item) {
      if($client_item['projects']){
        $projects = explode(',', $client_item['projects']);
        $projects_mapped = array();
        foreach ($projects as $item)
          $projects_mapped[] = $this->projectMap[$item];
        $projects_str = implode(',', $projects_mapped);
      }
      fwrite($file, "  <client id=\"".$this->clientMap[$client_item['id']]."\" tax=\"".$client_item['tax']."\" projects=\"".$projects_str."\" status=\"".$client_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$client_item['name']."]]></name>\n");
      fwrite($file, "    <address><![CDATA[".$client_item['address']."]]></address>\n");
      fwrite($file, "  </client>\n");
    }
    fwrite($file, "</clients>\n");
    unset($clients);

    // Write invoices.
    fwrite($file, "<invoices>\n");
    foreach ($invoices as $invoice_item) {
      fwrite($file, "  <invoice id=\"".$this->invoiceMap[$invoice_item['id']]."\" date=\"".$invoice_item['date']."\" client_id=\"".$this->clientMap[$invoice_item['client_id']]."\" status=\"".$invoice_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$invoice_item['name']."]]></name>\n");
      fwrite($file, "  </invoice>\n");
    }
    fwrite($file, "</invoices>\n");
    unset($invoices);

    // Write custom fields.
    fwrite($file, "<custom_fields>\n");
    foreach ($custom_fields as $custom_field) {
      fwrite($file, "  <custom_field id=\"".$this->customFieldMap[$custom_field['id']]."\" type=\"".$custom_field['type']."\" required=\"".$custom_field['required']."\" status=\"".$custom_field['status']."\">\n");
      fwrite($file, "    <label><![CDATA[".$custom_field['label']."]]></label>\n");
      fwrite($file, "  </custom_field>\n");
    }
    fwrite($file, "</custom_fields>\n");
    unset($custom_fields);

    // Write custom field options.
    fwrite($file, "<custom_field_options>\n");
    foreach ($custom_field_options as $option) {
      fwrite($file, "  <custom_field_option id=\"".$this->customFieldOptionMap[$option['id']]."\" field_id=\"".$this->customFieldMap[$option['field_id']]."\">\n");
      fwrite($file, "    <value><![CDATA[".$option['value']."]]></value>\n");
      fwrite($file, "  </custom_field_option>\n");
    }
    fwrite($file, "</custom_field_options>\n");
    unset($custom_field_options);

    // Write monthly quotas.
    $quotas = ttTeamHelper::getMonthlyQuotas($user->team_id);
    fwrite($file, "<monthly_quotas>\n");
    foreach ($quotas as $quota) {
      fwrite($file, "  <monthly_quota year=\"".$quota['year']."\" month=\"".$quota['month']."\" quota=\"".$quota['quota']."\"/>\n");
    }
    fwrite($file, "</monthly_quotas>\n");

    // Write time log entries.
    fwrite($file, "<log>\n");
    $key = 0;
    foreach ($users as $user_item) {
      $records = ttTimeHelper::getAllRecords($user_item['id']);
      foreach ($records as $record) {
        $key++;
        $this->logMap[$record['id']] = $key;
        fwrite($file, "  <log_item id=\"$key\" timestamp=\"".$record['timestamp']."\" user_id=\"".$this->userMap[$record['user_id']]."\" date=\"".$record['date']."\" start=\"".$record['start']."\" finish=\"".$record['finish']."\" duration=\"".($record['start']?"":$record['duration'])."\" client_id=\"".$this->clientMap[$record['client_id']]."\" project_id=\"".$this->projectMap[$record['project_id']]."\" task_id=\"".$this->taskMap[$record['task_id']]."\" invoice_id=\"".$this->invoiceMap[$record['invoice_id']]."\" billable=\"".$record['billable']."\" status=\"".$record['status']."\">\n");
        fwrite($file, "    <comment><![CDATA[".$record['comment']."]]></comment>\n");
        fwrite($file, "  </log_item>\n");
      }
    }
    fwrite($file, "</log>\n");
    unset($records);

    // Write custom field log.
    $custom_field_log = ttTeamHelper::getCustomFieldLog($user->team_id);
    fwrite($file, "<custom_field_log>\n");
    foreach ($custom_field_log as $entry) {
      fwrite($file, "  <custom_field_log_entry log_id=\"".$this->logMap[$entry['log_id']]."\" field_id=\"".$this->customFieldMap[$entry['field_id']]."\" option_id=\"".$this->customFieldOptionMap[$entry['option_id']]."\" status=\"".$entry['status']."\">\n");
      fwrite($file, "    <value><![CDATA[".$entry['value']."]]></value>\n");
      fwrite($file, "  </custom_field_log_entry>\n");
    }
    fwrite($file, "</custom_field_log>\n");
    unset($custom_field_log);

    // Write expense items.
    $expense_items = ttTeamHelper::getExpenseItems($user->team_id);
    fwrite($file, "<expense_items>\n");
    foreach ($expense_items as $expense_item) {
      fwrite($file, "  <expense_item date=\"".$expense_item['date']."\" user_id=\"".$this->userMap[$expense_item['user_id']]."\" client_id=\"".$this->clientMap[$expense_item['client_id']]."\" project_id=\"".$this->projectMap[$expense_item['project_id']]."\" cost=\"".$expense_item['cost']."\" invoice_id=\"".$this->invoiceMap[$expense_item['invoice_id']]."\" status=\"".$expense_item['status']."\">\n");
      fwrite($file, "    <name><![CDATA[".$expense_item['name']."]]></name>\n");
      fwrite($file, "  </expense_item>\n");
    }
    fwrite($file, "</expense_items>\n");
    unset($expense_items);

    // Write fav reports.
    fwrite($file, "<fav_reports>\n");
    $fav_reports = ttTeamHelper::getFavReports($user->team_id);
    foreach ($fav_reports as $fav_report) {
      $user_list = '';
      if (strlen($fav_report['users']) > 0) {
        $arr = explode(',', $fav_report['users']);
        foreach ($arr as $k=>$v) {
          if (array_key_exists($arr[$k], $this->userMap))
            $user_list .= (strlen($user_list) == 0? '' : ',').$this->userMap[$v];
        }
      }
      fwrite($file, "\t<fav_report user_id=\"".$this->userMap[$fav_report['user_id']]."\"".
        " client_id=\"".$this->clientMap[$fav_report['client_id']]."\"".
        " cf_1_option_id=\"".$this->customFieldOptionMap[$fav_report['cf_1_option_id']]."\"".
        " project_id=\"".$this->projectMap[$fav_report['project_id']]."\"".
        " task_id=\"".$this->taskMap[$fav_report['task_id']]."\"".
        " billable=\"".$fav_report['billable']."\"".
        " users=\"".$user_list."\"".
        " period=\"".$fav_report['period']."\"".
        " period_start=\"".$fav_report['period_start']."\"".
        " period_end=\"".$fav_report['period_end']."\"".
        " show_client=\"".$fav_report['show_client']."\"".
        " show_invoice=\"".$fav_report['show_invoice']."\"".
        " show_project=\"".$fav_report['show_project']."\"".
        " show_start=\"".$fav_report['show_start']."\"".
        " show_duration=\"".$fav_report['show_duration']."\"".
        " show_cost=\"".$fav_report['show_cost']."\"".
        " show_task=\"".$fav_report['show_task']."\"".
        " show_end=\"".$fav_report['show_end']."\"".
        " show_note=\"".$fav_report['show_note']."\"".
        " show_custom_field_1=\"".$fav_report['show_custom_field_1']."\"".
        " group_by=\"".$fav_report['group_by']."\"".
        " show_totals_only=\"".$fav_report['show_totals_only']."\">\n");
      fwrite($file, "\t\t<name><![CDATA[".$fav_report["name"]."]]></name>\n");
      fwrite($file, "\t</fav_report>\n");
    }
    fwrite($file, "</fav_reports>\n");
    unset($fav_reports);

    // Cleanup.
    unset($users);
    $this->userMap = array();
    $this->projectMap = array();
    $this->taskMap = array();

    fwrite($file, "</pack>\n");
    fclose($file);

    if ($compress) {
      $this->fileName = tempnam($dirName, 'tt');
      $this->compress($tmp_file, $this->fileName);
      unlink($tmp_file);
    } else
      $this->fileName = $tmp_file;

    return true;
  }

  // getFileName - returns file name.
  function getFileName() {
    return $this->fileName;
  }

  // compress - compresses the content of the $in file into $out file.
  function compress($in, $out) {
    // Initial checks of file names and permissions.
    if (!file_exists($in) || !is_readable ($in))
      return false;
    if ((!file_exists($out) && !is_writable(dirname($out))) || (file_exists($out) && !is_writable($out)))
      return false;

    $in_file = fopen($in, 'rb');

    if (function_exists('bzopen')) {
      if (!$out_file = bzopen($out, 'w'))
        return false;

      while (!feof ($in_file)) {
        $buffer = fread($in_file, 4096);
        bzwrite($out_file, $buffer, 4096);
      }
      bzclose($out_file);
    }
    fclose ($in_file);
    return true;
  }
}
