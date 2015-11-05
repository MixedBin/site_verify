<?php

/**
 * @file
 * Contains \Drupal\site_verify\Controller\SiteVerifyController.
 */

namespace Drupal\site_verify\Controller;

use Drupal\Component\Utility\Settings;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    //   '#markup' => $this->t('TODO: show list of verifications.'),
    // );

    $engines = site_verify_get_engines();
    $destination = drupal_get_destination();

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
      $row['data'][] = $verification->meta ? '<span title="' . check_plain(truncate_utf8($verification->meta, 48)) . '">' . t('Yes') . '</spam>' : t('No');
      $row['data'][] = $verification->file ? l($verification->file, $verification->file) : t('None');
      $operations = array();
      $operations['edit'] = array(
        'title' => t('Edit'),
        'href' => "admin/config/search/verifications/{$verification->svid}/edit",
        'query' => $destination,
      );
      $operations['delete'] = array(
        'title' => t('Delete'),
        'href' => "admin/config/search/verifications/{$verification->svid}/delete",
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
    $verification = site_verify_load($svid);
    if ($verification['file_contents'] && $verification['engine']['file_contents']) {
      $response = new Response();
      $response->setContent($verification['file_contents']);
      return $response;
    }
    else {
      drupal_set_title(t('Verification page'));
      return t('This is a verification page for the @title search engine.', array(
        '!title' => $verification['engine']['name'],
      ));
    }
  }

}
