<?php

namespace Drupal\aydinlik\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Assigns the proper subscription when an order is placed.
 */
class OrderPaySubscriber implements EventSubscriberInterface {

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * The entity type manager
   * 
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * NodeAccessSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $this->current_user
   *   Current user account.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->current_user = $this->current_user;
    $this->entity_type_manager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.place.post_transition' => ['addSubscription', -100],
      'commerce_order.place.pre_transition' => ['removeSubscription', -100]
    ];
    return $events;
  }

  /**
   * Adds subscription upon successful payment.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event we subscribed to.
   */
  public function addSubscription(WorkflowTransitionEvent $event) {
    $dateTime = \DateTime::createFromFormat('Y-m-d',date('Y-m-d'));
    $today = $dateTime->format('Y-m-d');
    $this->current_user = User::load(\Drupal::currentUser()->id());
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $order_items = $order->getItems();
    $order_item = reset($order_items);
    $product_variation = $order_item->getPurchasedEntity();
    $sku = $product_variation->getSku();
    if (str_contains ($sku, 'aylik')) {
      $sku_substr = substr($sku, 0, 14);
    }
    elseif (str_contains ($sku, 'yillik')) {
      $sku_substr = substr($sku, 0, 15);   
    }
    $from = ["aylik", "yillik", "-"];
    $to = ["Aylık", "Yıllık", " "];
    $name = ucwords(str_replace($from, $to, $sku_substr));
    $epaper_subscription = $this->entity_type_manager->getStorage('taxonomy_term')->loadByProperties(['name' => 'E-Gazete Aboneliği']);
    $subscription_duration = $this->entity_type_manager->getStorage('taxonomy_term')->loadByProperties(['name' => $name]);
    $this->current_user->field_abonelik_suresi[0] = ['target_id' => reset($subscription_duration)->id()];
    switch ($sku_substr) {
        case 'aylik-abonelik':
            if (!empty($this->current_user->field_abonelik_turu)) {
              unset($this->current_user->field_abonelik_turu);
            }
            if ($this->current_user->field_abonelik_baslangic_tarihi->value == null) {
              $this->current_user->field_abonelik_baslangic_tarihi->value = $today;
            }
            $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d', strtotime('+1 month'));
            $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
            $this->current_user->save();
            break;
        case '3-aylik-abonelik':
            if (!empty($this->current_user->field_abonelik_turu)) {
              unset($this->current_user->field_abonelik_turu);
            }
            if ($this->current_user->field_abonelik_baslangic_tarihi->value == null) {
              $this->current_user->field_abonelik_baslangic_tarihi->value = $today;
            }
            $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d', strtotime('+3 months'));
            $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
            $this->current_user->save();
            break;
        case '6-aylik-abonelik':
            if (!empty($this->current_user->field_abonelik_turu)) {
              unset($this->current_user->field_abonelik_turu);
            }
            if ($this->current_user->field_abonelik_baslangic_tarihi->value == null) {
              $this->current_user->field_abonelik_baslangic_tarihi->value = $today;
            }
            $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d', strtotime('+6 months'));
            $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
            $this->current_user->save();
            break;
        case 'yillik-abonelik':
            if (!empty($this->current_user->field_abonelik_turu)) {
              unset($this->current_user->field_abonelik_turu);
            }
            if ($this->current_user->field_abonelik_baslangic_tarihi->value == null) {
              $this->current_user->field_abonelik_baslangic_tarihi->value = $today;
            }
            $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d', strtotime('+1 year'));
            $earchive_subscription = $this->entity_type_manager->getStorage('taxonomy_term')->loadByProperties(['name' => 'E-Arşiv Aboneliği']);
            $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
            $this->current_user->field_abonelik_turu[] = ['target_id' => reset($earchive_subscription)->id()];
            $this->current_user->save();
        default:
            # code...
            break;
    }
  }

  /*
   * Removes subscription upon pending payment.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event we subscribed to.
   */
  public function removeSubscription(WorkflowTransitionEvent $event) {
    /*$this->current_user = User::load(\Drupal::currentUser()->id());
    if ($this->current_user->hasRole('abone')) {
      $this->current_user->removeRole('abone');
      $this->current_user->save();
    }*/
  }

}
