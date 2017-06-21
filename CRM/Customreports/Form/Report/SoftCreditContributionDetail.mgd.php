<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Customreports_Form_Report_SoftCreditContributionDetail',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'Customreports Soft Credit Contribution Detail',
      'description' => 'Generates a detail view of contributions by soft credited contacts, including all contact and contribution fields. (com.crusonweb.nynjtc.customreports)',
      'class_name' => 'CRM_Customreports_Form_Report_SoftCreditContributionDetail',
      'report_url' => 'com.crusonweb.nynjtc.customreports/softcreditcontributiondetail',
      'component' => 'CiviContribute',
    ),
  ),
);
