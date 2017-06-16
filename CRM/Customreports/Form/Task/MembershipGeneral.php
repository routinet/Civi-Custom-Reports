<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_MembershipGeneral extends CRM_Customreports_Form_Task_MembershipBase {
  protected $templateName = 'MembershipGeneral';
  protected $templateTitle = 'Membership - New/Renew/Return';
  protected $context = 'membership';

}