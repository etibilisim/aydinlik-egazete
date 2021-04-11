<?php

namespace Drupal\aydinlik\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaidSubscriber implements EventSubscriberInterface {

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * OrderPaidSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user account.
   */
  public function __construct(AccountInterface $current_user) {
    $user = $current_user;
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = ['commerce_order.order.paid' => 'onPaid'];
    return $events;
  }

  /**
   * Adds the necessary role to the customer upon successful payment
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onPaid(OrderEvent $event) {
    $user = User::load($order->uid[0]->target_id);
    if ($order->getState()->value === 'completed') {
      $user->addRole('abone');
      $user->save();
    }
    else {
      if ($user->hasRole('abone')) {
        $user->removeRole('abone');
        $user->save();
      }
    }
  }

}
