<?php
/**
 * @file
 * Contains Drupal\config_translation_example\Form\ConfigForm.
 */
namespace Drupal\config_translation_example\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;


class ConfigForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'config_translation_example.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'config_translation_example_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('config_translation_example.adminsettings');

    $languageList = getlanguages();
    $form['#prefix'] = '<div id="ptov_template_form_wrapper">';
    $form['#suffix'] = '</div>';
    $master_selection = !empty($form_state->getValue('select_ptov_language')) ? $form_state->getValue('select_ptov_language') : t('Select');
    if (!empty($languageList)) {

      $form['country_specific_config']['select_ptov_language'] = array(
        '#type' => 'select',
        '#title' => t('Please select the country.'),
        '#default_value' => $master_selection,
        '#options' => $languageList,
        '#weight' => '-5',
        '#ajax' => [
          'callback' => '::ptov_form_populate',
          // don't forget :: when calling a class method.
          'disable-refocus' => FALSE,
          // Or TRUE to prevent re-focusing on the triggering element.
          'event' => 'change',
          'wrapper' => 'ptov_template_form_wrapper',
          // This element is updated with this AJAX callback.
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Please wait...'),
          ],
        ]
      );

      if ($master_selection != 'Select') {
        $form['country_specific_config'][$master_selection . '_publictov_enable_mbox_data_screen'] = array(
          '#type' => 'checkbox',
          '#title' => t("Publish Public TOV for ") . "<strong>" . $form['country_specific_config']['select_ptov_language']['#options'][$master_selection] . "</strong>?",
          '#default_value' => $config->get($master_selection . '_publictov_enable_mbox_data_screen') ?: 0,
        );

        $form['country_specific_config'][$master_selection . '_Country'] = array(
          '#type' => 'textfield',
          '#title' => t('Country'),
          '#default_value' => $config->get($master_selection . '_Country'),
          '#format' => $config->get($master_selection . '_Country.format'),
        );
        $form['country_specific_config'][$master_selection . '_VAT'] = array(
          '#type' => 'textfield',
          '#title' => t('VAT'),
          '#default_value' => $config->get($master_selection . '_VAT'),
        );

        $form['country_specific_config'][$master_selection . '_email'] = array(
          '#type' => 'email',
          '#title' => t('EMAIL'),
          '#default_value' => $config->get($master_selection . '_email'),
          '#description' => 'Enter your email'
        );

      } else {
        $form['country_specific_config']['help'] = array(
          '#type' => 'markup',
          '#markup' => t('Please select any language')
        );
      }

    }

    return parent::buildForm($form, $form_state);
  }
  public function ptov_form_populate(array &$form, FormStateInterface $form_state)
  {
    return $form;

  }
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $select_lang = $form_state->getValue('select_ptov_language');
    if (empty($select_lang) || ($select_lang == 'Select')) {
      $form_state->setErrorByName('select_ptov_language', $this->t("Please select the language"));
    }
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitForm($form, $form_state);
    $form_values = $form_state->getValues();
    foreach ($form_values as $key => $value) {
      \Drupal::service('config.factory')->getEditable('config_translation_example.adminsettings')->set($key, $value)->save();
    }

  }

}