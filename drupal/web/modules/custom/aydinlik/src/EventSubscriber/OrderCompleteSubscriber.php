<?php

namespace Drupal\aydinlik\EventSubscriber;

use DateTime;
use Drupal;
use Drupal\iyzipay\Config;
use Iyzipay\Model\Customer;
use Iyzipay\Model\PaymentCard;
use Iyzipay\Model\Subscription\SubscriptionActivate;
use Iyzipay\Model\Subscription\SubscriptionCreate;
use Iyzipay\Model\Subscription\SubscriptionDetails;
use Iyzipay\Request\Subscription\SubscriptionActivateRequest;
use Iyzipay\Request\Subscription\SubscriptionCreateRequest;
use Iyzipay\Request\Subscription\SubscriptionDetailsRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\commerce_order\Event\OrderEvent;
use Iyzipay\Model\ThreedsPayment;
use Iyzipay\Options;
use Iyzipay\Model\Locale;
use Iyzipay\Request\CreateThreedsPaymentRequest;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment\Entity\Payment;
use Iyzipay\Request\RetrieveInstallmentInfoRequest;
use Iyzipay\Model\InstallmentInfo;
/**
 * Class OrderCompleteSubscriber.
 *
 * @package Drupal\aydinlik
 */
class OrderCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var EntityTypeManager
   */
  protected $current_user;
  protected $entityQuery;
  protected $entityTypeManager;
  private $entity;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = ['orderCompleteHandler'];

    return $events;
  }

  /**
   * This method is called whenever the commerce_order.place.post_transition event is
   * dispatched.
   *
   * @param WorkflowTransitionEvent $event
   */
  public function orderCompleteHandler(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    //$order = $event->getEntity();
    $orders = Order::loadMultiple();
    $order = end($orders);
    $this->current_user = User::load($order->uid->target_id);
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s',date('Y-m-d\TH:i:s'));
    $today = new DateTime();
    $today = new DateTime();
    date_default_timezone_set('UTC');
    $today = new \DateTime('now', new \DateTimeZone('UTC'));
    $today_ts = $today->getTimestamp();
    $this->entity;
    // @var \Drupal\commerce_order\Entity\OrderInterface $order
    $order_id = $order->id();
    $order_price = $order->total_price->number;
    $order_items = $order->getItems();
    $order_item = reset($order_items);
    $product_variation = $order_item->getPurchasedEntity();
    $sku = $product_variation->getSku();
    $config = Drupal::configFactory()->getEditable('iyzipay.settings');
    $bin_number = substr($config->get('number'),0,6);
    # create request class
    $request = new RetrieveInstallmentInfoRequest();
    $request->setLocale(Locale::TR);
    $request->setConversationId($order_id);
    $request->setBinNumber($bin_number);
    $request->setPrice("$order_price");

    # make request
    $installmentInfo = InstallmentInfo::retrieve($request, Config::options());

    $cardType = $installmentInfo->getInstallmentDetails()[0]->getCardType();
    $paymentStatus = $installmentInfo->getStatus();

    $from = ["aylik", "yillik", "ogrenci", "avrupa", "disi","-"];
    $to = ["Ayl??k", "Y??ll??k", "????renci", "Avrupa", "D??????", " "];
    $name = ucwords(str_replace($from, $to, $sku));
    if($paymentStatus == "success"){
      $epaper_subscription = Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'E-Gazete Aboneli??i']);
      $subscription_duration = Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $name]);
      $this->current_user->field_abonelik_suresi[0] = ['target_id' => reset($subscription_duration)->id()];
      switch ($sku) {
        case 'aylik-abonelik':
        case 'aylik-abonelik-ogrenci':
          if (!empty($this->current_user->field_abonelik_turu)) {
            unset($this->current_user->field_abonelik_turu);
          }
          $this->current_user->set('field_son_abonelik_islem_tarihi', date('Y-m-d\TH:i:s',$today_ts));
          if ($this->current_user->field_abonelik_baslangic_tarihi->value == NULL) {
            $this->current_user->set('field_abonelik_baslangic_tarihi', date('Y-m-d\TH:i:s',$today_ts));
          }
          $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d\TH:i:s', strtotime('+1 month'));
          $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
          if ($this->current_user->field_taahhut_tarihi->value != NULL){
            $this->current_user->field_abonelik_turu[] = ['target_id' => 3];
          }
          $this->current_user->addRole('abone');
          $this->current_user->save();
          break;
        case 'yillik-abonelik':
        case 'yillik-abonelik-ogrenci':
        case 'yillik-abonelik-avrupa':
        case 'yillik-abonelik-avrupa-disi':
          if (!empty($this->current_user->field_abonelik_turu)) {
            unset($this->current_user->field_abonelik_turu);
          }
          $this->current_user->set('field_son_abonelik_islem_tarihi', date('Y-m-d\TH:i:s',$today_ts));
          if ($this->current_user->field_abonelik_baslangic_tarihi->value == NULL) {
            $this->current_user->set('field_abonelik_baslangic_tarihi', date('Y-m-d\TH:i:s',$today_ts));
          }
          $this->current_user->field_abonelik_bitis_tarihi->value = date('Y-m-d\TH:i:s', strtotime('+1 year'));
          $earchive_subscription = Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'E-Ar??iv Aboneli??i']);
          $this->current_user->field_abonelik_turu[] = ['target_id' => reset($epaper_subscription)->id()];
          $this->current_user->field_abonelik_turu[] = ['target_id' => reset($earchive_subscription)->id()];
          $this->current_user->addRole('abone');
          $this->current_user->save();
        default:
          # code...
          break;
      }
    }
    if ($paymentStatus == "success"){
      if ($cardType != NULL) {
        if (!str_contains($cardType, 'DEBIT')) {
          $order_items = $order->getItems();
          $order_item = reset($order_items);
          $product_variation = $order_item->getPurchasedEntity();
          $sku_field = $product_variation->field_sku->value;
          //$this->current_user = User::load(Drupal::currentUser()->id());
          /* $ad_soyad = $this->>$this->current_user->field_adiniz_soyadiniz->value;
          $parts = explode(' ', $ad_soyad);
          $last = array_pop($parts);
          $parts = array(implode(' ', $parts), $last); */
          $ad = $this->current_user->field_adiniz->value;
          $soyad = $this->current_user->field_soyadiniz->value;
          $ad_soyad = $ad.' '.$soyad;
          $request = new SubscriptionCreateRequest();
          $request->setLocale("tr");
          $request->setConversationId($order_id);
          $request->setPricingPlanReferenceCode($sku_field);
          $request->setSubscriptionInitialStatus("PENDING");
          $paymentCard = new PaymentCard();
          $paymentCard->setCardHolderName($config->get('holder_name'));
          $paymentCard->setCardNumber($config->get('number'));
          $paymentCard->setExpireMonth($config->get('expiration_month'));
          $paymentCard->setExpireYear($config->get('expiration_year'));
          $paymentCard->setCvc($config->get('cvc'));
          $request->setPaymentCard($paymentCard);
          $customer = new Customer();
          $customer->setName($ad);
          $customer->setSurname($soyad);
          $gsm_number = $this->current_user->field_telefon->value;
          $num='';
          $gsm_st='';
          if (strlen($gsm_number) >= 10 && $this->current_user->field_adres->country_code == 'TR'){
            $gsm_lastten = substr($gsm_number, -10);
            $gsm_valid = '+90'.$gsm_lastten;
          }
          else {
            $gsm_operator_number = '+905';
            $gsm_nums[]= array(0, 3, 4, 5);
            $sayi = rand(0,3);
            $gsm_st = $gsm_operator_number.$gsm_nums[0][$sayi];
            if (str_contains($gsm_st, '53')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(0,9);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '54')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(1,6);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '50')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(5,7);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '55')) {
              $gsm_st=$gsm_st.'5';
              for ($i=0; $i<7; $i++) {
                $sayi1 = rand(1,9);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
          }
          $customer->setGsmNumber($gsm_valid);
          $customer->setEmail($this->current_user->getEmail());
          $customer->setIdentityNumber(($this->current_user->field_tc->value)?: '11111111110');
          $customer->setShippingContactName($ad_soyad);
          $customer->setShippingCity(($this->current_user->field_adres->administrative_area)?:'??stanbul');
          $customer->setShippingCountry(($this->current_user->field_adres->country_code)?:'TR');
          $customer->setShippingAddress(($this->current_user->field_adres->address_line1)?:'Beyo??lu');
          $customer->setShippingZipCode(($this->current_user->field_adres->postal_code)?:'34100');
          $customer->setBillingContactName($ad_soyad);
          $customer->setBillingCity(($this->current_user->field_adres->administrative_area)?:'??stanbul');
          $customer->setBillingCountry(($this->current_user->field_adres->country_code)?:'TR');
          $customer->setBillingAddress(($this->current_user->field_adres->address_line1)?:'Beyo??lu');
          $customer->setBillingZipCode(($this->current_user->field_adres->postal_code)?:'34100');
          $request->setCustomer($customer);
          $result = SubscriptionCreate::create($request, Config::options());
          $rs = $result->getStatus(); //$rs is the result_status.
          $src = $result->getReferenceCode(); //$src is Subscription reference code.
          /**
           * If result is success activate subscription
           */
          if ($rs == 'success') {
            $request = new SubscriptionActivateRequest();
            $request->setLocale("tr");
            $request->setConversationId($order->id());
            $request->setSubscriptionReferenceCode($src);
            $result = SubscriptionActivate::update($request, Config::options());
          }
          //Retrive subscription details and saving field_abonelik_durumu
          $request = new SubscriptionDetailsRequest();
          $request->setSubscriptionReferenceCode($src);
          $result = SubscriptionDetails::retrieve($request, Config::options());
          $ss = $result->getSubscriptionStatus(); //$ss is subscription status.
          if ($ss == 'ACTIVE') {
            $this->current_user->field_abonelik_durumu->value = 'Aktif';
            $this->current_user->field_abonelik_referans_kodu->value = $src;
            $this->current_user->addRole('abone');
            $this->current_user->save();
          }
          else {
            $this->current_user->field_abonelik_durumu->value = 'Beklemede';
            $this->current_user->save();
          }
          $order->field_kart_turu->value = 'Kredi Kart??';
          $order->save();
          if ($order->total_paid->number != $order->total_price->number) {
            Drupal::messenger()->addError(t('Your payment has failed and subscription was not created.'));
          }
          else {
            Drupal::messenger()->addMessage(t('Your payment was successfully received and subscription was created.'));
          }
          $config->delete();
        }
        else {
          $order->field_kart_turu->value = 'Banka Kart??';
          $order->save();
          $this->current_user->field_abonelik_durumu->value = 'Banka Kart?? ile Abonelik';
          $this->current_user->field_abonelik_referans_kodu->value = "";
          $this->current_user->addRole('abone');
          $this->current_user->save();
          if ($order->total_paid->number != $order->total_price->number) {
            Drupal::messenger()->addError(t('Your payment was failed and subscription was not created.'));
          }
          else {
            Drupal::messenger()->addMessage(t('Your payment card is a debit card. Subsciption can not be created.'));
            Drupal::messenger()->addMessage(t('Your payment was successfully received.'));
            $this->current_user->addRole('abone');
            $this->current_user->save();
          }
          $config->delete();
        }
      }
      else{
        try {
          $order_items = $order->getItems();
          $order_item = reset($order_items);
          $product_variation = $order_item->getPurchasedEntity();
          $sku_field = $product_variation->field_sku->value;
          //$this->current_user = User::load(Drupal::currentUser()->id());
          /* $ad_soyad = $this->>$this->current_user->field_adiniz_soyadiniz->value;
          $parts = explode(' ', $ad_soyad);
          $last = array_pop($parts);
          $parts = array(implode(' ', $parts), $last); */
          $ad = $this->current_user->field_adiniz->value;
          $soyad = $this->current_user->field_soyadiniz->value;
          $ad_soyad = $ad.' '.$soyad;
          $request = new SubscriptionCreateRequest();
          $request->setLocale("tr");
          $request->setConversationId($order_id);
          $request->setPricingPlanReferenceCode($sku_field);
          $request->setSubscriptionInitialStatus("PENDING");
          $paymentCard = new PaymentCard();
          $paymentCard->setCardHolderName($config->get('holder_name'));
          $paymentCard->setCardNumber($config->get('number'));
          $paymentCard->setExpireMonth($config->get('expiration_month'));
          $paymentCard->setExpireYear($config->get('expiration_year'));
          $paymentCard->setCvc($config->get('cvc'));
          $request->setPaymentCard($paymentCard);
          $customer = new Customer();
          $customer->setName($ad);
          $customer->setSurname($soyad);
          //$customer->setGsmNumber(($this->>$this->current_user->field_telefon->value)?: '+905555555555');
          //$customer->setGsmNumber('+905555555555');
          $gsm_number = $this->current_user->field_telefon->value;
          $num='';
          $gsm_st='';
          if (strlen($gsm_number) >= 10 && $this->current_user->field_adres->country_code == 'TR'){
            $gsm_lastten = substr($gsm_number, -10);
            $gsm_valid = '+90'.$gsm_lastten;
          }
          else {
            $gsm_operator_number = '+905';
            $gsm_nums[]= array(0, 3, 4, 5);
            $sayi = rand(0,3);
            $gsm_st = $gsm_operator_number.$gsm_nums[0][$sayi];
            if (str_contains($gsm_st, '53')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(0,9);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '54')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(1,6);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '50')) {
              for ($i=0; $i<8; $i++) {
                $sayi1 = rand(5,7);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
            if (str_contains($gsm_st, '55')) {
              $gsm_st=$gsm_st.'5';
              for ($i=0; $i<7; $i++) {
                $sayi1 = rand(1,9);
                $num = $num.$sayi1;
              }
              $gsm_valid = $gsm_st.$num;
            }
          }
          $customer->setGsmNumber($gsm_valid);
          $customer->setEmail($this->current_user->getEmail());
          $customer->setIdentityNumber(($this->current_user->field_tc->value)?: '11111111111');
          $customer->setShippingContactName($ad_soyad);
          $customer->setShippingCity(($this->current_user->field_adres->administrative_area)?:'??stanbul');
          $customer->setShippingCountry(($this->current_user->field_adres->country_code)?:'TR');
          $customer->setShippingAddress(($this->current_user->field_adres->address_line1)?:'Beyo??lu');
          $customer->setShippingZipCode(($this->current_user->field_adres->postal_code)?:'34100');
          $customer->setBillingContactName($ad_soyad);
          $customer->setBillingCity(($this->current_user->field_adres->administrative_area)?:'??stanbul');
          $customer->setBillingCountry(($this->current_user->field_adres->country_code)?:'TR');
          $customer->setBillingAddress(($this->current_user->field_adres->address_line1)?:'Beyo??lu');
          $customer->setBillingZipCode(($this->current_user->field_adres->postal_code)?:'34100');
          $request->setCustomer($customer);
          $result = SubscriptionCreate::create($request, Config::options());
          $rs = $result->getStatus(); //$rs is the result status.
          $src = $result->getReferenceCode(); //$src is subscription reference code.
          /**
           * If result is success activate subscription
           */
          if ($rs == 'success') {
            $request = new SubscriptionActivateRequest();
            $request->setLocale("TR");
            $request->setConversationId($order->id());
            $request->setSubscriptionReferenceCode($src);
            $result = SubscriptionActivate::update($request, Config::options());
          }
          //Retrive subscription details and saving field_abonelik_durumu
          $request = new SubscriptionDetailsRequest();
          $request->setSubscriptionReferenceCode($src);
          $result = SubscriptionDetails::retrieve($request, Config::options());
          $ss = $result->getSubscriptionStatus(); //$ss is subscription status.
          if ($ss == 'ACTIVE') {
            $this->current_user->field_abonelik_durumu->value = 'Aktif';
            $this->current_user->field_abonelik_referans_kodu->value = $src;
            $this->current_user->addRole('abone');
            $this->current_user->save();
          }
          else {
            $this->current_user->field_abonelik_durumu->value = 'Beklemede';
            $this->current_user->save();
          }

          $order->field_kart_turu->value = 'Yabanc?? Kart';
          $order->save();
          if ($order->total_paid->number != $order->total_price->number) {
            Drupal::messenger()->addError(t('Your payment was failed and subscription was not created.'));
          }
          else {
            Drupal::messenger()->addMessage(t('Your payment was successfully received and subscription was created.'));
            $config->delete();
          }
        }
        catch (Exception $e) {
          $config->delete();
          Drupal::messenger()->addError(t('Your payment card is not a credit card. Subsciption can not be created, please contact us.'));
        }
      }
    }
    else{
      $config->delete();
      Drupal::messenger()->addError(t('Your payment was failed and subscription was not created.'));
    }
  }
}
