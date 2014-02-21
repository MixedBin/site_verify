<?php

/**
 * @file
 * Contains \Drupal\site_verify\EventSubscriber\SiteVerifySubscriber.
 */

namespace Drupal\site_verify\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SiteVerifySubscriber implements EventSubscriberInterface {

  /**
   * Add the verification meta tags to the front page.
   */
  public function checkForMetatag(GetResponseEvent $event) {
    if (drupal_is_front_page()) {
      $meta_tags = db_select('site_verify', 'site_verify')
        ->fields('site_verify', array('svid', 'meta'))
        ->condition('meta', '', '<>')
        ->execute()
        ->fetchAllKeyed();
      foreach ($meta_tags as $svid => $meta_tag) {
        $data = array(
          '#type' => 'markup',
          '#markup' => $meta_tag . PHP_EOL,
        );
        drupal_add_html_head($data, 'site_verify:' . $svid);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForMetatag');
    return $events;
  }
}
