<?php

namespace Drupal\tyres_running_info\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Drupal\tyres_running_info\OrderExport;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Class ExportOrderForm.
 *
 * @package Drupal\tyres_running_info\Form
 */
class TyresExportForm extends FormBase {
  use DependencySerializationTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tyers_export_form';
  }

  /**
   * Initializes a new export form.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   Private temporary storage factory.
   */
  public function __construct(
    TimeInterface $time,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStoreFactory $tempStoreFactory
  ) {
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStoreFactory = $tempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $store = $this->tempStoreFactory->get('tyres_running_info');
    $file_link = $store->get('export_file_link');
    if ($file_link) {
      $store->delete('export_file_link');
      $form['actions']['download'] = [
        '#type' => 'link',
        '#title' => $this->t('Download File'),
        "#weight" => 1,
        '#url' => Url::fromUserInput('/' . $file_link),
        '#attributes' => [
          'target' => '_blank',
          'class' => 'button button--primary js-form-submit form-submit'
        ],
      ];
    }
    $form['date_range'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date Range'),
      // Added.
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t("Please select max 2 month range for better performance.")
    ];
    $form['date_range']['start'] = [
      '#title' => $this->t('Start Date'),
      '#default_value' => new DrupalDateTime('-60 days'),
      '#type' => 'datetime',
      '#date_format' => 'Y-m-d',
      '#date_year_range' => '-10:0',
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#required' => TRUE,
      '#attributes' => [
        'max' => $this->dateFormatter->format(strtotime('-1 days', $this->time->getRequestTime()), 'custom', 'Y-m-d'),
      ],
    ];
    $form['date_range']['end'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Date'),
      '#default_value' => new DrupalDateTime('now'),
      '#type' => 'datetime',
      '#date_format' => 'Y-m-d',
      '#date_year_range' => '-10:0',
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#required' => TRUE,
      '#attributes' => [
        'max' => $this->dateFormatter->format($this->time->getRequestTime(), 'custom', 'Y-m-d'),
      ],
    ];
    $form['other'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select Order state'),
      // Added.
      '#collapsible' => TRUE,
      // Added.
      '#collapsed' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Tyres'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start_date = $form_state->getValue('start')->getTimestamp();
    $end = $form_state->getValue('end')->getTimestamp();
    $diff = abs($end - $start_date);
    $years = floor($diff / (365 * 60 * 60 * 24));
    $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
    if ($months > 2) {
      $form_state->setErrorByName('start', $this->t('Please select date with in the 2 months range.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $start_date = $form_state->getValue('start')->getTimestamp();
    $end = $form_state->getValue('end')->getTimestamp();
    $state = $form_state->getValue('state');
    $query = $this->entityTypeManager->getStorage('typer_running_info')
      ->getQuery()
      ->accessCheck(FALSE);

    $query->condition('created', [$start_date, $end], 'BETWEEN');
    $tyres = $query->execute();
    $oprations = [];
    foreach ($tyres as $tyre) {
      $tyre_info = $this->entityTypeManager->getStorage('typer_running_info')->load($tyre);
      $oprations[] = [
        '\Drupal\tyres_running_info\TyresExport::exportTyresData',
        [$tyre_info]
      ];
    }
    $batch = [
      'title' => t('Fetching Order data...'),
      'operations' => $oprations,
      'finished' => '\Drupal\tyres_running_info\TyresExport::exportOrderDataFinishedCallback',
      'init_message' => t('Fetching order data'),
      'progress_message' => t('Processed @current out of @total.'),
    ];
    batch_set($batch);
  }

}
