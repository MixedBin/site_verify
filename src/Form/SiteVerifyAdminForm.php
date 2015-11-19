<?php

/**
 * @file
 * Contains \Drupal\site_verify\Form\SiteVerifyAdminForm.
 */

namespace Drupal\site_verify\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure cron settings for this site.
 */
class SiteVerifyAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['site_verify.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_verify_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $record = array(), $engine = NULL, $site_verify = NULL) {
    if (!empty($site_verify)) {
      $record = \Drupal::service('site_verify_service')->siteVerifyLoad($site_verify);
    }

    $storage = $form_state->getStorage();
    if (!isset($storage['step'])) {
      $record += array(
        'svid' => NULL,
        'file' => '',
        'file_contents' => t('This is a verification page.'),
        'meta' => '',
        'engine' => $engine,
      );
      !empty($record['engine']) ? $form_state->setStorage(array('step' => 2, 'record' => $record)) : $form_state->setStorage(array('step' => 1, 'record' => $record));
    }
    else {
      $record = $storage['record'];
    }

    $form['actions'] = array('#type' => 'actions');

    $storage = $form_state->getStorage();
    switch ($storage['step']) {
      case 1:
        $engines = \Drupal::service('site_verify_service')->siteVerifyGetEngines();
        $options = array();
        foreach ($engines as $key => $engine) {
          $options[$key] = $engine['name'];
        }
        asort($options);

        $form['engine'] = array(
          '#type' => 'select',
          '#title' => t('Search engine'),
          '#options' => $options,
        );
        break;

      case 2:
        $form['svid'] = array(
          '#type' => 'value',
          '#value' => $record['svid'],
        );
        $form['engine'] = array(
          '#type' => 'value',
          '#value' => $record['engine']['key'],
        );
        $form['engine_name'] = array(
          '#type' => 'item',
          '#title' => t('Search engine'),
          '#markup' => $record['engine']['name'],
        );
        $form['#engine'] = $record['engine'];

        $form['meta'] = array(
          '#type' => 'textfield',
          '#title' => t('Verification META tag'),
          '#default_value' => $record['meta'],
          '#description' => t('This is the full meta tag provided for verification. Note that this meta tag will only be visible in the source code of your <a href="@frontpage">front page</a>.', array('@frontpage' => \Drupal::url('<front>'))),
          '#element_validate' => $record['engine']['meta_validate'],
          '#access' => $record['engine']['meta'],
          '#maxlength' => NULL,
          '#attributes' => array(
            'placeholder' => $record['engine']['meta_example'],
          ),
        );

        $form['file_upload'] = array(
          '#type' => 'file',
          '#title' => t('Upload an existing verification file'),
          '#description' => t('If you have been provided with an actual file, you can simply upload the file.'),
          '#access' => $record['engine']['file'],
        );

        $form['file'] = array(
          '#type' => 'textfield',
          '#title' => t('Verification file'),
          '#default_value' => $record['file'],
          '#description' => t('The name of the HTML verification file you were asked to upload.'),
          '#element_validate' => $record['engine']['file_validate'],
          '#access' => $record['engine']['file'],
          '#attributes' => array(
            'placeholder' => $record['engine']['file_example'],
          ),
        );

        $form['file_contents'] = array(
          '#type' => 'textarea',
          '#title' => t('Verification file contents'),
          '#default_value' => $record['file_contents'],
          '#element_validate' => $record['engine']['file_contents_validate'],
          '#wysiwyg' => FALSE,
          '#access' => $record['file_contents'],
        );

        if ($record['engine']['file']) {
          $form['#attributes'] = array('enctype' => 'multipart/form-data');
        }
        break;
    }

    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => t('Cancel'),
      '#url' => isset($_GET['destination']) ? $_GET['destination'] : Url::fromRoute('site_verify.verifications_list'),
      '#weight' => 15,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $values = &$form_state->getValues();

    // Check META tag.
    $form_state->setValue('meta', trim($values['meta']));
    if ($values['meta'] != '' && !preg_match('/<meta (.*)>/', $values['meta'])) {
      $form_state->setErrorByName('meta', $this->t('Only META tags are supported at this moment'));
    }

    // Check verification file.
    if ($storage['record']['engine']['file']) {

      // Import the uploaded verification file.
      $validators = array('file_validate_extensions' => array());
      if ($file = file_save_upload('file_upload', $validators, FALSE, 0, FILE_EXISTS_REPLACE)) {
        $contents = @file_get_contents($file->getFileUri());

        $file->delete();
        if ($contents === FALSE) {
          drupal_set_message(t('The verification file import failed, because the file %filename could not be read.', array('%filename' => $file->getFilename())), 'error');
        }
        else {
          $values['file'] = $file->getFilename();
          $values['file_contents'] = $contents;
        }
      }

      if ($values['file']) {
        $existing_file = db_query("SELECT svid FROM {site_verify} WHERE LOWER(file) = LOWER(:file)", array(
          ':file' => $values['file'],
        ))->fetchField();
        if ($existing_file && $values['svid'] !== $existing_file) {
          $form_state->setErrorByName('file', $this->t('The file %filename is already being used in another verification.', array('%filename' => $values['file'])));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();

    if ($storage['step'] == 1) {
      // Send the form to step 2 (verification details).
      $form_state->setStorage(array(
        'record' => array(
          'engine' => \Drupal::service('site_verify_service')->siteVerifyEngineLoad($form_state->getValue('engine')),
        ),
        'step' => 2,
      ));
      $form_state->setRebuild(TRUE);
    }
    else {
      // Save the verification to the database.
      \Drupal::database()->merge('site_verify')
        ->key('svid', $form_state->getValue('svid'))
        ->fields(array(
          'engine' => $form_state->getValue('engine'),
          'file' => $form_state->getValue('file'),
          'file_contents' => $form_state->getValue('file_contents'),
          'meta' => $form_state->getValue('meta'),
        ))
        ->execute();

      drupal_set_message(t('Verification saved.'));

      $form_state->setStorage(array());
      $form_state->setRebuild(NULL);
      $form_state->setRedirect('site_verify.verifications_list');

      // Set the menu to be rebuilt.
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
  }

}
