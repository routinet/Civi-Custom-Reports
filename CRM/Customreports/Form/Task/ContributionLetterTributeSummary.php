<?php

/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterTributeSummary extends CRM_Customreports_Form_Task_ContributeBase {

  protected $templateName = 'ContributionLetterTributeSummary';

  protected $templateTitle = 'Contribution Letter - Tribute Summary';

  protected $reportName = 'SoftCreditContributionDetail';

  public function loadReportData() {
    // Standard load of the report data.
    parent::loadReportData();

    // We need to add all "point of contact" contacts as related to the
    // beneficiary of the contribution.  We'll just use the existing array
    // of contact IDs as the basis of the search.
    $related_ids = [];
    $query = "SELECT r.contact_id_a AS related_id, ct.id AS contact_id " .
      "FROM civicrm_contact ct LEFT JOIN civicrm_relationship r ON ct.id=r.contact_id_b " .
      "LEFT JOIN civicrm_relationship_type rt ON r.relationship_type_id=rt.id " .
      "AND rt.name_a_b='Has point of contact' WHERE ct.id IN (" .
      implode(',', $this->_contactIds) . ")";
    $dao   = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      if (!(empty($dao->contact_id) || empty($dao->related_id))) {
        $related_ids[$dao->contact_id] = (int) $dao->related_id;
      }
    }

    // For each row already loaded, assign the "related_id" field, according to
    // the contact ID of the beneficiary.  To avoid an additional side-load of
    // these contacts, add their IDs to the array of contacts to be loaded.
    foreach ($this->report_data as $rownum => &$data) {
      $data['civicrm_contribution_related_id'] =
        empty($related_ids[$data['civicrm_contact_id']]) ? 0 : $related_ids[$data['civicrm_contact_id']];
      $this->_contactIds[] = $related_ids[$data['civicrm_contact_id']];
    }
    H::log("child " . __FUNCTION__ . " _contactIDs=\n" . var_export($this->_contactIds, 1));
  }

  // To avoid rewiring ContributeBase::getHtmlFromSmarty(), we need to
  // reorganize the token structure already established.  This letter prints
  // one page per summary recipient.
  public function customizeTokenDetails() {
    $new_tokens = [];
    foreach ($this->tokens['component'] as $contribution_id => $token_row) {
      $related_id = $token_row['related_id'];
      if ($related_id) {
        $one_row = empty($new_tokens[$related_id])
          ? $this->tokens['contact'][$related_id]
          : $new_tokens[$related_id];
        if (empty($one_row['beneficiary_id'])) {
          $one_row['beneficiary_id'] = $token_row['id'];
        }
        if (empty($one_row['min_date'])) {
          $one_row['min_date'] = time();
        }
        $one_row['min_date'] = min($one_row['min_date'], $token_row['receive_date']);
        if (empty($one_row['max_date'])) {
          $one_row['max_date'] = 0;
        }
        $one_row['max_date'] = max($one_row['max_date'], $token_row['receive_date']);
        if (empty($one_row['summary'][$contribution_id])) {
          $giver_row = $this->tokens['contact'][$token_row['receiver_id']];
          $address_summary = [
            $giver_row['display_name'],
            $giver_row['street_address'],
            $giver_row['city'],
            $giver_row['state_province'],
            $giver_row['postal_code'],
          ];
          $giver_row['address_summary'] = implode(' ', $address_summary);
          $giver_row['amount'] = $token_row['net_amount'];
          $giver_row['contribution_date'] = $token_row['receive_date'];
          $one_row['summary'][$contribution_id] = $giver_row;
        }
        $new_tokens[$related_id] = $one_row;
      }
    }

    $this->tokens['raw_component'] = $this->tokens['component'];
    $this->tokens['component'] = $new_tokens;
    H::log("After customize=\n".var_export($this->tokens['component'],1));
  }

  public function assignExtraTokens(&$smarty, $row) {
    $smarty->assign('beneficiary', $this->tokens['contact'][$row['beneficiary_id']]);
  }
}