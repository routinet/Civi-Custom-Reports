<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterFromOrg extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterFromOrg';
  protected $templateTitle = 'Contribution Letter - From Organization';
  protected $reportName = 'FullContributionDetail';
}