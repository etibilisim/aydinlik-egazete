<?php

namespace Drupal\aydinlik_batch\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iyzipay\Config;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManager;
use Iyzipay\Model\Subscription\SubscriptionDetails;
use Iyzipay\Model\Subscription\SubscriptionRetry;
use Iyzipay\Request\Subscription\SubscriptionDetailsRequest;
use Iyzipay\Request\Subscription\SubscriptionRetryRequest;

/**
 * Implements a Batch Form to retry subscriptions.
 */
class AydinlikSubscriptionRetryForm extends FormBase {

    /**
     * {@inheritdoc}.
     */
    public function getFormId() {
      return 'aydinliksubscriptionretryform';
    }

    /**
     * {@inheritdoc}.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

      $form['subscriptionCodes'] = [
        '#type' => 'textarea',
        '#title' => t('Subscription Codes'),
        '#size' => 1000,
        '#description' => t('Enter the line separated subscription codes that you want to retry payment'),
        '#required' => TRUE,
      ];

      $form['submit_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Retry subscriptions'),
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
        'title' => t('Retrying subscirptions...'),
        'init_message'     => t('Processing'),
        'operations' => [],
        'progress_message' => t('Processed @current out of @total.'),
        'error_message'    => t('An error occurred during processing'),
        'finished' => '\Drupal\aydinlik_batch\Form\AydinlikSubscriptionRetryForm::batchFinished',
      );
      foreach ($ref_codes as $ref_code) {
        $ref_code = trim($ref_code);
        $batch['operations'][] = ['Drupal\aydinlik_batch\Form\AydinlikSubscriptionRetryForm::subscription_retry', [$ref_code]];
      }
        batch_set($batch);
        \Drupal::messenger()->addMessage('Abonelikler için yeniden ödeme talebi gönderildi. Bilgiler Son günlük iletilere eklendi.');
    }
    public static function subscription_retry($ref_code) {
        $request = new SubscriptionDetailsRequest();
        $request->setSubscriptionReferenceCode($ref_code);
        $result = SubscriptionDetails::retrieve($request, Config::options());
        $orders = [];
        $orders = $result->getOrders();
        $orc = end($orders)->referenceCode;
        $last_order = end($orders);
        $last_payments = $last_order->paymentAttempts;
        $last_payment = end($last_payments);
        $last_payment_ts = new \DateTime();
        $last_payment_ts = new \DateTime('now', new \DateTimeZone('UTC'));
        $last_payment_ts = substr($last_payment->createdDate,0,10);
        if ($result->getSubscriptionStatus() == 'UNPAID') {
            $email = $result->getCustomerEmail();
            $productName = $result->getProductName();
            $users = \Drupal::entityTypeManager()->getStorage('user')
              ->loadByProperties(['mail' => $email]);
            $user = reset($users);
            if ($user) {
                $name = $user->field_adiniz->value;
                $surname = $user->field_soyadiniz->value;
                $ns = $name . ' ' . $surname;
                $retry_request = new SubscriptionRetryRequest();
                $retry_request->setLocale("tr");
                $retry_request->setReferenceCode($orc);
                $retry_result = SubscriptionRetry::update($retry_request, Config::options());
                if ($retry_result->getStatus() == 'success') {
                    $request = new SubscriptionDetailsRequest();
                    $request->setSubscriptionReferenceCode($ref_code);
                    $result = SubscriptionDetails::retrieve($request, Config::options());
                    $orders = [];
                    $orders = $result->getOrders();
                    $orc = end($orders)->referenceCode;
                    $last_order = end($orders);
                    $last_payments = $last_order->paymentAttempts;
                    $last_payment = end($last_payments);
                    $last_payment_ts = new \DateTime();
                    $last_payment_ts = new \DateTime('now', new \DateTimeZone('UTC'));
                    $last_payment_ts = substr($retry_result->getSystemTime(),0,10);
                    $user->set('field_son_abonelik_islem_tarihi', date('Y-m-d\TH:i:s',$last_payment_ts));
                    if ($last_payment->paymentStatus == 'SUCCESS') {
                      $user->field_son_abonelik_islem_durumu->value = 'Abonelik yenilendi';
                      $user->field_abonelik_durumu->value = 'Aktif';
                      $message = $email.' eposta hesaplı '. $ns . ' kullanıcısının ' . $ref_code . ' referans kodlu '. $productName. ' adlı ürünü için yeniden ödeme başarılı bir şekilde alındı. Uzatma işlemi bir sonraki dönemsel görev çalıştığında yapılacaktır.';
                      $kullanici_notları = $user->field_kullanici_notlari->value;
                      $fabt = date('Y-m-d\TH:i:s', strtotime('1 month',strtotime($user->field_abonelik_bitis_tarihi->value)));
                      $user->field_abonelik_bitis_tarihi->value = $fabt;
                      $user->field_kullanici_notlari->value = $kullanici_notları."\n".$message;
                      $user->addRole('abone');
                      $user->save();
                      \Drupal::messenger()->addInfo($message);
                      \Drupal::logger('aydinlik_batch')->notice($message);
                    }
                    else{
                        $user->field_son_abonelik_islem_durumu->value = 'Abonelik yenilenmedi';
                        $kullanici_notları = $user->field_kullanici_notlari->value;
                        $error_message = $last_payment->errorMessage;
                      $message = $email.' eposta hesaplı '. $ns . ' kullanıcısının ' . $ref_code . ' referans kodlu aboneliği için yeniden ödeme alınamamıştır. Hata mesajı: '. $error_message;
                      $user->field_kullanici_notlari->value = $kullanici_notları."\n".$message;
                        $user->save();
                        \Drupal::messenger()->addError($message);
                        \Drupal::logger('aydinlik_batch')->info($message);
                    }
                }
                else {
                  $user->set('field_son_abonelik_islem_tarihi', date('Y-m-d\TH:i:s',$last_payment_ts));
                  $user->field_son_abonelik_islem_durumu->value = 'Abonelik aktif';
                  $user->save();
                  $error_message = $retry_result->getErrorMessage();
                  $message = $email.' eposta hesaplı '. $ns . ' kullanıcısının ' . $ref_code . ' referans kodlu aboneliği için yeniden ödeme henüz talep edilemez. Hata mesajı: '.$error_message;
                  \Drupal::messenger()->addError($message);
                  \Drupal::logger('aydinlik_batch')->info($message);
                }
            }
        }
    }
}
