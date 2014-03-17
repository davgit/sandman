<?php
/**
 * This file is part of DreamFactory Sandman(tm)
 *
 * Copyright (c) 2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Sandman\Commands;

use DreamFactory\Sandman\Sandman;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Utility\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Kevin Ran <kran@adobe.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jerry Ablan <jerryablan@dreamfactory.com>
 */
class SelfUpdateCommand extends Command
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const SITE_URL = 'dreamfactorysoftware.github.io';
    /** @type string */
    const OLD_INSTALL_EXT = '-old.phar';

    /**
     *
     */
    protected function configure()
    {
        $this->setName( 'self-update' )->setAliases( array( 'selfupdate' ) )->setDescription( 'Updates sandman.phar to the latest version.' )->setDefinition(
            array(
                new InputOption( 'rollback', 'r', InputOption::VALUE_NONE, 'Revert to an older installation of sandman' ),
                new InputOption( 'clean-backups', null, InputOption::VALUE_NONE, 'Delete old backups during an update. This makes the current version of sandman the only backup available after the update' ),
                new InputArgument( 'version', InputArgument::OPTIONAL, 'The version to which to update' ),
            )
        )->setHelp(
                <<<EOT
The <info>self-update</info> command checks {static::SITE_URL} for newer versions
of sandman and if found, installs the latest.

<info>php sandman.phar self-update</info>

EOT
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $_baseUrl = ( extension_loaded( 'openssl' ) ? 'https' : 'http' ) . '://' . static::SITE_URL;
        $remoteFilesystem = new RemoteFilesystem( $this->getIO() );
        $config = Factory::createConfig();
        $cacheDir = $config->get( 'cache-dir' );
        $rollbackPath = $config->get( 'home' );
        $localFilename = realpath( $_SERVER['argv'][0] ) ? : $_SERVER['argv'][0];

        // check if current dir is writable and if not try the cache dir from settings
        $tmpDir = is_writable( dirname( $localFilename ) ) ? dirname( $localFilename ) : $cacheDir;

        // check for permissions in local filesystem before start connection process
        if ( !is_writable( $tmpDir ) )
        {
            throw new FilesystemException( 'Sandman update failed: the "' . $tmpDir . '" directory used to download the temp file could not be written' );
        }
        if ( !is_writable( $localFilename ) )
        {
            throw new FilesystemException( 'Sandman update failed: the "' . $localFilename . '" file could not be written' );
        }

        if ( $input->getOption( 'rollback' ) )
        {
            return $this->rollback( $output, $rollbackPath, $localFilename );
        }

        $latestVersion = trim( $remoteFilesystem->getContents( static::SITE_URL, $_baseUrl . '/version', false ) );
        $updateVersion = $input->getArgument( 'version' ) ? : $latestVersion;

        if ( preg_match( '{^[0-9a-f]{40}$}', $updateVersion ) && $updateVersion !== $latestVersion )
        {
            $output->writeln( '<error>You can not update to a specific SHA-1 as those phars are not available for download</error>' );

            return 1;
        }

        if ( Sandman::PACKAGE_VERSION === $updateVersion )
        {
            $output->writeln( '<info>You are already using sandman version ' . $updateVersion . '.</info>' );

            return 0;
        }

        $tempFilename = $tmpDir . '/' . basename( $localFilename, '.phar' ) . '-temp.phar';
        $backupFile = sprintf(
            '%s/%s-%s%s',
            $rollbackPath,
            strtr( Sandman::RELEASE_DATE, ' :', '_-' ),
            preg_replace( '{^([0-9a-f]{7})[0-9a-f]{33}$}', '$1', Sandman::PACKAGE_VERSION ),
            static::OLD_INSTALL_EXT
        );

        $output->writeln( sprintf( "Updating to version <info>%s</info>.", $updateVersion ) );
        $remoteFilename = $_baseUrl . ( preg_match( '{^[0-9a-f]{40}$}', $updateVersion ) ? '/sandman.phar' : "/download/{$updateVersion}/sandman.phar" );
        $remoteFilesystem->copy( static::SITE_URL, $remoteFilename, $tempFilename );
        if ( !file_exists( $tempFilename ) )
        {
            $output->writeln( '<error>The download of the new sandman version failed for an unexpected reason</error>' );

            return 1;
        }

        // remove saved installations of sandman
        if ( $input->getOption( 'clean-backups' ) )
        {
            $files = $this->getOldInstallationFiles( $rollbackPath );

            if ( !empty( $files ) )
            {
                $fs = new Filesystem;

                foreach ( $files as $file )
                {
                    $output->writeln( '<info>Removing: ' . $file . '</info>' );
                    $fs->remove( $file );
                }
            }
        }

        if ( $err = $this->setLocalPhar( $localFilename, $tempFilename, $backupFile ) )
        {
            $output->writeln( '<error>The file is corrupted (' . $err->getMessage() . ').</error>' );
            $output->writeln( '<error>Please re-run the self-update command to try again.</error>' );

            return 1;
        }

        if ( file_exists( $backupFile ) )
        {
            $output->writeln( 'Use <info>sandman self-update --rollback</info> to return to version ' . Sandman::PACKAGE_VERSION );
        }
        else
        {
            $output->writeln( '<warning>A backup of the current version could not be written to ' . $backupFile . ', no rollback possible</warning>' );
        }
    }

    /**
     * @param OutputInterface $output
     * @param                 $rollbackPath
     * @param                 $localFilename
     *
     * @return int
     * @throws \UnexpectedValueException
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    protected function rollback( OutputInterface $output, $rollbackPath, $localFilename )
    {
        $rollbackVersion = $this->getLastBackupVersion( $rollbackPath );
        if ( !$rollbackVersion )
        {
            throw new \UnexpectedValueException( 'Sandman rollback failed: no installation to roll back to in "' . $rollbackPath . '"' );
        }

        if ( !is_writable( $rollbackPath ) )
        {
            throw new FilesystemException( 'Sandman rollback failed: the "' . $rollbackPath . '" dir could not be written to' );
        }

        $old = $rollbackPath . '/' . $rollbackVersion . static::OLD_INSTALL_EXT;

        if ( !is_file( $old ) )
        {
            throw new FilesystemException( 'Sandman rollback failed: "' . $old . '" could not be found' );
        }
        if ( !is_readable( $old ) )
        {
            throw new FilesystemException( 'Sandman rollback failed: "' . $old . '" could not be read' );
        }

        $oldFile = $rollbackPath . "/{$rollbackVersion}" . static::OLD_INSTALL_EXT;
        $output->writeln( sprintf( "Rolling back to version <info>%s</info>.", $rollbackVersion ) );
        if ( $err = $this->setLocalPhar( $localFilename, $oldFile ) )
        {
            $output->writeln( '<error>The backup file was corrupted (' . $err->getMessage() . ') and has been removed.</error>' );

            return 1;
        }

        return 0;
    }

    /**
     * @param      $localFilename
     * @param      $newFilename
     * @param null $backupTarget
     *
     * @return \Exception
     * @throws \Exception
     */
    protected function setLocalPhar( $localFilename, $newFilename, $backupTarget = null )
    {
        try
        {
            @chmod( $newFilename, 0777 & ~umask() );
            // test the phar validity
            $phar = new \Phar( $newFilename );
            // free the variable to unlock the file
            unset( $phar );

            // copy current file into installations dir
            if ( $backupTarget && file_exists( $localFilename ) )
            {
                @copy( $localFilename, $backupTarget );
            }

            unset( $phar );
            rename( $newFilename, $localFilename );
        }
        catch ( \Exception $e )
        {
            if ( $backupTarget )
            {
                @unlink( $newFilename );
            }
            if ( !$e instanceof \UnexpectedValueException && !$e instanceof \PharException )
            {
                throw $e;
            }

            return $e;
        }
    }

    /**
     * @param $rollbackPath
     *
     * @return bool|string
     */
    protected function getLastBackupVersion( $rollbackPath )
    {
        $files = $this->getOldInstallationFiles( $rollbackPath );
        if ( empty( $files ) )
        {
            return false;
        }

        sort( $files );

        return basename( end( $files ), static::OLD_INSTALL_EXT );
    }

    /**
     * @param $rollbackPath
     *
     * @return array
     */
    protected function getOldInstallationFiles( $rollbackPath )
    {
        return glob( $rollbackPath . '/*' . static::OLD_INSTALL_EXT ) ? : array();
    }
}
