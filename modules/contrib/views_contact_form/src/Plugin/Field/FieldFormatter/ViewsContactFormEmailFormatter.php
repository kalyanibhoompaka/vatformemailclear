<?php

namespace Drupal\views_contact_form\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'views_contact_form' email formatter.
 *
 * @FieldFormatter(
 *   id = "views_contact_form_email_formatter",
 *   label = @Translation("Views Contact Form"),
 *   field_types = {
 *     "email"
 *   },
 *   settings = {
 *     "contact_type" = "feedback",
 *     "contact_recipients_include" = FALSE
 *   }
 * )
 */
class ViewsContactFormEmailFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'contact_type' => 'feedback',
      'contact_recipients_include' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Get value from the items and store it.
    $recipients = [];
    foreach ($items as $delta => $item) {
      $recipients[] = $item->value;
    }

    if (empty($recipients)) {
      return [];
    }

    $contact_form = clone ContactForm::load($this->getSetting('contact_type'));

    // If we want to send email also to the recipients from the contact_form,
    // merge recipients from items and contact_form.
    if ($this->getSetting('contact_recipients_include') == TRUE) {
      $recipients = array_merge($recipients, $contact_form->get('recipients'));
    }

    // Remove the doubles to avoid double mail.
    $recipients = array_unique($recipients);
    // Finally override the recipients on the contact_form entity.
    $contact_form->set('recipients', $recipients);

    $message = \Drupal::entityTypeManager()->getStorage('contact_message')->create([
      'contact_form' => $contact_form->get('id'),
    ]);

    // Override the entity contact_form in the Message.
    // So the recipients are also set.
    $message->contact_form->entity = $contact_form;

    $form = \Drupal::service('entity.form_builder')->getForm($message);

    return [0 => ['#markup' => \Drupal::service('renderer')->render($form)]];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $entity = ContactForm::load($this->getSetting('contact_type'));
    $contact_recipients_include = $this->getSetting('contact_recipients_include') == TRUE ? 'Yes' : 'No';

    $summary[] = t('Contact: <a href="@url">@contact_label</a>',
    [
      '@url' => '/admin/structure/contact/manage/' . $entity->get('id'),
      '@contact_label' => $entity->get('label'),
    ]);
    $summary[] = t('Include contact form recipient(s): @contact_recipients_include',
    ['@contact_recipients_include' => $contact_recipients_include]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    foreach (ContactForm::loadMultiple() as $id => $contact_form) {
      if ($id != 'personal') {
        $options[$id] = $contact_form->get('label');
      }
    }

    $form['contact_type'] = [
      '#title' => 'Choose the contact form type',
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->getSetting('contact_type'),
    ];

    $form['contact_recipients_include'] = [
      '#title' => 'Contact form recipient(s)',
      '#description' => 'Should we also send the mail to the default category recipient(s) ?',
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('contact_recipients_include'),
    ];

    $form['form_display'] = [
      '#title' => 'Form display',
      '#markup' => 'You can customize the display of the form by editing the ' .
      'category form display. Click on the corresponding link: ' .
      '<em>Manage form display</em> on ' .
      Link::fromTextAndUrl(t('this page'), Url::fromRoute('entity.contact_form.collection'), ['attributes' => ['target' => '_blank']])->toString(),
      '#type' => 'item',
    ];

    return $form;
  }

}
