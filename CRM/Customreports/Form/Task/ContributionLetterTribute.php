<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterTribute extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterTribute';
  protected $templateTitle = 'Contribution Letter - Tribute Thank You';
  protected $reportName = 'TributeContributionDetail';

  public function assignExtraTokens(&$smarty, $row) {
    $smarty->assign('giver', $this->tokens['contact'][$row['giver_id']]);
    $smarty->assign('beneficiary', $this->tokens['contact'][$row['beneficiary_id']]);
  }

}