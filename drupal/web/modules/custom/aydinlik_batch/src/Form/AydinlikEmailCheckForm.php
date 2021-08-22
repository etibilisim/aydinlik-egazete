<?php

namespace Drupal\aydinlik_batch\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iyzipay\Config;
use Drupal\user\Entity\User;

/**
 * Implements a user check and delete wrong reference codes that you know has wrong reference codes.
 */
class AydinlikEmailCheckForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'aydinlikemailcheckform';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['emails'] = [
      '#type' => 'textarea',
      '#title' => t('Emails'),
      '#size' => 1000,
      '#description' => t('Enter the line separated emails that you want to check and delete wrong expression'),
      '#required' => TRUE,
    ];
    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check emails'),
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
    $emails = explode("\n",$form_state->getValue('emails'));
    $batch = array(
      'title' => t('Checking emails...'),
      'init_message'     => t('Processing'),
      'operations' => [],
      'progress_message' => t('Processed @current out of @total.'),
      'error_message'    => t('An error occurred during processing'),
      'finished' => '\Drupal\aydinlik_batch\Form\AydinlikEmailCheckForm::batchFinished',
    );
    foreach ($emails as $email) {
      $email = trim($email);
      $batch['operations'][] = ['Drupal\aydinlik_batch\Form\AydinlikEmailCheckForm::email_check', [$email]];
    }
      batch_set($batch);
      Drupal::messenger()->addMessage('E-Postalar kontrol edildi.');
  }
    public static function email_check($email) {
      $users = \Drupal::entityTypeManager()->getStorage('user')
        ->loadByProperties(['mail' => $email]);
      $user = reset($users);
      if ($user->field_abonelik_referans_kodu->value == 'Yanlış Referans Kodu Silindi') {
        $user->field_abonelik_referans_kodu->value = '';
        $user->save();
        $message = $email . ' eposta hesaplı kullanıcıdaki Yanlış Referans Kodu Silindi ibaresi kaldırıldı.';
        Drupal::messenger()->addMessage($message);
      }
    }
  }
