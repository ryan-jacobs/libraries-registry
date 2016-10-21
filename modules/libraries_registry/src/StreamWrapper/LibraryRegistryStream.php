<?php

/**
 * @file
 * Contains \Drupal\libraries\StreamWrapper\LibraryDefinitionsStream.
 */

namespace Drupal\libraries_registry\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Provides a stream wrapper for library definitions.
 *
 * Can be used with the 'library-definitions' scheme, for example
 * 'library-definitions://example.json'.
 *
 * @see \Drupal\locale\StreamWrapper\TranslationsStream
 */
class LibraryRegistryStream extends LocalStream {

  /**
   * The config factory
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an external library registry.
   *
   * @todo Dependency injection.
   */
  public function __construct() {
    $this->configFactory = \Drupal::configFactory();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('Library registry');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Provides access to library registry files.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    // The registry is actually part of the module, so we define this stream
    // wrapper relative to it. This of course means that a user would need the
    // appropraite (and non-typical) web-server write permissions on this
    // directory. This is certainly not best/standard practice, but this is only
    // for development use cases.
    $path = drupal_get_path('module', 'libraries_registry') . '/../../registry/8';
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  function getExternalUrl() {
    throw new \LogicException("{$this->getName()} should not be public.");
  }

}
