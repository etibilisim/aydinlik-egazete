<?php

namespace Drupal\aydinlik\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_product\Entity\ProductVariation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Cart Event Subscriber.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger, CartManagerInterface $cart_manager) {
    $this->messenger = $messenger;
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_ENTITY_ADD => [['addToCart', 100]]
    ];
  }

  /**
   * Alter user field values
   * 
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart add event
   * 
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function addToCart(CartEntityAddEvent $event) { //bind to checkout
    \Drupal::logger('aydinlik')->notice('Sepete ürün eklendi');
    $store_id = 1;
    $order_type = 'default';
    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $entity_manager = \Drupal::entityManager();
    $store = $entity_manager->getStorage('commerce_store')->load($store_id); 
    $cart = $cart_provider->getCart($order_type, $store);
    $total_items = count($cart-> getItems());
    if ($total_items>1){
       \Drupal::messenger()->addError('Her siparişte yalnızca bir adet abonelik satın alabilirsiniz. Lütfen "Satın Al" sayfasına dönünüz');
      $cartManager = \Drupal::service('commerce_cart.cart_manager');
      $store = \Drupal\commerce_store\Entity\Store::load(1);
      $order_type = 'default';
      $cart_provider = \Drupal::service('commerce_cart.cart_provider');
      $cart = $cart_provider->getCart($order_type, $store);
      if (!empty($cart)) {
        $cartManager->emptyCart($cart);
        return new RedirectResponse('user.login');
      }
    }
  }
}
