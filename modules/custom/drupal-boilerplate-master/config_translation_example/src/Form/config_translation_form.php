<?php
namespace Drupal\config_translation_example\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing;

class config_translation_form extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'config_translation_example_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('config_translation_example.adminsettings');
    $form['Name'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter Name:'),
      '#required' => TRUE,
    );
    $form['Email'] = array(
      '#type' => 'email',
      '#title' => $this->t('Enter email:'),
      '#required' => TRUE,
    );
    $form['Organization_Name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Enter Organization Name :'),
      '#required' => TRUE,
    );
    $language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $lang_name = \Drupal::languageManager()->getCurrentLanguage()->getName();
    $vat = \Drupal::config('config_translation_example.adminsettings')->get($language . '_VAT');
    $getEmailid = \Drupal::config('config_translation_example.adminsettings')->get($language . '_email');
    $form['VAT'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('VAT:'),
      '#value' => $vat,
      '#required' => TRUE,
    );

    $form['Country'] = array(
      '#type' => 'textfield',
      // '#title' => 'Country'
      '#value' => \Drupal::config('config_translation_example.adminsettings')->get($language . '_Country'),
      '#title' => 'Country',
      '#required' => TRUE,
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('SEND'),
      '#button_type' => 'primary',
    );
    return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $bcc = $form_state->getValue('Email');
    $language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $to = \Drupal::config('config_translation_example.adminsettings')->get($language . '_email');
    // $to_str = implode(",", $to);
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;
    $params = [
      'values' => $form_state->getValues(),
      'Email' => $form_state->getValue('Email'),
      'Name' => $form_state->getValue('Name'),
      'Organization_Name' => $form_state->getValue('Organization_Name'),
      'VAT' => $form_state->getValue('VAT'),
      'Country' => $form_state->getValue('Country'),
    ];
    $paramss = [
      'values' => $form_state->getValues(),
      'Email' => $form_state->getValue('Email'),
      'Name' => $form_state->getValue('Name'),
      'Organization_Name' => $form_state->getValue('Organization_Name'),
      'VAT' => $form_state->getValue('VAT'),
      'Country' => $form_state->getValue('Country'),

    ];
    $params['headers'] = array('Cc' => $cc, );
    // $params['headers'] = array('Bcc' => $bcc, );
    $params['message'] = 'a message';
    // The 'plugin.manager.mail' service is the one to use for $mailManager.
    $result = $mailManager->mail('config_translation_example', 'myform_submit', $bcc, $langcode, $params, NULL, $send);
    $result = $mailManager->mail('config_translation_example', 'myform_submit', $to, $langcode, $params, NULL, $send);

    if ($result['result'] != true) {
      $message = $this->t('There was a problem sending your email notification to @email.', array('@email' => $to));
      \Drupal::messenger()->addMessage($this->t($message), 'error');

      // drupal_set_message($message, 'error');
      \Drupal::logger('mail-log')->error($message);
      return;
    } 
    else {

      \Drupal::messenger()->addMessage($this->t('Your data has been succesfully sent to @email1 and  @email2', array('@email1' => $to,'@email2' => $bcc)));
    }
  }

}