<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterFromOrg extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterFromOrg';
  protected $templateTitle = 'Contribution Letter - From Organization';
  protected $reportName = 'FullContributionDetail';

  /**
   * Loads the letter recipient (through related_id) and assigns its
   * reference back into the respective contribution set.
   */
  public function loadReportData() {
    // Standard load of the report data.
    parent::loadReportData();

    // We need to add all "point of contact" contacts as related to the
    // organization making the contribution.  We'll just use the existing
    // array of contact IDs as the basis of the search.
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

  public function assignExtraTokens(&$smarty, $row) {
    $smarty->assign('related', $this->tokens['contact'][$row['related_id']]);
  }
}