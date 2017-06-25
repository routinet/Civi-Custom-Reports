<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterPledge extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterPledge';
  protected $templateTitle = 'Contribution Letter - Pledge';
  protected $reportName = 'PledgeContributionDetail';
}