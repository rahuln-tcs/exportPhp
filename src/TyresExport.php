<?php

namespace Drupal\tyres_running_info;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a service for Order export.
 */
class TyresExport {

  /**
   * Function to export order data.
   */
  public static function exportTyresData($tyre, &$context) {
    $tyre_id = $tyre->id();
    $label = $tyre->label->value;
    $manufacturer = $tyre->field_manufacturer->value;
    $sr_no = $tyre->field_sr_no->value;

    $tyre_rotations = [];
    $row['header'] = "SR No.: " . $sr_no . " " . $manufacturer . " " . $label;
    $row['header2'] = [
      'Rotations S No.',
      'DU NO',
      'HMR',
      'Date',
      'Position',
    ];
    $i = 1;
    foreach($tyre->get('field_rotation') as $paragraph) {
      $row[$paragraph->entity->id()] = [
          "Rotation " . $i,
          ($paragraph->entity->field_du_no->value) ? $paragraph->entity->field_du_no->value : "",
          ($paragraph->entity->field_hmr_on->value) ? $paragraph->entity->field_hmr_on->value : "",
          ($paragraph->entity->field_date->value) ? $paragraph->entity->field_date->value : "",
          ($paragraph->entity->field_position->value) ? $paragraph->entity->field_position->value : "",
      ];
      $i++;
    }

    if (!empty($row)) {
      $results =  $row;
    }
    $context['message'] = $message;
    $context['results'][] = $results;
  }

  /**
   * Callback for order data.
   */
  public static function exportOrderDataFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected.
    // All other error management should be handled using 'results'.

    if ($success) {
      if (!empty($results)) {
        $message = \Drupal::translation()->formatPlural(
          count($results),
         'One order exported.', '@count orders exported.'
        );
        self::orderExportxls($results);

      }
      else {
        $message = t('No order founds.');
      }
    }
    else {
      $message = t('Finished with an error.');
    }
    $messenger = \Drupal::messenger();
    $messenger->addMessage($message);
  }

  /**
   * Function to generate xls.
   */
  public static function orderExportxls($orders_data) {
    // Creates New Spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // Retrieve the current active worksheet
    $sheet = $spreadsheet->getActiveSheet();

    $row = [];
    $i = 1;
    foreach ($orders_data as $data) {
      foreach($data as $key => $row_data) {
        if (is_array($row_data)) {
          $row[] = $row_data;
          if ($key != "header2") {
            $sheet->getStyle("A{$i}")->getFont()
            ->setBold(true);
          }        
        } else {
          $row[] = [$row_data, 0,0,0];
          $sheet->mergeCells("A{$i}:E{$i}");
          $sheet->getStyle("A{$i}:E{$i}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('00FF7F');
          $sheet->getStyle("A{$i}")->getFont()
          ->setBold(true);
        }
        $i++;
      }
      $i++;
      $row[] = [
      ];
    }
    $sheet->fromArray(
      $row,  // The data to set
      NULL,  // Array values with this value will not be set
      'A1'   // Top left coordinate of the worksheet range where
    );
    $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
    // Save .xlsx file to the current directory
    $dateTime = new DrupalDateTime('now');
    $timestamp = $dateTime->getTimestamp();
    $filepath = 'sites/default/files/order-export-' . $timestamp . '.xlsx';
    $writer->save($filepath);
    $store = \Drupal::service('tempstore.private')->get('tyres_running_info');
    $store->set('export_file_link', $filepath);
  }

}
