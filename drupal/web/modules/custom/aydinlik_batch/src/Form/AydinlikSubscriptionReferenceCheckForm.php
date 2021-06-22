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
      $request = new SubscriptionDetailsRequest();
      $request->setSubscriptionReferenceCode($ref_code);
      $result = SubscriptionDetails::retrieve($request, Config::options());
      if ($result->getSubscriptionStatus() == 'ACTIVE') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        $user->field_abonelik_durumu->value = 'Aktif';
        $user->field_abonelik_referans_kodu->value = $ref_code;
        $user->save();
        $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği ilgili alana eklenmiştir ve abonelik durumu Aktif olarak düzenlenmiştir.';
        Drupal::messenger()->addMessage($message);
      }
      if ($result->getSubscriptionStatus() == 'UNPAID') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        $user->field_abonelik_durumu->value = 'Yenileme Bekliyor';
        $user->field_abonelik_referans_kodu->value = $ref_code;
        $user->save();
        $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği ilgili alana eklenmiştir ve abonelik durumu Yenileme bekliyor olarak düzenlenmiştir.';
        Drupal::messenger()->addWarning($message);
      }
      if ($result->getSubscriptionStatus() == 'CANCELED') {
        $email = $result->getCustomerEmail();
        $users = \Drupal::entityTypeManager()->getStorage('user')
          ->loadByProperties(['mail' => $email]);
        $user = reset($users);
        if ($user->field_abonelik_referans_kodu->value == 'Yanlış Referans Kodu Silindi') {
          $user->field_abonelik_referans_kodu->value = '';
          $user->save();
          $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu aboneliği iptal edildiğinden silinmiştir.';
          Drupal::messenger()->addWarning($message);
        }
        else {
          $message = $email . ' eposta hesaplı kullanıcının ' . $ref_code . ' referans kodlu abonelikten başka bir aboneliği olduğu için değişiklik yapılmamıştır.';
          Drupal::messenger()->addError($message);
        }
      }
    }
  }
