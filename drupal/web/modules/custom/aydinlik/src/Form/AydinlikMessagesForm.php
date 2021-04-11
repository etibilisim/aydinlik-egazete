<?php

namespace Drupal\aydinlik\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Aydinlik module messages
 */
class AydinlikMessagesForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'aydinlik.settings';
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['aydinlik.settings'];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aydinlik.settings');
    $form['abonelikaktifdegilmesaji'] = [
      '#type' => 'textarea',
      '#title' => 'Aboneliği aktif olmayanlara gösterilecek mesaj',
      '#default_value' => $config->get('abonelikaktifdegilmesaji'),
      '#format' => 'full_html',
    ];
    $form['earsivabonesidegilmesaji'] = [
      '#type' => 'textarea',
      '#title' => 'E-arşiv aboneliği olmayanlara gösterilecek mesaj',
      '#default_value' => $config->get('earsivabonesidegilmesaji'),
      '#format' => 'full_html',
    ];
    $form['egazeteaboneliksuresibitecekmesaji'] = [
      '#type' => 'textarea',
      '#title' => 'E-gazete abonelik süresi bitmek üzere olanlara gösterilecek mesaj',
      '#default_value' => $config->get('egazeteaboneliksuresibitecekmesaji'),
      '#format' => 'full_html',
    ];
    $form['egazeteaboneliksuresibittimesaji'] = [
      '#type' => 'textarea',
      '#title' => 'E-gazete abonelik süresi bitenlere gösterilecek mesaj',
      '#default_value' => $config->get('egazeteaboneliksuresibittimesaji'),
      '#format' => 'full_html',
    ];
    $form['girisyapmesaji'] = [
      '#type' => 'textarea',
      '#title' => 'Misafir kullanıcılara gösterilecek mesaj',
      '#default_value' => $config->get('girisyapmesaji'),
      '#format' => 'full_html',
    ];
    $form['icerikaboneligiaraligimesaji'] = [
      '#type' => 'textarea',
      '#title' => 'İçerik aboneliği e-gazetenin çıkış tarihi aralığında olmayanlara gösterilecek mesaj',
      '#default_value' => $config->get('icerikaboneligiaraligimesaji'),
      '#format' => 'full_html',
    ];
    $form['satinalmesaji'] = [
      '#type' => 'textarea',
      '#title' => 'Kayıtlı kullanıcı olup da e-gazete aboneliği olmayanlara gösterilecek satın alın mesajı',
      '#default_value' => $config->get('satinalmesaji'),
      '#format' => 'full_html',
    ];
    $form['kaydet'] = [
      '#type' => 'submit',
      '#value' => 'Mesajları kaydet',
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('aydinlik.settings');
    $config->set('abonelikaktifdegilmesaji', $form_state->getValue('abonelikaktifdegilmesaji'));
    $config->set('earsivabonesidegilmesaji', $form_state->getValue('earsivabonesidegilmesaji'));
    $config->set('egazeteaboneliksuresibitecekmesaji', $form_state->getValue('egazeteaboneliksuresibitecekmesaji'));
    $config->set('egazeteaboneliksuresibittimesaji', $form_state->getValue('egazeteaboneliksuresibittimesaji'));
    $config->set('girisyapmesaji', $form_state->getValue('girisyapmesaji'));
    $config->set('icerikaboneligiaraligimesaji', $form_state->getValue('icerikaboneligiaraligimesaji'));
    $config->set('satinalmesaji', $form_state->getValue('satinalmesaji'));
    $config->save();
    $test = '';
    return parent::submitForm($form, $form_state);
  }
}
