<?php

namespace Drupal\libraries_registry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Defines a form that configures global Juicebox settings.
 */
class ProcessingForm extends FormBase {

  /**
   * A Drupal module manager service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleManager;

  /**
   * A Drupal serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Class constructor.
   */
  public function __construct(ModuleHandlerInterface $module_manager, SerializerInterface $serializer) {
    $this->moduleManager = $module_manager;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static($container->get('module_handler'), $container->get('serializer'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'libraries_registry_process_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Loop through libraries_info hooks, serialize, and save to
    // libraries-registry stream wrapper.
    $libraries_info = $this->moduleManager->invokeAll('libraries_info');
    $saved_count = 0;
    foreach ($libraries_info as $library_name => $data) {
      $this->preSerializeAlter($library_name, $data);
      $serialized = $this->serializer->serialize($data, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
      if ($serialized) {
        $this->postSerializeAlter($library_name, $serialized);
        $filename = $library_name . '.json';
        $saved_path = file_unmanaged_save_data($serialized, 'libraries-registry://' . $filename, FILE_EXISTS_REPLACE);
        $saved_count += !empty($saved_path);
      }
    }
    drupal_set_message(t('@count registry files were updated.', ['@count' => $saved_count]));
  }

  /**
   * Alter library array date before serialize.
   *
   * @param string $library_name
   *   The machine name of the library.
   * @param array $library_data
   *   An array of library data as fetced from hook_libraries_info to be
   *   altered.
   */
  protected function preSerializeAlter(&$library_name, array &$library_data) {
    // Get an id based on library name. We'll also use this as the raw filename.
    $id = strtolower(preg_replace('/[^0-9a-zA-Z]/', '_', $library_name));
    $library_name = $id;
    // Add a type to the TOP of the data (assume an asset library for now).
    $library_data = array('type' => 'asset') + $library_data;
    // Callbacks are likely no longer relevant.
    unset($library_data['callbacks']);
    // Process files including variant support.
    if (!empty($library_data['files'])) {
      $this->convertFiles($library_name, $library_data);
    }
    if (!empty($library_data['variants'])) {
      foreach ($library_data['variants'] as $variant_name => &$variant_data) {
        $this->convertFiles($library_name, $variant_data);
      }
    }
    // Process version detection details.
    $this->convertVersionDef($library_name, $library_data);
  }

  /**
   * Utility: convert D7 files array to D8 structures.
   *
   * @param string $library_name
   *   The machine name of the library.
   * @param array $element
   *   A library definition array element to test for files data and convert if
   *   needed.
   */
  private function convertFiles($library_name, array &$element) {
    if (!empty($element['files'])) {
      // Process CSS definitions.
      if (!empty($element['files']['css'])) {
        $element['css']['base'] = []; // Assume base for modules
        foreach ($element['files']['css'] as $file => $data) {
          $element['css']['base'][$file] = $data;
        }
      }
      // Process JS
      if (!empty($element['files']['js'])) {
        $element['js'] = $element['files']['js'];
      }
      unset($element['files']);
    }
  }

  /**
   * Utility: Convert D7 version def info to D8 structures.
   *
   * @param string $library_name
   *   The machine name of the library.
   * @param array $library_data
   *   An array of library data as fetced from hook_libraries_info to be
   *   altered.
   */
  private function convertVersionDef($library_name, array &$library_data) {
    // Detect line pattern version config.
    if (!empty($library_data['version arguments']['pattern'])) {
      $library_data['version_detector'] = [
        'id' => 'line_pattern',
        'configuration' => $library_data['version arguments'],
      ];
      // "cols" key has changed to "columns"
      if (!empty($library_data['version_detector']['configuration']['cols'])) {
        $library_data['version_detector']['configuration']['columns'] = $library_data['version_detector']['configuration']['cols'];
        unset($library_data['version_detector']['configuration']['cols']);
      }
    }
    // Handle some version callbacks that can be programmatically mapped to
    // known D8 detection plugins.
    elseif (!empty($library_data['version callback']) && !empty($library_data['version arguments'])) {
      switch ($library_data['version callback']) {
        // Library pack uses a callback to force a static version.
        case '_library_pack_force_version':
          $library_data['version_detector'] = [
            'id' => 'static',
            'configuration' => [
              'version' => $library_data['version arguments']['force'],
            ],
          ];
          break;
        default:
          drupal_set_message(t('@lib: A version callback was specified but the implmenting logic could not be progrmatically converted.', ['@lib' => $library_name]), 'error');
      }
    }
    else {
      drupal_set_message(t('@lib: Version detection details could not be determined.', ['@lib' => $library_name]), 'error');
    }
    // Cleanup the old structures that have no meaning in D8.
    unset($library_data['version callback']);
    unset($library_data['version arguments']);
  }

  /**
   * {@inheritdoc}
   */
  protected function postSerializeAlter(&$library_name, &$library_data_serialized) {

  }

}
