<?php

/**
 * @file
 * Contains \Drupal\site_verify\Form\SiteVerifyAdminForm.
 */

namespace Drupal\site_verify\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\CronInterface;
use Drupal\Core\KeyValueStore\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Configure cron settings for this site.
 */
class SiteVerifyAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_verify_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $record = array(), $engine = NULL, $site_verify = NULL) {
    if (!empty($site_verify)) {
      $record = site_verify_load($site_verify);
    }
    if (!isset($form_state['storage']['step'])) {
      $record += array(
        'svid' => NULL,
        'file' => '',
        'file_contents' => t('This is a verification page.'),
        'meta' => '',
        'engine' => $engine,
      );
      $form_state['storage']['record'] = $record;
      $form_state['storage']['step'] = $record['engine'] ? 2 : 1;
    }
    else {
      $record = $form_state['storage']['record'];
    }

    $form['actions'] = array('#type' => 'actions');
    switch ($form_state['storage']['step']) {
      case 1:
        $engines = site_verify_get_engines();
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
          '#description' => t('This is the full meta tag provided for verification. Note that this meta tag will only be visible in the source code of your <a href="@frontpage">front page</a>.', array('@front-page' => url('<front>'))),
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

        // Assume clean URLs unless the request tells us otherwise.
        $clean_urls = TRUE;
        try {
          $request = \Drupal::request();
          $clean_urls = $request->attributes->get('clean_urls');
        }
        catch (ServiceNotFoundException $e) {
        }
        if ($clean_urls == FALSE) {
          drupal_set_message(t('Using verification files will not work if <a href="@clean-urls">clean URLs</a> are disabled.', array('@clean-urls' => url('admin/settings/clean-url'))), 'error', FALSE);
          $form['file']['#disabled'] = TRUE;
          $form['file_contents']['#disabled'] = TRUE;
          $form['file_upload']['#disabled'] = TRUE;
        }

        if ($record['engine']['file']) {
          //$form['#validate'][] = 'site_verify_validate_file';
          $form['#attributes'] = array('enctype' => 'multipart/form-data');
        }
        break;
    }

    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#href' => isset($_GET['destination']) ? $_GET['destination'] : 'admin/config/search/verifications',
      '#title' => t('Cancel'),
      '#weight' => 15,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    if ($form_state['storage']['record']['engine']['file']) {
      //site_verify_validate_file

    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    if ($form_state['storage']['step'] == 1) {
      // Send the form to step 2 (verification details).
      $form_state['storage']['record']['engine'] = site_verify_engine_load($form_state['values']['engine']);
      $form_state['storage']['step']++;
      $form_state['rebuild'] = TRUE;
    }
    else {
      // Save the verification to the database.
      if ($form_state['values']['svid']) {
        drupal_write_record('site_verify', $form_state['values'], array('svid'));
      }
      else {
        drupal_write_record('site_verify', $form_state['values']);
      }

      drupal_set_message(t('Verification saved.'));
      $form_state['storage'] = $form_state['rebuild'] = NULL;
      $form_state['redirect'] = 'admin/config/search/verifications';

      // Set the menu to be rebuilt.
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
  }
}
