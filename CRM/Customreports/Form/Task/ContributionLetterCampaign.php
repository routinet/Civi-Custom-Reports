<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterCampaign extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterCampaign';
  protected $templateTitle = 'Contribution Letter - Campaign';
  protected $reportName = 'FullContributionDetail';
}