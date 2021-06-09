<?php

use Drupal\user\Entity\User;

/**
 * Batch execution.
 *
 * @param $number
 *   Number of times to execute.
 * @param array $context
 *   An array of contextual key/value information for rebuild batch process.
 */

 function _aydinlik_batch($rCodes){
    foreach ($rCodes as $rCode) {
    $rCode = trim($rCode);
    $request = new \Iyzipay\Request\Subscription\SubscriptionDetailsRequest();
    $request->setSubscriptionReferenceCode($rCode);
    $result = \Iyzipay\Model\Subscription\SubscriptionDetails::retrieve($request,\Drupal\iyzipay\Config::options());
    if ($result->getStatus() == 'success') {
    $email = $result->getCustomerEmail();
    $productName = $result->getProductName();
    
    $users = User::loadMultiple();
    foreach ($users as $user) {
      $umail = $user->mail->value;
      if ($email == $umail) {
        if ($user->field_abonelik_suresi->target_id == '1354') {
      $sType = 'Aylık Abonelik';
    }
    if ($user->field_abonelik_suresi->target_id =='1360') {
      $sType = 'Aylık Abonelik - Öğrenci';
    }
    if ($user->field_abonelik_suresi->target_id == '1364') {
      $sType = 'Yıllık Abonelik - Avrupa';
    }
    if ($user->field_abonelik_suresi->target_id == '1365') {
      $sType = 'Yıllık Abonelik - Avrupa Dışı';
    }
    if ($user->field_abonelik_suresi->target_id == '1359') {
      $sType = 'Yıllık Abonelik - Öğrenci';
    }
    if ($user->field_abonelik_suresi->target_id == '1355') {
      $sType = '3 Aylık Abonelik';
    }
    if ($user->field_abonelik_suresi->target_id == '1356') {
      $sType = '6 Aylık Abonelik';
    }
    if ($user->field_abonelik_suresi->target_id == '1358') {
      $sType = 'Yıllık Abonelik';
    }
    if ($productName != $sType) {
        $name = $user->field_adiniz->value;
        $surname = $user->field_soyadiniz->value;
        $ns = $name.' '.$surname;
    $request = new \Iyzipay\Request\Subscription\SubscriptionCancelRequest();
    $request->setLocale("tr");
    $request->setSubscriptionReferenceCode($rCode);
    $result = \Iyzipay\Model\Subscription\SubscriptionCancel::cancel($request,\Drupal\iyzipay\Config::options());
    $message = $ns.' kullanıcısının '.$rCode.' referans kodlu yanlış aboneliği iptal edilmiştir.';
    \Drupal::messenger()->addWarning($message);
    \Drupal::logger('aydinlik_cron')->notice($message);
              }
          }
        }
      }
    }
 }