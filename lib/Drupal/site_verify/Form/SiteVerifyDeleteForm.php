<?php

/**
 * @file
 * Contains \Drupal\site_verify\Form\SiteVerifyDeleteForm.
 */


namespace Drupal\site_verify\Form;

use Drupal\Core\Form\ConfirmFormBase;

/**
 * Builds the form to delete a forum term.
 */
class SiteVerifyDeleteForm extends ConfirmFormBase {

  protected $site_verify = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_verify_confirm_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if (!empty($this->site_verify)) {
      $record = site_verify_load($this->site_verify);
      return $this->t('Are you sure you want to delete the site verification %label?', array('%label' => $record['engine']['name']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $site_verify = NULL) {

    $this->site_verify = $site_verify;
    $record = site_verify_load($this->site_verify);

    $form = parent::buildForm($form, $form_state);

    $form['record'] = array(
      '#type' => 'value',
      '#value' => $record,
    );

    // @todo Convert to getCancelRoute() after http://drupal.org/node/1974210.
    $form['actions']['cancel']['#href'] = 'admin/config/search/verifications';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $record = $form_state['values']['record'];
    db_delete('site_verify')
      ->condition('svid', $record['svid'])
      ->execute();
    drupal_set_message(t('Verification for %engine has been deleted.', array(
      '%engine' => $record['engine']['name'],
    )));
    watchdog('site_verify', 'Verification for %engine deleted.', array(
      '%engine' => $record['engine']['name'],
    ), WATCHDOG_NOTICE);
    $form_state['redirect_route']['route_name'] = 'site_verify.verifications_list';

    // Clear front page caches and set the menu to be rebuilt.
    cache()->deleteTags(array('cache_page'));
    \Drupal::service('router.builder')->setRebuildNeeded();
  }

}
