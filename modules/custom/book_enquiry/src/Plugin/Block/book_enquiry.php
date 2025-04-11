<?php

namespace Drupal\book_enquiry\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\book_enquiry\Form\BookEnquiryConfigForm;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'book_enquiry' Block.
 *
 * @Block(
 *   id = "book_enquiry",
 *   admin_label = @Translation("Hello block"),
 *   category = @Translation("book_enquiry config form"),
 * )
 */
class book_enquiry extends BlockBase{

  /**
   * {@inheritdoc}
   */
  public function build() {

    // $form = \Drupal::formBuilder()->getForm('Drupal\book_enquiry\Form\BookEnquiryConfigForm');

    $config = \Drupal::config('book_enquiry.settings');
    $Country = $config->get('Country');
    $State = $config->get('State');
    $City = $config->get('City');
    $Timezone = $config->get('Timezone');
    $Pincode = $config->get('Pincode');

    date_default_timezone_set($Timezone); 
    // Get the current date and time in the specified 
    $currentDateTime = date('Y-m-d H:i:s');
    drupal_flush_all_caches();
        $content = [
          // '#markup' => $this->t('Country: @Country, State: @State, City: @City,Timezone: @Timezone,Pincode: @Pincode' ,[
          //   '@Country' => $Country,
          //   '@State' => $State,
          //   '@City' => $City,
          //   '@Timezone' => $Timezone,
          //   '@Pincode' => $Pincode,
          //       ]),
          '#cache' => [
            'tags' => $config->getCacheTags(),
          ],
          '#id' => 1,
            '#theme' => 'timetempl',
            '#Country' => $Country,
            '#State' => $State,
            '#City' => $City,
            '#Timezone' => $Timezone,
            '#Pincode' => $Pincode,
            '#cache' => [
              'contexts' => [],
              'tags' => [],
            ],
            '#currentDateTime' =>$currentDateTime,
            '#attached' => [
             'library' => [
                'book_enquiry/custom',
                  ]
             ],
           
              ];

    return $content;

  }
  public function getCacheMaxAge() {
    \Drupal::service('page_cache_kill_switch')->trigger();

    return 1;
  }
  
}