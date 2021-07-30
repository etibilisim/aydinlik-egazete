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
 * Implements a Batch example Form.
 */
class AydinlikUnpaidSubscriptionCheckForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'aydinlikunpaidsubscriptioncheckform';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['subscriptionCodes'] = [
      '#type' => 'textarea',
      '#title' => t('Subscription Codes'),
      '#size' => 1000,
      '#description' => t('Enter the line separated subscription codes that you want to check paid status'),
      '#required' => TRUE,
    ];
    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fix unpaid subscriptions'),
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
      'finished' => '\Drupal\aydinlik_batch\Form\AydinlikUnpaidSubscriptionCheckForm::batchFinished',
    );
    foreach ($ref_codes as $ref_code) {
      $ref_code = trim($ref_code);
      $batch['operations'][] = ['Drupal\aydinlik_batch\Form\AydinlikUnpaidSubscriptionCheckForm::unpaid_check', [$ref_code]];
    }
      batch_set($batch);
      Drupal::messenger()->addMessage('Ödemesi gelmeyen abonelikler ile ilgili gerekli işlemler yapıldı.');
  }
    public static function unpaid_check($ref_code) {
      date_default_timezone_set('UTC');
      $request = new SubscriptionDetailsRequest();
      $request->setSubscriptionReferenceCode($ref_code);
      $result = SubscriptionDetails::retrieve($request, Config::options());
      
      if ($result->getSubscriptionStatus() == 'UNPAID') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        $orders = $result->getOrders();
        $last_order = $orders[0];
        $last_payments = $last_order->paymentAttempts;
        $last_payment = end($last_payments);
        $last_payment_status = $last_payment->paymentStatus;
        $last_payment_date_ts = substr($last_payment->createdDate,0,10);
        $subscription_end_date = new \DateTime;
        $subscription_end_date->setTimestamp($last_payment_date_ts);
        $subscription_end_date = date('Y-m-d\TH:i:s',$subscription_end_date->getTimestamp());
        $user->field_abonelik_durumu->value = 'Yenileme bekliyor';
        $user->field_abonelik_bitis_tarihi->value = $subscription_end_date;
        if ($user->hasRole('abone')) {
          $user->removeRole('abone');
        }
        $user->save();
        $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği ilgili alana eklenmiştir ve abonelik durumu Yenileme bekliyor olarak düzenlenmiştir ve abonelik bitiş tarihi geriye alınmıştır.';
        Drupal::messenger()->addWarning($message);
      }
    }
  }