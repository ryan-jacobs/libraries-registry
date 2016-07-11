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
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Process'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Loop through libraries_info hooks, serialize, and save to
    // library-registry stream wrapper.
    $libraries_info = $this->moduleManager->invokeAll('libraries_info');
    $saved_count = 0;
    foreach ($libraries_info as $library_name => $data) {
      $this->preSerializeAlter($library_name, $data);
      $serialized = $this->serializer->serialize($data, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
      if ($serialized) {
        $this->postSerializeAlter($library_name, $serialized);
        $filename = $library_name . '.json';
        $saved_path = file_unmanaged_save_data($serialized, 'library-registry://' . $filename, FILE_EXISTS_REPLACE);
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
    // @todo: Make common programmatic changes to library data to support D8
    // structures.
  }

  /**
   * {@inheritdoc}
   */
  protected function postSerializeAlter(&$library_name, &$library_data_serialized) {

  }

}
