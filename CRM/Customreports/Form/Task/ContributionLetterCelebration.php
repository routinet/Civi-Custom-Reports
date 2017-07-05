<?php

/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterCelebration extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterCelebration';
  protected $templateTitle = 'Contribution Letter - Celebration Thank You';
  protected $reportName = 'TributeContributionDetail';

  public function assignExtraTokens(&$smarty, $row) {
    $smarty->assign('giver', $this->tokens['contact'][$row['giver_id']]);
    $smarty->assign('beneficiary', $this->tokens['contact'][$row['beneficiary_id']]);

    $credit_types = array();
    $actual_type_name = '';
    CRM_Core_OptionGroup::getAssoc('soft_credit_type', $credit_types, TRUE);
    H::log("dumping credit types=\n".var_export($credit_types,1));
    foreach ($credit_types as $key => $type) {
      if ($type['value'] == $row['soft_credit_type_id']) {
        $actual_type_name = $type['label'];
      }
    }
    if ($actual_type_name) {
      $smarty->assign('soft_credit_type', $actual_type_name);
    }
    H::log("dumping actual type name=\n".var_export($actual_type_name,1));
  }

}