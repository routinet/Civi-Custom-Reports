<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterStandard extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterStandard';
  protected $templateTitle = 'Contribution Letter - Standard';
  protected $context = 'contribution';

}