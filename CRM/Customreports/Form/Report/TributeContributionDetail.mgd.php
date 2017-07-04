<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more Summarys, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Customreports_Form_Report_TributeContributionDetail',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'Customreports Tribute Contribution Detail',
      'description' => 'Generates a detail view of tribute contributions, to/from contact information. (com.crusonweb.nynjtc.customreports)',
      'class_name' => 'CRM_Customreports_Form_Report_TributeContributionDetail',
      'report_url' => 'com.crusonweb.nynjtc.customreports/tributecontributiondetail',
      'component' => 'CiviContribute',
    ),
  ),
);
