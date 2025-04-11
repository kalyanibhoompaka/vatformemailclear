<?php
namespace Drupal\book_enquiry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Routing;

class BookEnquiryForm extends FormBase {
  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'book_enquiry_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
      $config = $this->config('book_enquiry.settings');
      $form['Country'] = array(
        '#type' => 'textfield',
        '#title' => !empty($config->get('Country')) ? $config->get('Country') : $this->t('Enter Country:'),
        '#required' => TRUE,
      );
      $form['State'] = array(
        '#type' => 'textfield',
        '#title' => !empty($config->get('State')) ? $config->get('State') : $this->t('Enter State:'),
        '#required' => TRUE,
      );
      $form['City'] = array (
        '#type' => 'textfield',
        '#title' => !empty($config->get('City')) ? $config->get('City') : $this->t('Enter your City:'),
        '#required' => TRUE,
      );

      $form['Timezone'] = array (
        '#type' => 'select',
        '#options' => [
          //'' => $this->t('--Select technology--'),
          'America/Chicago' => $this->t('America/Chicago'),
          'Asia/Kolkata' => $this->t('Asia/Kolkata'),
          'Australia/Sydney' => $this->t('Australia/Sydney'),
        ],
        '#title' => !empty($config->get('Timezone')) ? $config->get('Timezone') : $this->t('Select  Timezone:'),
        '#required' => TRUE,
      );
      $form['Pincode'] = array (
        '#type' => 'number',
        '#title' => !empty($config->get('Pincode')) ? $config->get('Pincode') : $this->t('Enter your  Pincode:'),
        '#required' => TRUE,
      );
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Enquiry'),
        '#button_type' => 'primary',
      );
      return $form;
    }

    /*public function validateForm(array &$form, FormStateInterface $form_state) {
      if(!$form_state->getValue('author_name') || empty($form_state->getValue('author_name'))) {
        $form_state->setErrorByName('author_name', $this->t('Please enter your Name'));
      }

      if(!$form_state->getValue('author_mail') || empty($form_state->getValue('author_mail'))) {
        $form_state->setErrorByName('author_mail', $this->t('Please enter your Email'));
      }

      $email=$form_state->getValue('author_mail');
      if (!preg_match("/^[a-z0-9_-]+[a-z0-9_.-]*@[a-z0-9_-]+[a-z0-9_.-]*\.[a-z]{2,5}$/",trim($email))) {
        $form_state->setErrorByName('author_mail', $this->t('Please enter valid Email'));
      } 
      
      //echo strlen($form_state->getValue('author_phone'));
    
      if(strlen($form_state->getValue('author_phone')) != 10) {
        $form_state->setErrorByName('author_phone', $this->t('Please enter a valid Contact Number'));
      }
    }*/

    public function submitForm(array &$form, FormStateInterface $form_state) {
      try{
          $conn = Database::getConnection();
          
          $field = $form_state->getValues();
          
          $fields["Country"] = $field['Country'];
          $fields["State"] = $field['State'];
          $fields["City"] = $field['City'];
          $fields["Timezone"] = $field['Timezone'];
          $fields["Pincode"] = $field['Pincode'];
          
            $conn->insert('book_enquiry')
                  ->fields($fields)->execute();
              \Drupal::messenger()->addMessage($this->t('Your Enquiry has been succesfully sended'));
            
      } catch(Exception $ex){
          \Drupal::logger('book_enquiry')->error($ex->getMessage());
      }
    }
}