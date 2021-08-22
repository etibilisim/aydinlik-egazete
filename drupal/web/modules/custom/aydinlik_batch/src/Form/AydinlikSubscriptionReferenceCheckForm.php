<?php

namespace Drupal\aydinlik_batch\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iyzipay\Config;
use Drupal\user\Entity\User;
use Iyzipay\Model\Subscription\SubscriptionCancel;
use Iyzipay\Model\Subscription\SubscriptionDetails;
use Iyzipay\Request\Subscription\SubscriptionCancelRequest;
use Iyzipay\Request\Subscription\SubscriptionDetailsRequest;

/**
 * Implements a Form that you can check users' subscription status by reference codes.
 */
class AydinlikSubscriptionReferenceCheckForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'aydinliksubscriptionreferencecheckform';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['subscriptionCodes'] = [
      '#type' => 'textarea',
      '#title' => t('Subscription Codes'),
      '#size' => 1000,
      '#description' => t('Enter the line separated subscription codes that you want to check and paste to profile'),
      '#required' => TRUE,
    ];
    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check subscriptions and paste them to the profiles'),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $ref_codes = explode("\n",$form_state->getValue('subscriptionCodes'));
    $batch = array(
      'title' => t('Checking subscirptions...'),
      'init_message'     => t('Processing'),
      'operations' => [],
      'progress_message' => t('Processed @current out of @total.'),
      'error_message'    => t('An error occurred during processing'),
      'finished' => '\Drupal\aydinlik_batch\Form\AydinlikSubscriptionReferenceCheckForm::batchFinished',
    );
    foreach ($ref_codes as $ref_code) {
      $ref_code = trim($ref_code);
      $batch['operations'][] = ['Drupal\aydinlik_batch\Form\AydinlikSubscriptionReferenceCheckForm::reference_check', [$ref_code]];
    }
      batch_set($batch);
      Drupal::messenger()->addMessage('Abonelikler kontrol edildi. Referans kodları ilgili profillere eklendi.');
  }
    public static function reference_check($ref_code) {
      date_default_timezone_set('UTC');
      $request = new SubscriptionDetailsRequest();
      $request->setSubscriptionReferenceCode($ref_code);
      $result = SubscriptionDetails::retrieve($request, Config::options());
      $email = $result->getCustomerEmail();
      $users = \Drupal::entityTypeManager()->getStorage('user')
        ->loadByProperties(['mail' => $email]);
      $user = reset($users);
      $user_name = $user->field_adiniz->value;
      $user_surname = $user->field_soyadiniz->value;
      $sed = $user->field_abonelik_bitis_tarihi->value;
      //$subscription_end_date = new \DateTime;
      $subscription_end_date = new \DateTime($sed, new \DateTimeZone('UTC'));
      $subscription_end_date_ts = $subscription_end_date->getTimestamp();
      $result_status = $result->getStatus();
      $orders = $result->getOrders();
      if ($orders != NULL) {
        $last_order = $orders[0];
        $last_order_status = $last_order->orderStatus;
      }
      $last_order_start_date_ts = substr($last_order->startPeriod,0,10);
      $last_order_start_date = date('Y-m-d\TH:i:s', $last_order_start_date_ts);
      $last_order_start_date = new \DateTime($last_order_start_date, new \DateTimeZone('UTC'));
      $last_order_sd_ts = $last_order_start_date->getTimestamp();
      $last_order_start_date = date('Y-m-d\TH:i:s', $last_order_sd_ts);
      if ($result->getSubscriptionStatus() == 'ACTIVE') {
        if ($last_order_status == 'WAITING') {
          $user->field_abonelik_durumu->value = 'Aktif';
          $user->field_abonelik_referans_kodu->value = $ref_code;
          $user->field_abonelik_bitis_tarihi->value = $last_order_start_date;
          $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği ilgili alana eklenmiştir ve abonelik durumu Aktif olarak düzenlenmiştir.';
          $user->field_kullanici_notlari->value = $user->field_kullanici_notlari->value.'\n'.$message.'-'.date('d.m.y H:i:s');
          $user->save();
          Drupal::messenger()->addMessage($message);
        }
      }
      if ($result->getSubscriptionStatus() == 'UNPAID') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        $user->field_abonelik_durumu->value = 'Yenileme Bekliyor';
        $user->field_abonelik_referans_kodu->value = $ref_code;
        $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği ilgili alana eklenmiştir ve abonelik durumu Yenileme bekliyor olarak düzenlenmiştir.';
        $user->field_kullanici_notlari->value = $user->field_kullanici_notlari->value.'\n'.$message.'-'.date('d.m.y H:i:s');
        $user->save();
        Drupal::messenger()->addWarning($message);
      }
      if ($result->getSubscriptionStatus() == 'CANCELED') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        if ($user->field_abonelik_referans_kodu->value == 'Yanlış Referans Kodu Silindi') {
          $user->field_abonelik_referans_kodu->value = '';
          $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği iptal edildiğinden silinmiştir.';
          $user->field_kullanici_notlari->value = $user->field_kullanici_notlari->value.'</ br>'.$message.'-'.date('d.m.y H:i:s');
          $user->save();
          Drupal::messenger()->addWarning($message);
        }
        else {
          $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu abonelikten başka bir aboneliği olduğu için değişiklik yapılmamıştır.';
          Drupal::messenger()->addError($message);
        }
      }
    }
  }
