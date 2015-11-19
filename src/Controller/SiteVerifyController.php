<?php

/**
 * @file
 * Contains \Drupal\site_verify\Controller\SiteVerifyController.
 */

namespace Drupal\site_verify\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element\HtmlTag;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Site Verify module routes.
 */
class SiteVerifyController extends ControllerBase {

  /**
   * Controller content callback: Verifications List page.
   *
   * @return string
   *   Render Array
   */
  public function verificationsListPage() {
    // $build['verifications_list'] = array(
    // '#markup' => $this->t('TODO: show list of verifications.'),
    // );
    \Drupal::service('router.builder')->rebuild();

    $engines = \Drupal::service('site_verify_service')->siteVerifyGetEngines();
    $destination = \Drupal::destination()->getAsArray();

    $header = array(
      array('data' => t('Engine'), 'field' => 'engine'),
      array('data' => t('Meta tag'), 'field' => 'meta'),
      array('data' => t('File'), 'field' => 'file'),
      array('data' => t('Operations')),
    );

    $verifications = db_select('site_verify', 'sv')
      ->fields('sv')
      ->execute();

    $rows = array();
    foreach ($verifications as $verification) {
      $row = array('data' => array());
      $row['data'][] = $engines[$verification->engine]['name'];
      $row['data'][] = $verification->meta ? t('Yes') : t('No');
      $row['data'][] = $verification->file ? \Drupal::l($verification->file, Url::fromRoute('site_verify.' . $verification->file)) : t('None');
      $operations = array();
      $operations['edit'] = array(
        'title' => t('Edit'),
        'url' => Url::fromRoute('site_verify.verification_edit', array('site_verify' => $verification->svid)),
        'query' => $destination,
      );
      $operations['delete'] = array(
        'title' => t('Delete'),
        'url' => Url::fromRoute('site_verify.verification_delete', array('site_verify' => $verification->svid)),
        'query' => $destination,
      );
      $row['data']['operations'] = array(
        'data' => array(
          '#theme' => 'links',
          '#links' => $operations,
          '#attributes' => array('class' => array('links', 'inline')),
        ),
      );
      $rows[] = $row;
    }

    $build['verification_table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No verifications available. <a href="@add">Add verification</a>.', array('@add' => \Drupal::url('site_verify.verification_add'))),
    );
    // $build['verification_pager'] = array('#theme' => 'pager');
    return $build;
  }

  /**
   * Controller content callback: Verifications File content.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response containing the Verification File content.
   */
  public function verificationsFileContent($svid) {
    $verification = \Drupal::service('site_verify_service')->siteVerifyLoad($svid);
    if ($verification['file_contents'] && $verification['engine']['file_contents']) {
      $response = new Response();
      $response->setContent($verification['file_contents']);
      return $response;
    }
    else {
      $build = array();
      $build['#title'] = $this->t('Verification page');
      $build['#markup'] = $this->t('This is a verification page for the !title search engine.', array(
        '!title' => $verification['engine']['name'],
      ));

      return $build;
    }
  }

}
