<?php

/**
 * @file
 * Contains \Drupal\site_verify\Controller\SiteVerifyController.
 */

namespace Drupal\site_verify\Controller;

use Drupal\Component\Utility\Settings;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Site Verify module routes.
 */
class SiteVerifyController extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Constructs a SiteVerifyController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

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

    $query = $this->database->select('site_verify', 'sv');
    $query->fields('sv');
    // $query->extend('\Drupal\Core\Database\Query\TableSortExtender');
    // $query->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $verifications = $query
      // ->orderByHeader($header)
      // ->limit(50)
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
      '#empty' => t('No verifications available. <a href="@add">Add verification</a>.', array('@add' => url('admin/config/search/verifications/add'))),
    );
    // $build['verification_pager'] = array('#theme' => 'pager');
    return $build;
  }

}
