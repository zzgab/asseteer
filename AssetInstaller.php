<?php

namespace asseteer;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Composer\Script\Event;

class AssetInstaller
{
  const extraKey = 'post-install-asseteer';
  const vendorKey = 'vendor';
  const includeFitersKey = 'filters';
  const targetKey = 'target';

  private static $vendorOfAssetsToCopy = '';
  private static $vendorDir = '';


  public static function postInstall(Event $event)
  {

    $extra = $event->getComposer()->getPackage()->getExtra();
    if ( (! is_array($extra)) || (! array_key_exists(self::extraKey, $extra)) ) {
      return;
    }

    $postInstallConfig = $extra[self::extraKey];
    if ( ! is_array($postInstallConfig)) {
      return;
    }

    self::$vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

    foreach ($postInstallConfig as $entry) {
      self::performPostInstallEntry($entry);
    }
  }

  private static function performPostInstallEntry(array $entry) {

    $includeFilters = [];
    if (array_key_exists(self::includeFitersKey, $entry)) {
      if (is_array($entry[self::includeFitersKey])) {
        $includeFilters = $entry[self::includeFitersKey];
      }
      else {
        $includeFilters = [ $entry[self::includeFitersKey] ];
      }
    }

    $vendorAssetsRootFolder = $entry[self::vendorKey];
    self::$vendorOfAssetsToCopy = self::$vendorDir .'/' . $vendorAssetsRootFolder;


    $targetPath = $entry[self::targetKey];

    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$vendorOfAssetsToCopy),
      RecursiveIteratorIterator::SELF_FIRST);

    $matches = null;
    foreach($objects as $name => $object){
      if (is_dir($name)) {
        continue;
      }
      foreach ($includeFilters as $filter) {
        if (preg_match('#' . $filter . '#', $name)) {
          self::copy($name, $targetPath);
          break;
        }
      }
    }

  }

  private static function copy($filename, $targetPath)
  {
    $relativeFilename = preg_replace('#^'.self::$vendorOfAssetsToCopy.'/#', '', $filename);

    $directory = $targetPath . '/' . dirname($relativeFilename);
    if (! is_dir($directory)) {
      mkdir($directory, 0766, true);
    }

    $targetfile = $directory . '/' . basename($filename);
    if ( (! file_exists($targetfile)) || (md5_file($targetfile) != md5_file($filename)) ) {
      echo "  [copy] $targetfile\n";
      chmod($filename, 0766);
      copy($filename, $targetfile);
      chmod($targetfile, 0766);
    }
  }
}
