<?php
namespace Drupal\book_enquiry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\book_enquiry\Form\Database;
use Drupal\Core\Url;
use Drupal\Core\Routing;


class BookEnquiryConfigForm extends ConfigFormBase {

    protected function getEditableConfigNames() {
        return ['book_enquiry.settings'];
    }
     
    public function getFormId() {
        return 'book_enquiry_settings';
    }
     
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('book_enquiry.settings');
        $form['text_info']['fieldset']['source_formats']['selected_langcode'] = [
            '#default_value' => $config->get('en'),
            '#type' => 'language_select',
            '#title' => $this->t('Language'),
        ];



            $form['Timezone'] = array (
                '#default_value' => $config->get('Timezone'),
                '#type' => 'select',
                '#options' => [
                  '' => $this->t('--Select Timezone--'),
                  'America/Chicago' => $this->t('America/Chicago'),
                  'Asia/Kolkata' => $this->t('Asia/Kolkata'),
                  'Australia/Sydney' => $this->t('Australia/Sydney'),
                ],
                '#title' => $this->t('Timezone:'),
              );
    
        $form['Country'] = [
            '#default_value' => $config->get('Country'),
            '#title' => $this->t('Country:'),
            '#type' => 'textfield',
            // '#states' => [
            //     'visible'=>[
            //         ':input[name="selected_langcode"]'=>['value'=>'gu']
            //     ],
            // ],
        ];
        $form['boolean'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('publish public content'),
            '#default_value' => 1,
          ];

        $form['email'] = [
            '#default_value' => $config->get('email'),
            '#title' => $this->t('email:'),
            '#type' => 'email',
        ];
        return parent::buildForm($form, $form_state);
    }
     
    public function submitForm(array &$form, FormStateInterface $form_state) {
        parent::submitForm($form, $form_state);
        $this->config('book_enquiry.settings')
        ->set('selected_langcode', $form_state->getValue('selected_langcode'))
        ->set('Country', $form_state->getValue('Country'))
            ->set('boolean', $form_state->getValue('boolean'))
            ->set('email', $form_state->getValue('email'))
            ->save();
        
        # save to config and clear cache
     
           

        // clear cache
        drupal_flush_all_caches();
        
        // parent::submitForm($form, $form_state);



    }
}