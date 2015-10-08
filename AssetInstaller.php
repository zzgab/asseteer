<?php

namespace asseteer;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Composer\Script\Event;
use Composer\Repository\PackageRepository;
use Composer\Package\Package;
use Composer\Util\Filesystem;
use Composer\IO\IOInterface;

class AssetInstaller
{
  const extraKey = 'post-install-asseteer';
  const packageExtraKey = 'asseteer';
  const vendorKey = 'vendor';
  const includeFitersKey = 'filters';
  const targetKey = 'target';

  private static $vendorOfAssetsToCopy = '';
  private static $vendorDir = '';

  public static function postUpdate(Event $event)
  {
    $repositoryManager = $event->getComposer()->getRepositoryManager();
    $installationManager = $event->getComposer()->getInstallationManager();
    $fs = new Filesystem();
    $io = $event->getIO();

    $repositories = $repositoryManager->getRepositories();
    foreach ($repositories as $repository) {
      if ($repository instanceof PackageRepository) {

        $package = $repository->getPackages()[0];
        $extra = $package->getExtra();
        if (array_key_exists(self::packageExtraKey, $extra) && is_array($extra[self::packageExtraKey])) {


          foreach ($extra[self::packageExtraKey] as $url) {
            $newPackage = new Package($package->getName(), $package->getVersion(), $package->getPrettyVersion());
            $newPackage->setType($package->getType());
            $newPackage->setDistUrl($url);
            $newPackage->setDistReference(sha1($url));

            $installationPath = $installationManager->getInstallPath($package);
            $filename = basename($url);
            $targetFile = $installationPath.'/'.$filename;
            if (! file_exists($targetFile)) {
              $io->write('  [Asseteer] Fetching URL: ' . $url);

              $tmpDir = sys_get_temp_dir() . '/asseteer-' . md5($url);
              $fs->ensureDirectoryExists($tmpDir);
              $event->getComposer()->getDownloadManager()->getDownloader('file')->download($newPackage, $tmpDir);

              $fs->rename($tmpDir . '/' . $filename, $targetFile);
              $fs->remove($tmpDir);
            }
          }
        }

      }
    }
  }

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

    $io = $event->getIO();
    foreach ($postInstallConfig as $entry) {
      self::performPostInstallEntry($entry, $io);
    }
  }

  private static function performPostInstallEntry(array $entry, IOInterface $io) {

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
          self::copy($name, $targetPath, $io);
          break;
        }
      }
    }

  }

  private static function copy($filename, $targetPath, IOInterface $io)
  {
    $relativeFilename = preg_replace('#^'.self::$vendorOfAssetsToCopy.'/#', '', $filename);

    $directory = $targetPath . '/' . dirname($relativeFilename);
    if (! is_dir($directory)) {
      mkdir($directory, 0766, true);
    }

    $targetfile = $directory . '/' . basename($filename);
    if ( (! file_exists($targetfile)) || (md5_file($targetfile) != md5_file($filename)) ) {
      $io->write("  [copy] $targetfile");
      chmod($filename, 0766);
      copy($filename, $targetfile);
      chmod($targetfile, 0766);
    }
  }
}
